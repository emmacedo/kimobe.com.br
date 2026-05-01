<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProprietarioRequest;
use App\Http\Requests\UpdateProprietarioRequest;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProprietarioController extends Controller
{
    /**
     * Listagem de proprietários do tenant. Mostra apenas vínculos ativos por padrão.
     */
    public function index(Request $request): Response
    {
        $tenantId = app(TenantService::class)->getTenantId();
        $busca = trim((string) $request->input('busca', ''));
        $tipoPessoa = $request->input('tipo_pessoa');
        $incluirInativos = $request->boolean('incluir_inativos');

        $query = Vinculo::query()
            ->where('tenant_id', $tenantId)
            ->where('papel', 'proprietario')
            ->with('user')
            ->withCount(['titularidades' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])]);

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

        $proprietarios = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        // Mascara emails placeholder antes de enviar à UI (o User não os exibe como reais).
        $proprietarios->getCollection()->transform(function ($vinculo) {
            if ($vinculo->user && $vinculo->user->hasPlaceholderEmail()) {
                $vinculo->user->email = null;
                $vinculo->user->setAttribute('email_placeholder', true);
            }

            return $vinculo;
        });

        return Inertia::render('proprietarios/index', [
            'proprietarios' => $proprietarios,
            'filtros' => [
                'busca' => $busca,
                'tipo_pessoa' => $tipoPessoa ?? '',
                'incluir_inativos' => $incluirInativos,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('proprietarios/criar');
    }

    public function store(StoreProprietarioRequest $request): RedirectResponse
    {
        $this->criarProprietario($request->validated());

        return redirect()->route('proprietarios.index')
            ->with('success', 'Proprietário cadastrado com sucesso.');
    }

    /**
     * Cria proprietário a partir do dialog inline (formulário do imóvel).
     * Retorna JSON com {vinculo_id, name, email, documento, tipo_pessoa}.
     */
    public function storeInline(StoreProprietarioRequest $request): JsonResponse
    {
        $vinculo = $this->criarProprietario($request->validated());
        $vinculo->load('user');

        return response()->json($this->formatarParaFrontend($vinculo), 201);
    }

    /**
     * Endpoint de busca para autocomplete: aplica WHERE LIKE %palavra% AND para cada palavra.
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
            ->where('papel', 'proprietario')
            ->where('status', 'ativo')
            ->whereHas('user', fn ($q) => $this->aplicarFiltroTermos($q, $palavras))
            ->with('user')
            ->limit(20)
            ->get();

        return response()->json($vinculos->map(fn ($v) => $this->formatarParaFrontend($v)));
    }

    public function edit(Vinculo $proprietario): Response
    {
        $this->garantirPapelProprietario($proprietario);
        $proprietario->load('user');

        return Inertia::render('proprietarios/editar', [
            'proprietario' => $this->formatarParaFrontend($proprietario, completo: true),
        ]);
    }

    public function update(UpdateProprietarioRequest $request, Vinculo $proprietario): RedirectResponse
    {
        $this->garantirPapelProprietario($proprietario);

        $proprietario->user->update($request->validated());

        return redirect()->route('proprietarios.index')
            ->with('success', 'Proprietário atualizado.');
    }

    /**
     * Inativação lógica: marca o vínculo como inativo. Bloqueia se há titularidades ativas.
     */
    public function destroy(Vinculo $proprietario): RedirectResponse
    {
        $this->garantirPapelProprietario($proprietario);

        $temTitularidades = Titularidade::withoutGlobalScopes([TenantScope::class])
            ->where('vinculo_id', $proprietario->id)
            ->exists();

        if ($temTitularidades) {
            return back()->withErrors([
                'proprietario' => 'Não é possível inativar — este proprietário tem imóveis vinculados. Remova as titularidades primeiro.',
            ]);
        }

        $proprietario->update(['status' => 'inativo']);

        return redirect()->route('proprietarios.index')
            ->with('success', 'Proprietário inativado.');
    }

    /**
     * Aplica busca AND por palavra na query do User: cada palavra precisa aparecer
     * em pelo menos um dos campos name/email/documento. Compartilhado entre index e buscar.
     *
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
     * Cria User + Vinculo (papel=proprietario) atomicamente. Gera email placeholder
     * se ausente para preservar a unique constraint.
     *
     * @param  array<string, mixed>  $dados
     */
    private function criarProprietario(array $dados): Vinculo
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return DB::transaction(function () use ($dados, $tenantId) {
            $user = User::create([
                'name' => $dados['name'],
                'email' => $this->resolverEmail($dados['email'] ?? null),
                'telefone' => $dados['telefone'] ?? null,
                'tipo_pessoa' => $dados['tipo_pessoa'],
                'documento' => $dados['documento'] ?? null,
                // Senha aleatória — proprietário pode redefinir depois via "esqueci senha".
                'password' => bcrypt(Str::random(40)),
            ]);

            return Vinculo::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'papel' => 'proprietario',
                'status' => 'ativo',
            ]);
        });
    }

    /**
     * Gera email placeholder único quando o admin cadastra alguém sem email.
     */
    private function resolverEmail(?string $email): string
    {
        if ($email !== null && $email !== '') {
            return $email;
        }

        // Loop curtíssimo na prática — Str::random(8) tem colisão extremamente improvável.
        do {
            $candidato = 'pessoa.'.Str::lower(Str::random(8)).'@'.User::EMAIL_PLACEHOLDER_DOMAIN;
        } while (User::where('email', $candidato)->exists());

        return $candidato;
    }

    /**
     * Confirma que o vínculo é de proprietário (route binding já filtra tenant).
     */
    private function garantirPapelProprietario(Vinculo $vinculo): void
    {
        abort_unless($vinculo->papel === 'proprietario', 404);
    }

    /**
     * Formata para o frontend o vínculo + user, com campos relevantes.
     *
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
