<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContratoRequest;
use App\Http\Requests\UpdateContratoRequest;
use App\Models\Contrato;
use App\Models\Garantia;
use App\Models\Imovel;
use App\Models\Vinculo;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                })->orWhereHas('inquilino.user', function ($qu) use ($busca) {
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
     */
    public function create(): Response
    {
        $tenantId = app(TenantService::class)->getTenantId();

        $imoveisDisponiveis = Imovel::where('status', 'disponivel')
            ->with('titularidades.vinculo.user')
            ->orderBy('logradouro')
            ->get();

        $inquilinosDisponiveis = Vinculo::where('tenant_id', $tenantId)
            ->where('papel', 'inquilino')
            ->where('status', 'ativo')
            ->with('user')
            ->get();

        return Inertia::render('contratos/criar', [
            'imoveisDisponiveis' => $imoveisDisponiveis,
            'inquilinosDisponiveis' => $inquilinosDisponiveis,
        ]);
    }

    /**
     * Salva um novo contrato.
     * Cria garantia se aplicável e muda status do imóvel para alugado.
     */
    public function store(StoreContratoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $tenantId = app(TenantService::class)->getTenantId();

        // Separar dados de garantia
        $garantiaDados = $this->extrairDadosGarantia($dados);

        // Remover campos de garantia do array do contrato
        $contratoData = collect($dados)->except([
            'garantia_valor', 'garantia_seguradora', 'garantia_numero_apolice',
            'garantia_numero_titulo', 'garantia_data_inicio', 'garantia_data_fim',
        ])->toArray();

        $contrato = Contrato::create($contratoData);

        // Criar garantia se não for 'sem_garantia' e não for 'fiador' (fiador é cadastrado depois)
        if ($garantiaDados && ! in_array($dados['tipo_garantia'], ['sem_garantia', 'fiador'])) {
            Garantia::create([
                'tenant_id' => $tenantId,
                'contrato_id' => $contrato->id,
                'tipo' => $dados['tipo_garantia'],
                ...$garantiaDados,
            ]);
        }

        // Marcar imóvel como alugado
        Imovel::where('id', $dados['imovel_id'])->update(['status' => 'alugado']);

        // Notificar proprietários sobre novo contrato
        try {
            app(\App\Services\NotificacaoAdminService::class)->notificarNovoContratoProprietarios($contrato);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Falha ao notificar novo contrato: {$e->getMessage()}");
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
            $adminVinculo = \App\Models\Vinculo::where('tenant_id', app(TenantService::class)->getTenantId())
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
            'garantia',
            'responsabilidades',
            'fiadores',
        ]);

        $tenantId = app(TenantService::class)->getTenantId();

        $inquilinosDisponiveis = Vinculo::where('tenant_id', $tenantId)
            ->where('papel', 'inquilino')
            ->where('status', 'ativo')
            ->with('user')
            ->get();

        return Inertia::render('contratos/editar', [
            'contrato' => $contrato,
            'inquilinosDisponiveis' => $inquilinosDisponiveis,
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

        try { app(\App\Services\NotificacaoAdminService::class)->notificarContratoEncerradoProprietarios($contrato); } catch (\Throwable $e) { /* silencioso */ }

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

        try { app(\App\Services\NotificacaoAdminService::class)->notificarContratoEncerradoProprietarios($contrato); } catch (\Throwable $e) { /* silencioso */ }

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
