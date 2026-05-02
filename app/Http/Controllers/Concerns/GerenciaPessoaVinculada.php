<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lógica compartilhada entre InquilinoController e ProprietarioController.
 * Ambos gerenciam um Vinculo (User × Tenant) com papel específico — o trait
 * abstrai o que é comum e exige que cada controller especialize via
 * métodos abstract: papel(), viewPrefix(), routePrefix(), validarPodeInativar(),
 * relacaoContagem(), nomeEntidade().
 */
trait GerenciaPessoaVinculada
{
    /**
     * Papel do vínculo: 'proprietario' ou 'inquilino'.
     */
    abstract protected function papel(): string;

    /**
     * Prefixo da view Inertia (ex: 'proprietarios', 'inquilinos').
     */
    abstract protected function viewPrefix(): string;

    /**
     * Prefixo das rotas nomeadas (ex: 'proprietarios', 'inquilinos').
     */
    abstract protected function routePrefix(): string;

    /**
     * Nome da relação para count na listagem (ex: 'titularidades', 'participacoesEmContratos').
     */
    abstract protected function relacaoContagem(): string;

    /**
     * Alias do count exposto ao frontend. Por padrão, snake_case da relação + '_count'.
     * Sobrescreva para manter retrocompatibilidade quando o nome da relação muda.
     */
    protected function aliasContagem(): string
    {
        return Str::snake($this->relacaoContagem()).'_count';
    }

    /**
     * Singular da entidade pra mensagens (ex: 'proprietário', 'inquilino').
     */
    abstract protected function nomeEntidade(): string;

    /**
     * Valida se o vínculo pode ser inativado. Retorna a mensagem de erro ou null.
     * Cada controller implementa a regra de negócio específica (proprietário com
     * titularidades vs inquilino com contratos ativos).
     */
    abstract protected function validarPodeInativar(Vinculo $vinculo): ?string;

    public function index(Request $request): Response
    {
        $tenantId = app(TenantService::class)->getTenantId();
        $busca = trim((string) $request->input('busca', ''));
        $tipoPessoa = $request->input('tipo_pessoa');
        $incluirInativos = $request->boolean('incluir_inativos');

        $query = Vinculo::query()
            ->where('tenant_id', $tenantId)
            ->where('papel', $this->papel())
            ->with('user')
            ->withCount([$this->relacaoContagem().' as '.$this->aliasContagem() => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])]);

        if (! $incluirInativos) {
            $query->where('status', 'ativo');
        }

        if ($busca !== '') {
            $palavras = array_filter(explode(' ', $busca));
            $query->whereHas('user', fn ($q) => $this->aplicarFiltroTermos($q, $palavras));
        }

        if (in_array($tipoPessoa, ['pf', 'pj'], true)) {
            $query->whereHas('user', fn ($q) => $q->where('tipo_pessoa', $tipoPessoa));
        }

        $registros = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        // Mascara emails placeholder antes de enviar à UI.
        $registros->getCollection()->transform(function ($vinculo) {
            if ($vinculo->user && $vinculo->user->hasPlaceholderEmail()) {
                $vinculo->user->email = null;
                $vinculo->user->setAttribute('email_placeholder', true);
            }

            return $vinculo;
        });

        return Inertia::render($this->viewPrefix().'/index', [
            $this->viewPrefix() => $registros,
            'filtros' => [
                'busca' => $busca,
                'tipo_pessoa' => $tipoPessoa ?? '',
                'incluir_inativos' => $incluirInativos,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render($this->viewPrefix().'/criar');
    }

    /**
     * Helper para o controller chamar com seu Request tipado. Mantém Laravel
     * resolvendo o tipo correto via método do controller.
     */
    protected function storeBase(FormRequest $request): RedirectResponse
    {
        $this->criarPessoa($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', ucfirst($this->nomeEntidade()).' cadastrado com sucesso.');
    }

    protected function storeInlineBase(FormRequest $request): JsonResponse
    {
        $vinculo = $this->criarPessoa($request->validated());
        $vinculo->load('user');

        return response()->json($this->formatarParaFrontend($vinculo), 201);
    }

    /**
     * Endpoint de busca AND por palavra: cada palavra deve aparecer em pelo menos
     * um dos campos name/email/documento.
     */
    public function buscar(Request $request): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();
        $termo = trim((string) $request->input('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $palavras = array_filter(explode(' ', $termo));

        $vinculos = Vinculo::query()
            ->where('tenant_id', $tenantId)
            ->where('papel', $this->papel())
            ->where('status', 'ativo')
            ->whereHas('user', fn ($q) => $this->aplicarFiltroTermos($q, $palavras))
            ->with('user')
            ->limit(20)
            ->get();

        return response()->json($vinculos->map(fn ($v) => $this->formatarParaFrontend($v)));
    }

    protected function editBase(Vinculo $vinculo): Response
    {
        $this->garantirPapel($vinculo);
        $vinculo->load('user');

        return Inertia::render($this->viewPrefix().'/editar', [
            $this->nomeEntidade() => $this->formatarParaFrontend($vinculo, completo: true),
        ]);
    }

    protected function updateBase(FormRequest $request, Vinculo $vinculo): RedirectResponse
    {
        $this->garantirPapel($vinculo);

        $vinculo->user->update($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', ucfirst($this->nomeEntidade()).' atualizado.');
    }

    protected function destroyBase(Vinculo $vinculo): RedirectResponse
    {
        $this->garantirPapel($vinculo);

        if ($mensagemErro = $this->validarPodeInativar($vinculo)) {
            return back()->withErrors([$this->nomeEntidade() => $mensagemErro]);
        }

        $vinculo->update(['status' => 'inativo']);

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', ucfirst($this->nomeEntidade()).' inativado.');
    }

    /**
     * @param  array<int, string>  $palavras
     */
    private function aplicarFiltroTermos(Builder $query, array $palavras): void
    {
        foreach ($palavras as $palavra) {
            $like = '%'.$palavra.'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('documento', 'like', $like);
            });
        }
    }

    /**
     * Cria User + Vinculo (papel específico do controller) atomicamente.
     * Email placeholder se ausente.
     *
     * @param  array<string, mixed>  $dados
     */
    private function criarPessoa(array $dados): Vinculo
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return DB::transaction(function () use ($dados, $tenantId) {
            $user = User::create([
                'name' => $dados['name'],
                'email' => $this->resolverEmail($dados['email'] ?? null),
                'telefone' => $dados['telefone'] ?? null,
                'tipo_pessoa' => $dados['tipo_pessoa'],
                'documento' => $dados['documento'] ?? null,
                'password' => bcrypt(Str::random(40)),
            ]);

            return Vinculo::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'papel' => $this->papel(),
                'status' => 'ativo',
            ]);
        });
    }

    private function resolverEmail(?string $email): string
    {
        if ($email !== null && $email !== '') {
            return $email;
        }

        do {
            $candidato = 'pessoa.'.Str::lower(Str::random(8)).'@'.User::EMAIL_PLACEHOLDER_DOMAIN;
        } while (User::where('email', $candidato)->exists());

        return $candidato;
    }

    private function garantirPapel(Vinculo $vinculo): void
    {
        abort_unless($vinculo->papel === $this->papel(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatarParaFrontend(Vinculo $vinculo, bool $completo = false): array
    {
        $user = $vinculo->user;
        $emailReal = ! $user->hasPlaceholderEmail() ? $user->email : null;

        $base = [
            'vinculo_id' => $vinculo->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $emailReal,
            'telefone' => $user->telefone,
            'tipo_pessoa' => $user->tipo_pessoa,
            'documento' => $user->documento,
            'status' => $vinculo->status,
        ];

        if ($completo) {
            $base['email_placeholder'] = $user->hasPlaceholderEmail();
        }

        return $base;
    }
}
