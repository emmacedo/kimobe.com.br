<?php

namespace App\Http\Controllers;

use App\Http\Concerns\EnsuresPlanCapacity;
use App\Http\Requests\StoreImovelRequest;
use App\Http\Requests\UpdateImovelRequest;
use App\Models\EntidadeExterna;
use App\Models\Imovel;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ImovelController extends Controller
{
    use EnsuresPlanCapacity, ScopesPorPapel;

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
        $contagemQuery = Imovel::query()->selectRaw('status, count(*) as total')->groupBy('status');
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
        return Inertia::render('imoveis/criar', [
            'entidadesExternas' => EntidadeExterna::where('tipo', 'administradora_condominio')->orderBy('nome')->get(),
        ]);
    }

    /**
     * Salva um novo imóvel, opcionalmente com condomínio e titulares informados juntos.
     */
    public function store(StoreImovelRequest $request): RedirectResponse
    {
        // Garante que o plano cobre — em caso de overage, AutoUpgradeService
        // sobe automaticamente para o próximo plano da escada.
        if ($redirect = $this->ensureTenantCapacity('imoveis', 1, 'cadastrar mais imóveis')) {
            return $redirect;
        }

        $dados = $request->validated();
        $condominioDados = $this->extrairDadosCondominio($dados);
        $titulares = $dados['titulares'] ?? [];
        unset($dados['titulares']);

        // Status default se não informado
        if (empty($dados['status'])) {
            $dados['status'] = 'disponivel';
        }

        $tenantId = app(TenantService::class)->getTenantId();

        $imovel = DB::transaction(function () use ($dados, $condominioDados, $titulares, $tenantId) {
            $imovel = Imovel::create($dados);

            if ($condominioDados !== null) {
                $imovel->condominio()->create($condominioDados);
            }

            foreach ($titulares as $titular) {
                $imovel->titularidades()->create([
                    'tenant_id' => $tenantId,
                    'vinculo_id' => $titular['vinculo_id'],
                    'tipo_titular' => $titular['tipo_titular'],
                    'papel' => $titular['papel'],
                    'percentual' => $titular['percentual'],
                    'dados_bancarios_id' => $titular['dados_bancarios_id'] ?? null,
                ]);
            }

            return $imovel;
        });

        // Mensagem contextual: se já tem titulares, vai direto para detalhes; senão, edição.
        if (count($titulares) > 0) {
            return redirect()->route('imoveis.show', $imovel)
                ->with('success', 'Imóvel cadastrado com sucesso.');
        }

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
            'condominio.entidadeExterna',
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
            'condominio',
        ]);

        return Inertia::render('imoveis/editar', [
            'imovel' => $imovel,
            'entidadesExternas' => EntidadeExterna::where('tipo', 'administradora_condominio')->orderBy('nome')->get(),
        ]);
    }

    /**
     * Atualiza um imóvel existente.
     */
    public function update(UpdateImovelRequest $request, Imovel $imovel): RedirectResponse
    {
        $dados = $request->validated();
        $condominioDados = $this->extrairDadosCondominio($dados);

        DB::transaction(function () use ($imovel, $dados, $condominioDados) {
            $imovel->update($dados);

            if ($condominioDados !== null) {
                // Restaura registro soft-deleted antes do upsert para evitar conflito
                // com a unique constraint em imovel_id.
                $existente = $imovel->condominio()->withTrashed()->first();
                if ($existente && $existente->trashed()) {
                    $existente->restore();
                }

                $imovel->condominio()->updateOrCreate([], $condominioDados);
            } else {
                // Se o usuário limpou todos os campos do condomínio, soft-delete o registro existente
                $imovel->condominio?->delete();
            }
        });

        return redirect()->route('imoveis.show', $imovel)
            ->with('success', 'Imóvel atualizado com sucesso.');
    }

    /**
     * Extrai e remove o sub-array 'condominio' dos dados validados.
     * Retorna null se não há dados significativos (todos os campos vazios).
     *
     * @param  array<string, mixed>  $dados  Modificado por referência para remover a chave 'condominio'.
     * @return array<string, mixed>|null
     */
    private function extrairDadosCondominio(array &$dados): ?array
    {
        $condominio = $dados['condominio'] ?? null;
        unset($dados['condominio']);

        if (! is_array($condominio)) {
            return null;
        }

        // Considera "vazio" se todos os campos significativos estão vazios
        $temAlgumDado = collect($condominio)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        return $temAlgumDado ? $condominio : null;
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
