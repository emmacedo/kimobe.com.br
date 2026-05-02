<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInquilinoRequest;
use App\Http\Requests\UpdateInquilinoRequest;
use App\Models\Contrato;
use App\Models\Scopes\TenantScope;
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

class InquilinoController extends Controller
{
    /**
     * Listagem de inquilinos do tenant. Mostra apenas vínculos ativos por padrão.
     */
    public function index(Request $request): Response
    {
        $tenantId = app(TenantService::class)->getTenantId();
        $busca = trim((string) $request->input('busca', ''));
        $tipoPessoa = $request->input('tipo_pessoa');
        $incluirInativos = $request->boolean('incluir_inativos');

        $query = Vinculo::query()
            ->where('tenant_id', $tenantId)
            ->where('papel', 'inquilino')
            ->with('user')
            // Conta participações via pivot — inclui contratos onde é principal E onde é co-inquilino.
            ->withCount(['participacoesEmContratos as contratos_como_inquilino_count' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])]);

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

        $inquilinos = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        // Mascara emails placeholder antes de enviar à UI.
        $inquilinos->getCollection()->transform(function ($vinculo) {
            if ($vinculo->user && $vinculo->user->hasPlaceholderEmail()) {
                $vinculo->user->email = null;
                $vinculo->user->setAttribute('email_placeholder', true);
            }

            return $vinculo;
        });

        return Inertia::render('inquilinos/index', [
            'inquilinos' => $inquilinos,
            'filtros' => [
                'busca' => $busca,
                'tipo_pessoa' => $tipoPessoa ?? '',
                'incluir_inativos' => $incluirInativos,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inquilinos/criar');
    }

    public function store(StoreInquilinoRequest $request): RedirectResponse
    {
        $this->criarInquilino($request->validated());

        return redirect()->route('inquilinos.index')
            ->with('success', 'Inquilino cadastrado com sucesso.');
    }

    /**
     * Cria inquilino a partir do dialog inline (formulário do contrato).
     * Retorna JSON com {vinculo_id, name, email, documento, tipo_pessoa}.
     */
    public function storeInline(StoreInquilinoRequest $request): JsonResponse
    {
        $vinculo = $this->criarInquilino($request->validated());
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
            ->where('papel', 'inquilino')
            ->where('status', 'ativo')
            ->whereHas('user', fn ($q) => $this->aplicarFiltroTermos($q, $palavras))
            ->with('user')
            ->limit(20)
            ->get();

        return response()->json($vinculos->map(fn ($v) => $this->formatarParaFrontend($v)));
    }

    public function edit(Vinculo $inquilino): Response
    {
        $this->garantirPapelInquilino($inquilino);
        $inquilino->load('user');

        return Inertia::render('inquilinos/editar', [
            'inquilino' => $this->formatarParaFrontend($inquilino, completo: true),
        ]);
    }

    public function update(UpdateInquilinoRequest $request, Vinculo $inquilino): RedirectResponse
    {
        $this->garantirPapelInquilino($inquilino);

        $inquilino->user->update($request->validated());

        return redirect()->route('inquilinos.index')
            ->with('success', 'Inquilino atualizado.');
    }

    /**
     * Inativação lógica. Bloqueia se há contrato ativo (como principal ou co-inquilino).
     */
    public function destroy(Vinculo $inquilino): RedirectResponse
    {
        $this->garantirPapelInquilino($inquilino);

        $temContratoAtivo = Contrato::withoutGlobalScopes([TenantScope::class])
            ->where('status', 'ativo')
            ->whereHas('inquilinos', fn ($q) => $q->where('vinculo_id', $inquilino->id))
            ->exists();

        if ($temContratoAtivo) {
            return back()->withErrors([
                'inquilino' => 'Não é possível inativar — este inquilino está em contrato(s) ativo(s). Encerre os contratos primeiro.',
            ]);
        }

        $inquilino->update(['status' => 'inativo']);

        return redirect()->route('inquilinos.index')
            ->with('success', 'Inquilino inativado.');
    }

    /**
     * Aplica busca AND por palavra: cada palavra precisa aparecer em pelo menos um
     * dos campos name/email/documento.
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
     * Cria User + Vinculo (papel=inquilino) atomicamente. Email placeholder se ausente.
     *
     * @param  array<string, mixed>  $dados
     */
    private function criarInquilino(array $dados): Vinculo
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
                'papel' => 'inquilino',
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

    private function garantirPapelInquilino(Vinculo $vinculo): void
    {
        abort_unless($vinculo->papel === 'inquilino', 404);
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
