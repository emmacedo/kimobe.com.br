<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContratoRequest;
use App\Http\Requests\UpdateContratoRequest;
use App\Models\Contrato;
use App\Models\EntidadeExterna;
use App\Models\Garantia;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\Vinculo;
use App\Services\ContratoAuditoriaService;
use App\Services\ItemCobrancaService;
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

        try {
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

                // Gera automaticamente o item de cobrança recorrente "Aluguel" — pré-gera
                // todas as ocorrências mensais até data_fim.
                $this->gerarItemAluguelInicial($contrato);
                $this->gerarItemTaxaAdminInicial($contrato);

                return $contrato;
            });
        } catch (\DomainException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'contrato' => "Não foi possível gerar o item de cobrança 'Aluguel' automaticamente: {$e->getMessage()}. Verifique os dados do contrato e tente novamente. Nenhuma informação foi salva.",
                ]);
        } catch (\Throwable $e) {
            Log::error("Falha ao criar contrato: {$e->getMessage()}", ['exception' => $e]);

            return back()
                ->withInput()
                ->withErrors([
                    'contrato' => 'Ocorreu um erro inesperado ao criar o contrato. Nenhuma informação foi salva. Por favor, tente novamente em alguns instantes.',
                ]);
        }

        // Notificar proprietários sobre novo contrato (fora da transação para não rollback em falha de email)
        try {
            app(NotificacaoAdminService::class)->notificarNovoContratoProprietarios($contrato);
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar novo contrato: {$e->getMessage()}");
        }

        return redirect()->route('contratos.edit', $contrato)
            ->with('success', 'Contrato criado com sucesso.');
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
            'garantia',
            'fiadores',
            'reajustes.alteradoPor:id,name',
        ]);

        $faturas = $contrato->faturas()
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

        // Timeline de auditoria — visível apenas para admin e proprietário (não inquilino)
        $timeline = null;
        if (! $this->isInquilino() || $this->isAdmin()) {
            $timeline = app(ContratoAuditoriaService::class)->montarTimeline($contrato);
        }

        // Gerenciador de itens de cobrança — apenas admin (operação rotineira)
        $itensCobranca = null;
        $entidadesExternas = null;
        if ($this->isAdmin()) {
            $itensCobranca = ItemCobranca::where('contrato_id', $contrato->id)
                ->whereNull('parent_item_id')
                ->with('entidadeExterna:id,nome,tipo')
                ->orderBy('created_at', 'desc')
                ->get();
            $entidadesExternas = EntidadeExterna::orderBy('nome')->get();
        }

        return Inertia::render('contratos/mostrar', [
            'contrato' => $contrato,
            'faturasRecentes' => $faturas,
            'contatoAdmin' => $contatoAdmin,
            'timelineAuditoria' => $timeline,
            'itensCobranca' => $itensCobranca,
            'entidadesExternas' => $entidadesExternas,
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

    /**
     * Cria a série recorrente mensal de "Aluguel" para o contrato recém-criado.
     * Pré-gera ocorrências de `data_inicio` até `data_fim` via ItemCobrancaService.
     */
    private function gerarItemAluguelInicial(Contrato $contrato): void
    {
        app(ItemCobrancaService::class)->criar($contrato, [
            'descricao' => 'Aluguel',
            'natureza' => 'aluguel',
            'pagante' => 'inquilino',
            'recebedor' => 'proprietario',
            'tipo' => 'recorrente',
            'periodicidade' => 'mensal',
            'valor_unitario' => $contrato->valor_aluguel,
            'dia_vencimento' => $contrato->dia_vencimento,
            'mes_referencia' => $contrato->data_inicio->format('m/Y'),
            'visivel_inquilino' => true,
        ]);
    }

    /**
     * Item de cobrança recorrente da taxa administrativa cobrada do
     * proprietário pela imobiliária. Espelha o padrão do aluguel mas
     * com pagante=proprietario e recebedor=administradora. Não é
     * reajustada automaticamente (filtro do reajuste é por natureza).
     */
    private function gerarItemTaxaAdminInicial(Contrato $contrato): void
    {
        $valor = $contrato->valorTaxaAdministrativa();
        if ($valor <= 0) {
            return;
        }

        app(ItemCobrancaService::class)->criar($contrato, [
            'descricao' => 'Taxa administrativa',
            'natureza' => 'taxa_admin',
            'pagante' => 'proprietario',
            'recebedor' => 'administradora',
            'tipo' => 'recorrente',
            'periodicidade' => 'mensal',
            'valor_unitario' => $valor,
            'dia_vencimento' => $contrato->dia_vencimento,
            'mes_referencia' => $contrato->data_inicio->format('m/Y'),
            'visivel_inquilino' => false,
        ]);
    }
}
