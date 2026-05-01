<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdministradoraRequest;
use App\Http\Requests\UpdateAdministradoraRequest;
use App\Models\Administradora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministradoraController extends Controller
{
    /**
     * Listagem de administradoras do tenant com filtro de busca.
     */
    public function index(Request $request): Response
    {
        $query = Administradora::query()
            ->withCount('condominios')
            ->orderBy('nome');

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cpf_cnpj', 'like', '%'.preg_replace('/\D/', '', $busca).'%')
                    ->orWhere('cidade', 'like', "%{$busca}%");
            });
        }

        $administradoras = $query->paginate(20)->withQueryString();

        return Inertia::render('administradoras/index', [
            'administradoras' => $administradoras,
            'filtros' => [
                'busca' => $request->input('busca', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administradoras/criar');
    }

    public function store(StoreAdministradoraRequest $request): RedirectResponse
    {
        Administradora::create($request->validated());

        return redirect()->route('administradoras.index')
            ->with('success', 'Administradora cadastrada com sucesso.');
    }

    /**
     * Cria uma administradora a partir do dialog inline (formulário do imóvel).
     * Retorna JSON com a entidade criada para o frontend popular o select.
     */
    public function storeInline(StoreAdministradoraRequest $request): JsonResponse
    {
        $administradora = Administradora::create($request->validated());

        return response()->json($administradora);
    }

    public function edit(Administradora $administradora): Response
    {
        return Inertia::render('administradoras/editar', [
            'administradora' => $administradora,
        ]);
    }

    public function update(UpdateAdministradoraRequest $request, Administradora $administradora): RedirectResponse
    {
        $administradora->update($request->validated());

        return redirect()->route('administradoras.index')
            ->with('success', 'Administradora atualizada com sucesso.');
    }

    public function destroy(Administradora $administradora): RedirectResponse
    {
        $administradora->delete();

        return redirect()->route('administradoras.index')
            ->with('success', 'Administradora excluída com sucesso.');
    }
}
