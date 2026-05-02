<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContratoRequest;
use App\Http\Requests\UpdateContratoRequest;
use App\Models\Contrato;
use App\Models\Garantia;
use App\Models\Imovel;
use App\Models\Vinculo;
use App\Services\NotificacaoAdminService;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    use ScopesPorPapel;

    /**
     * Listagem de contratos com filtros, paginação e eager load.
     */
    public function index(Request $request): Response
    {
        $query = Contrato::query()
            ->with(['imovel', 'inquilino.user']);

        // Scoping por papel
        $this->scopeContratosDoUsuario($query);

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->whereHas('imovel', function ($qi) use ($busca) {
                    $qi->where('logradouro', 'like', "%{$busca}%")
                        ->orWhere('complemento', 'like', "%{$busca}%")
                        ->orWhere('bairro', 'like', "%{$busca}%")
                        ->orWhere('cidade', 'like', "%{$busca}%");
                })->orWhereHas('inquilinos.vinculo.user', function ($qu) use ($busca) {
                    // Busca em todos os inquilinos do contrato (principal + co-inquilinos)
                    $qu->where('name', 'like', "%{$busca}%");
                });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($modelo = $request->input('modelo_repasse')) {
            $query->where('modelo_repasse', $modelo);
        }

        if ($indice = $request->input('indice_reajuste')) {
            $query->where('indice_reajuste', $indice);
        }

        $contratos = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('contratos/index', [
            'contratos' => $contratos,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'status' => $request->input('status', ''),
                'modelo_repasse' => $request->input('modelo_repasse', ''),
                'indice_reajuste' => $request->input('indice_reajuste', ''),
            ],
        ]);
    }

    /**
     * Formulário de criação de contrato.
     * Listas pré-carregadas foram substituídas por autocompletes server-side
     * (endpoint imoveisDisponiveis e /inquilinos/buscar).
     */
    public function create(): Response
    {
        return Inertia::render('contratos/criar');
    }

    /**
     * Endpoint JSON para autocomplete de imóveis disponíveis para novo contrato.
     * Filtra: imóveis sem contrato com status='ativo'. Busca AND por palavra em
     * endereço (logradouro/complemento/bairro/cidade) e nome dos titulares.
     */
    public function imoveisDisponiveis(Request $request): JsonResponse
    {
        $termo = trim((string) $request->input('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $palavras = array_filter(explode(' ', $termo));

        $imoveis = Imovel::query()
            ->whereDoesntHave('contratos', fn ($q) => $q->where('status', 'ativo'))
            ->with('titularidades.vinculo.user')
            ->where(function ($q) use ($palavras) {
                foreach ($palavras as $palavra) {
                    $like = '%'.$palavra.'%';
                    $q->where(function ($qq) use ($like) {
                        $qq->where('logradouro', 'like', $like)
                            ->orWhere('complemento', 'like', $like)
                            ->orWhere('bairro', 'like', $like)
                            ->orWhere('cidade', 'like', $like)
                            ->orWhereHas('titularidades.vinculo.user', fn ($qu) => $qu->where('name', 'like', $like));
                    });
                }
            })
            ->limit(20)
            ->get();

        return response()->json($imoveis->map(fn ($im) => [
            'id' => $im->id,
            'logradouro' => $im->logradouro,
            'numero' => $im->numero,
            'complemento' => $im->complemento,
            'bairro' => $im->bairro,
            'cidade' => $im->cidade,
            'uf' => $im->uf,
            'tipo' => $im->tipo,
            'valor_aluguel_sugerido' => $im->valor_aluguel_sugerido,
            'titularidades' => $im->titularidades->map(fn ($t) => [
                'vinculo' => ['user' => ['name' => $t->vinculo->user->name]],
                'percentual' => $t->percentual,
                'papel' => $t->papel,
            ]),
        ]));
    }

    /**
     * Salva um novo contrato com seus inquilinos (principal + co-inquilinos).
     * Cria garantia se aplicável e muda status do imóvel para alugado.
     */
    public function store(StoreContratoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $tenantId = app(TenantService::class)->getTenantId();

        // Extrai inquilinos (lista) — o cache 'inquilino_vinculo_id' é definido pelo principal.
        $inquilinos = $dados['inquilinos'] ?? [];
        unset($dados['inquilinos']);
        $principal = collect($inquilinos)->firstWhere('principal', true);

        // Defensive: a validação cruzada já bloqueia esse caso, mas garante que não dá NULL deref
        // se o validator passar por estado inconsistente.
        if (! $principal) {
            return back()->withErrors(['inquilinos' => 'Marque exatamente 1 inquilino como principal.']);
        }

        $dados['inquilino_vinculo_id'] = $principal['vinculo_id'];

        // Separar dados de garantia
        $garantiaDados = $this->extrairDadosGarantia($dados);

        // Remover campos de garantia do array do contrato
        $contratoData = collect($dados)->except([
            'garantia_valor', 'garantia_seguradora', 'garantia_numero_apolice',
            'garantia_numero_titulo', 'garantia_data_inicio', 'garantia_data_fim',
        ])->toArray();

        $contrato = DB::transaction(function () use ($contratoData, $inquilinos, $garantiaDados, $dados, $tenantId) {
            $contrato = Contrato::create($contratoData);

            // Popula a pivot contrato_inquilinos com todos os inquilinos do contrato.
            foreach ($inquilinos as $inq) {
                $contrato->inquilinos()->create([
                    'tenant_id' => $tenantId,
                    'vinculo_id' => $inq['vinculo_id'],
                    'principal' => $inq['principal'],
                ]);
            }

            // Garantia (exceto fiador — que é cadastrado depois — e sem_garantia)
            if ($garantiaDados && ! in_array($dados['tipo_garantia'], ['sem_garantia', 'fiador'])) {
                Garantia::create([
                    'tenant_id' => $tenantId,
                    'contrato_id' => $contrato->id,
                    'tipo' => $dados['tipo_garantia'],
                    ...$garantiaDados,
                ]);
            }

            // Marca imóvel como alugado.
            Imovel::where('id', $dados['imovel_id'])->update(['status' => 'alugado']);

            return $contrato;
        });

        // Notificar proprietários sobre novo contrato (fora da transação para não rollback em falha de email)
        try {
            app(NotificacaoAdminService::class)->notificarNovoContratoProprietarios($contrato);
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar novo contrato: {$e->getMessage()}");
        }

        return redirect()->route('contratos.edit', $contrato)
            ->with('success', 'Contrato criado com sucesso. Agora defina as responsabilidades financeiras.');
    }

    /**
     * Detalhes de um contrato.
     */
    public function show(Contrato $contrato): Response
    {
        $contrato->load(['imovel', 'inquilino']);
        abort_unless($this->podeVerContrato($contrato), 403);

        $contrato->load([
            'imovel.fotoPrincipal',
            'imovel.titularidades.vinculo.user',
            'imovel.titularidades.dadosBancarios',
            'inquilino.user',
            'inquilinos.vinculo.user',
            'responsabilidades',
            'garantia',
            'fiadores',
        ]);

        $cobrancas = $contrato->cobrancas()
            ->orderBy('data_vencimento', 'desc')
            ->limit(5)
            ->get();

        // Contato do admin para inquilinos
        $contatoAdmin = null;
        if ($this->isInquilino() && ! $this->isAdmin()) {
            $adminVinculo = Vinculo::where('tenant_id', app(TenantService::class)->getTenantId())
                ->where('papel', 'admin')
                ->where('status', 'ativo')
                ->with('user')
                ->first();
            if ($adminVinculo) {
                $contatoAdmin = [
                    'nome' => $adminVinculo->user->name,
                    'email' => $adminVinculo->user->email,
                ];
            }
        }

        return Inertia::render('contratos/mostrar', [
            'contrato' => $contrato,
            'cobrancasRecentes' => $cobrancas,
            'contatoAdmin' => $contatoAdmin,
        ]);
    }

    /**
     * Formulário de edição de contrato.
     */
    public function edit(Contrato $contrato): Response
    {
        $contrato->load([
            'imovel.titularidades.vinculo.user',
            'inquilino.user',
            'inquilinos.vinculo.user',
            'garantia',
            'responsabilidades',
            'fiadores',
        ]);

        return Inertia::render('contratos/editar', [
            'contrato' => $contrato,
        ]);
    }

    /**
     * Atualiza um contrato existente.
     * Atualiza/cria/remove garantia conforme o tipo.
     */
    public function update(UpdateContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        $dados = $request->validated();
        $tenantId = app(TenantService::class)->getTenantId();

        $garantiaDados = $this->extrairDadosGarantia($dados);

        $contratoData = collect($dados)->except([
            'garantia_valor', 'garantia_seguradora', 'garantia_numero_apolice',
            'garantia_numero_titulo', 'garantia_data_inicio', 'garantia_data_fim',
        ])->toArray();

        $contrato->update($contratoData);

        // Gerenciar garantia
        if (in_array($dados['tipo_garantia'], ['sem_garantia', 'fiador'])) {
            // Remover garantia existente se mudou para sem_garantia ou fiador
            $contrato->garantia?->delete();
        } elseif ($garantiaDados) {
            // Atualizar ou criar garantia
            $contrato->garantia
                ? $contrato->garantia->update(['tipo' => $dados['tipo_garantia'], ...$garantiaDados])
                : Garantia::create(['tenant_id' => $tenantId, 'contrato_id' => $contrato->id, 'tipo' => $dados['tipo_garantia'], ...$garantiaDados]);
        }

        return redirect()->route('contratos.show', $contrato)
            ->with('success', 'Contrato atualizado com sucesso.');
    }

    /**
     * Encerra um contrato ativo.
     */
    public function encerrar(Contrato $contrato): RedirectResponse
    {
        if ($contrato->status !== 'ativo') {
            return back()->withErrors(['contrato' => 'Apenas contratos ativos podem ser encerrados.']);
        }

        $contrato->update(['status' => 'encerrado']);
        $contrato->imovel->update(['status' => 'disponivel']);

        try {
            app(NotificacaoAdminService::class)->notificarContratoEncerradoProprietarios($contrato);
        } catch (\Throwable $e) { /* silencioso */
        }

        return redirect()->route('contratos.show', $contrato)
            ->with('success', 'Contrato encerrado com sucesso.');
    }

    /**
     * Cancela um contrato ativo (rescisão).
     */
    public function cancelar(Contrato $contrato): RedirectResponse
    {
        if ($contrato->status !== 'ativo') {
            return back()->withErrors(['contrato' => 'Apenas contratos ativos podem ser cancelados.']);
        }

        $contrato->update(['status' => 'cancelado']);
        $contrato->imovel->update(['status' => 'disponivel']);

        try {
            app(NotificacaoAdminService::class)->notificarContratoEncerradoProprietarios($contrato);
        } catch (\Throwable $e) { /* silencioso */
        }

        return redirect()->route('contratos.show', $contrato)
            ->with('success', 'Contrato cancelado.');
    }

    /**
     * Extrai e mapeia os dados de garantia do request para o formato do model.
     */
    private function extrairDadosGarantia(array $dados): ?array
    {
        $tipo = $dados['tipo_garantia'];

        if (in_array($tipo, ['sem_garantia', 'fiador'])) {
            return null;
        }

        return [
            'valor' => $dados['garantia_valor'] ?? null,
            'seguradora' => $dados['garantia_seguradora'] ?? null,
            'numero_apolice' => $dados['garantia_numero_apolice'] ?? null,
            'numero_titulo' => $dados['garantia_numero_titulo'] ?? null,
            'data_inicio' => $dados['garantia_data_inicio'] ?? null,
            'data_fim' => $dados['garantia_data_fim'] ?? null,
            'status' => 'ativo',
        ];
    }
}
