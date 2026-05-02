<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GerenciaPessoaVinculada;
use App\Http\Requests\StoreInquilinoRequest;
use App\Http\Requests\UpdateInquilinoRequest;
use App\Models\Contrato;
use App\Models\Scopes\TenantScope;
use App\Models\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class InquilinoController extends Controller
{
    use GerenciaPessoaVinculada;

    protected function papel(): string
    {
        return 'inquilino';
    }

    protected function viewPrefix(): string
    {
        return 'inquilinos';
    }

    protected function routePrefix(): string
    {
        return 'inquilinos';
    }

    protected function relacaoContagem(): string
    {
        return 'participacoesEmContratos';
    }

    /**
     * Mantém o nome semântico de domínio ('contratos_como_inquilino_count') no JSON
     * exposto ao frontend, mesmo após renomear a relação interna para participacoesEmContratos.
     */
    protected function aliasContagem(): string
    {
        return 'contratos_como_inquilino_count';
    }

    protected function nomeEntidade(): string
    {
        return 'inquilino';
    }

    /**
     * Inquilino não pode ser inativado se está em contrato com status='ativo'.
     */
    protected function validarPodeInativar(Vinculo $vinculo): ?string
    {
        $temContratoAtivo = Contrato::withoutGlobalScopes([TenantScope::class])
            ->where('status', 'ativo')
            ->whereHas('inquilinos', fn ($q) => $q->where('vinculo_id', $vinculo->id))
            ->exists();

        return $temContratoAtivo
            ? 'Não é possível inativar — este inquilino está em contrato(s) ativo(s). Encerre os contratos primeiro.'
            : null;
    }

    public function store(StoreInquilinoRequest $request): RedirectResponse
    {
        return $this->storeBase($request);
    }

    public function storeInline(StoreInquilinoRequest $request): JsonResponse
    {
        return $this->storeInlineBase($request);
    }

    public function edit(Vinculo $inquilino): Response
    {
        return $this->editBase($inquilino);
    }

    public function update(UpdateInquilinoRequest $request, Vinculo $inquilino): RedirectResponse
    {
        return $this->updateBase($request, $inquilino);
    }

    public function destroy(Vinculo $inquilino): RedirectResponse
    {
        return $this->destroyBase($inquilino);
    }
}
