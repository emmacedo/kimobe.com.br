<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImovelRequest;
use App\Http\Requests\UpdateImovelRequest;
use App\Models\Imovel;
use App\Models\Vinculo;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImovelController extends Controller
{
    use ScopesPorPapel;
    /**
     * Listagem de imóveis com filtros, paginação e eager load.
     * O TenantScope já filtra automaticamente por tenant.
     */
    public function index(Request $request): Response
    {
        $query = Imovel::query()
            ->with(['fotoPrincipal', 'titularidades.vinculo.user']);

        // Scoping por papel: proprietário vê apenas seus imóveis
        $this->scopeImoveisDoUsuario($query);

        // Filtro de busca por endereço, bairro ou cidade
        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('logradouro', 'like', "%{$busca}%")
                    ->orWhere('complemento', 'like', "%{$busca}%")
                    ->orWhere('bairro', 'like', "%{$busca}%")
                    ->orWhere('cidade', 'like', "%{$busca}%");
            });
        }

        // Filtro por tipo
        if ($tipo = $request->input('tipo')) {
            $query->where('tipo', $tipo);
        }

        // Filtro por status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Contagens por status (com mesmo scoping)
        $contagemQuery = Imovel::query()->selectRaw("status, count(*) as total")->groupBy('status');
        $this->scopeImoveisDoUsuario($contagemQuery);
        $contagens = $contagemQuery->pluck('total', 'status');

        $imoveis = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('imoveis/index', [
            'imoveis' => $imoveis,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'tipo' => $request->input('tipo', ''),
                'status' => $request->input('status', ''),
            ],
            'contagens' => $contagens,
            'pode_adicionar_imovel' => app(TenantService::class)->getTenant()?->podeAdicionarImovel() ?? true,
        ]);
    }

    /**
     * Formulário de criação de imóvel.
     */
    public function create(): Response
    {
        return Inertia::render('imoveis/criar');
    }

    /**
     * Salva um novo imóvel.
     */
    public function store(StoreImovelRequest $request): RedirectResponse
    {
        // Verificar limite do plano
        $tenant = app(TenantService::class)->getTenant();
        if ($tenant && ! $tenant->podeAdicionarImovel()) {
            $limite = $tenant->planoAssinatura?->limite_imoveis ?? 0;
            return back()->withErrors([
                'limite' => "Você atingiu o limite de imóveis do seu plano ({$limite} imóveis). Faça upgrade para cadastrar mais.",
            ]);
        }

        $dados = $request->validated();

        // Status default se não informado
        if (empty($dados['status'])) {
            $dados['status'] = 'disponivel';
        }

        $imovel = Imovel::create($dados);

        // Redireciona para edição para permitir upload de fotos e titulares imediatamente
        return redirect()->route('imoveis.edit', $imovel)
            ->with('success', 'Imóvel cadastrado com sucesso. Agora adicione fotos e titulares.');
    }

    /**
     * Exibe os detalhes de um imóvel.
     */
    public function show(Imovel $imovel): Response
    {
        // Validar acesso por papel
        abort_unless($this->podeVerImovel($imovel), 403);

        $imovel->load([
            'fotos',
            'fotoPrincipal',
            'titularidades.vinculo.user',
            'titularidades.dadosBancarios',
            'contratos.inquilino.user',
        ]);

        return Inertia::render('imoveis/mostrar', [
            'imovel' => $imovel,
        ]);
    }

    /**
     * Formulário de edição de imóvel.
     */
    public function edit(Imovel $imovel): Response
    {
        $imovel->load([
            'fotos',
            'titularidades.vinculo.user',
            'titularidades.dadosBancarios',
        ]);

        $tenantId = app(TenantService::class)->getTenantId();

        // IDs dos vínculos que já são titulares deste imóvel
        $titularVinculoIds = $imovel->titularidades->pluck('vinculo_id')->toArray();

        // Proprietários disponíveis: vínculos com papel=proprietario no tenant que NÃO são titulares
        $proprietariosDisponiveis = Vinculo::where('tenant_id', $tenantId)
            ->where('papel', 'proprietario')
            ->where('status', 'ativo')
            ->whereNotIn('id', $titularVinculoIds)
            ->with('user')
            ->get();

        return Inertia::render('imoveis/editar', [
            'imovel' => $imovel,
            'proprietariosDisponiveis' => $proprietariosDisponiveis,
        ]);
    }

    /**
     * Atualiza um imóvel existente.
     */
    public function update(UpdateImovelRequest $request, Imovel $imovel): RedirectResponse
    {
        $imovel->update($request->validated());

        return redirect()->route('imoveis.show', $imovel)
            ->with('success', 'Imóvel atualizado com sucesso.');
    }

    /**
     * Soft delete de um imóvel.
     * Não permite excluir se houver contratos ativos vinculados.
     */
    public function destroy(Imovel $imovel): RedirectResponse
    {
        $contratosAtivos = $imovel->contratos()
            ->where('status', 'ativo')
            ->count();

        if ($contratosAtivos > 0) {
            return back()->withErrors([
                'imovel' => "Este imóvel possui {$contratosAtivos} contrato(s) ativo(s). Não é possível excluí-lo enquanto houver contratos vinculados.",
            ]);
        }

        $imovel->delete();

        return redirect()->route('imoveis.index')
            ->with('success', 'Imóvel excluído com sucesso.');
    }
}
