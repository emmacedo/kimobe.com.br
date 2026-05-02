<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GerenciaPessoaVinculada;
use App\Http\Requests\StoreProprietarioRequest;
use App\Http\Requests\UpdateProprietarioRequest;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Models\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProprietarioController extends Controller
{
    use GerenciaPessoaVinculada;

    protected function papel(): string
    {
        return 'proprietario';
    }

    protected function viewPrefix(): string
    {
        return 'proprietarios';
    }

    protected function routePrefix(): string
    {
        return 'proprietarios';
    }

    protected function relacaoContagem(): string
    {
        return 'titularidades';
    }

    protected function nomeEntidade(): string
    {
        return 'proprietario';
    }

    /**
     * Proprietário não pode ser inativado se tem titularidades ativas (em qualquer status).
     */
    protected function validarPodeInativar(Vinculo $vinculo): ?string
    {
        $temTitularidades = Titularidade::withoutGlobalScopes([TenantScope::class])
            ->where('vinculo_id', $vinculo->id)
            ->exists();

        return $temTitularidades
            ? 'Não é possível inativar — este proprietário tem imóveis vinculados. Remova as titularidades primeiro.'
            : null;
    }

    // Wrappers de tipo: o trait usa FormRequest base, os controllers especializam
    // o Request injetado para Laravel resolver as validações específicas.
    public function store(StoreProprietarioRequest $request): RedirectResponse
    {
        return $this->storeBase($request);
    }

    public function storeInline(StoreProprietarioRequest $request): JsonResponse
    {
        return $this->storeInlineBase($request);
    }

    public function update(UpdateProprietarioRequest $request, Vinculo $proprietario): RedirectResponse
    {
        return $this->updateBase($request, $proprietario);
    }

    // Aliases para clareza nas rotas (Laravel resolve route binding como $vinculo via trait,
    // mas mantemos {proprietario} no path para semântica)
    public function edit(Vinculo $proprietario): Response
    {
        return $this->editBase($proprietario);
    }

    public function destroy(Vinculo $proprietario): RedirectResponse
    {
        return $this->destroyBase($proprietario);
    }

    // O index/create/buscar não precisam de wrapper — o trait expõe direto.
}
