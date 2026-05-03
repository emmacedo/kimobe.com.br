<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContratoReajusteRequest;
use App\Models\Contrato;
use App\Services\ContratoReajusteService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ContratoReajusteController extends Controller
{
    public function __construct(protected ContratoReajusteService $service) {}

    public function store(StoreContratoReajusteRequest $request, Contrato $contrato): RedirectResponse
    {
        try {
            $this->service->aplicar($contrato, $request->validated());
        } catch (DomainException $e) {
            return back()->withErrors(['valor_novo' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('contratos.show', $contrato)
            ->with('success', 'Reajuste aplicado com sucesso.');
    }
}
