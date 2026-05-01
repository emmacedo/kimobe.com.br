<?php

namespace App\Http\Controllers;

use App\Models\DadosBancarios;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Models\Vinculo;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DadosBancariosController extends Controller
{
    use ScopesPorPapel;

    /**
     * Listagem de dados bancários.
     * Admin: todos do tenant. Proprietário: apenas os seus.
     */
    public function index(): Response
    {
        $query = DadosBancarios::query()->with('vinculo.user');

        if (! $this->isAdmin()) {
            $vinculoIds = Vinculo::where('user_id', auth()->id())
                ->where('tenant_id', app(TenantService::class)->getTenantId())
                ->pluck('id');
            $query->whereIn('vinculo_id', $vinculoIds);
        }

        $contas = $query->orderBy('apelido')->get()->map(function ($conta) {
            // Contar imóveis ATIVOS que usam esta conta para repasse (ignora titularidades soft-deletadas).
            $imoveisCount = Titularidade::withoutGlobalScopes([TenantScope::class])
                ->where('dados_bancarios_id', $conta->id)
                ->distinct('imovel_id')
                ->count('imovel_id');

            $conta->imoveis_count = $imoveisCount;

            return $conta;
        });

        return Inertia::render('dados-bancarios/index', [
            'contas' => $contas,
        ]);
    }

    /**
     * Lista as contas bancárias de um vínculo específico (JSON, alimenta autocomplete
     * do gerenciador de titulares no formulário de imóvel).
     */
    public function byVinculo(Vinculo $vinculo): JsonResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();

        // O resolveRouteBinding do Vinculo já filtra por tenant, mas reforço a checagem.
        abort_unless($vinculo->tenant_id === $tenantId, 404);

        // Proprietário só pode listar contas dos seus próprios vínculos.
        if (! $this->isAdmin() && $vinculo->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json($vinculo->dadosBancarios()->orderBy('apelido')->get());
    }

    /**
     * Cria um cadastro bancário para um vínculo.
     * Aceita JSON (API) ou Inertia redirect.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $tenantId = app(TenantService::class)->getTenantId();

        $request->validate([
            'vinculo_id' => ['required', 'integer'],
            'apelido' => ['required', 'string', 'max:100'],
            'banco_codigo' => ['required', 'string', 'max:10'],
            'banco_nome' => ['required', 'string', 'max:100'],
            'agencia' => ['required', 'string', 'max:20'],
            'conta' => ['required', 'string', 'max:20'],
            'tipo_conta' => ['required', 'in:corrente,poupanca'],
            'pix_tipo' => ['nullable', 'in:cpf,cnpj,email,telefone,aleatoria'],
            'pix_chave' => ['nullable', 'string', 'max:255'],
        ]);

        // Verificar que o vínculo pertence ao tenant
        $vinculo = Vinculo::where('id', $request->vinculo_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Se proprietário: verificar que o vínculo é dele
        if (! $this->isAdmin() && $vinculo->user_id !== auth()->id()) {
            abort(403);
        }

        $dados = DadosBancarios::create([
            'tenant_id' => $tenantId,
            'vinculo_id' => $vinculo->id,
            'apelido' => $request->apelido,
            'banco_codigo' => $request->banco_codigo,
            'banco_nome' => $request->banco_nome,
            'agencia' => $request->agencia,
            'conta' => $request->conta,
            'tipo_conta' => $request->tipo_conta,
            'pix_tipo' => $request->pix_tipo,
            'pix_chave' => $request->pix_chave,
        ]);

        // Se é chamada API (JSON), retornar JSON
        if ($request->expectsJson()) {
            return response()->json($dados, 201);
        }

        return redirect()->route('dados-bancarios.index')
            ->with('success', 'Conta bancária cadastrada com sucesso.');
    }

    /**
     * Atualiza dados bancários.
     */
    public function update(Request $request, DadosBancarios $dadosBancarios): RedirectResponse
    {
        // Se proprietário: verificar que a conta pertence ao user
        if (! $this->isAdmin()) {
            $vinculo = $dadosBancarios->vinculo;
            abort_unless($vinculo && $vinculo->user_id === auth()->id(), 403);
        }

        $request->validate([
            'apelido' => ['required', 'string', 'max:100'],
            'banco_codigo' => ['required', 'string', 'max:10'],
            'banco_nome' => ['required', 'string', 'max:100'],
            'agencia' => ['required', 'string', 'max:20'],
            'conta' => ['required', 'string', 'max:20'],
            'tipo_conta' => ['required', 'in:corrente,poupanca'],
            'pix_tipo' => ['nullable', 'in:cpf,cnpj,email,telefone,aleatoria'],
            'pix_chave' => ['nullable', 'string', 'max:255'],
        ]);

        $dadosBancarios->update($request->only([
            'apelido', 'banco_codigo', 'banco_nome', 'agencia', 'conta',
            'tipo_conta', 'pix_tipo', 'pix_chave',
        ]));

        return redirect()->route('dados-bancarios.index')
            ->with('success', 'Conta bancária atualizada.');
    }

    /**
     * Remove dados bancários.
     * Set null nas titularidades vinculadas.
     */
    public function destroy(DadosBancarios $dadosBancarios): RedirectResponse
    {
        // Se proprietário: verificar que a conta pertence ao user
        if (! $this->isAdmin()) {
            $vinculo = $dadosBancarios->vinculo;
            abort_unless($vinculo && $vinculo->user_id === auth()->id(), 403);
        }

        // Desvincular das titularidades — incluindo soft-deletadas, para evitar FK órfã caso restauradas.
        Titularidade::withoutGlobalScopes()
            ->where('dados_bancarios_id', $dadosBancarios->id)
            ->update(['dados_bancarios_id' => null]);

        $dadosBancarios->delete();

        return redirect()->route('dados-bancarios.index')
            ->with('success', 'Conta bancária removida.');
    }
}
