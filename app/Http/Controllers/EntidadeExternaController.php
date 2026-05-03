<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntidadeExternaRequest;
use App\Http\Requests\UpdateEntidadeExternaRequest;
use App\Models\EntidadeExterna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntidadeExternaController extends Controller
{
    /**
     * Listagem de entidades externas do tenant com filtro de busca.
     */
    public function index(Request $request): Response
    {
        $query = EntidadeExterna::query()
            ->withCount('condominios')
            ->orderBy('nome');

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cpf_cnpj', 'like', '%'.preg_replace('/\D/', '', $busca).'%')
                    ->orWhere('cidade', 'like', "%{$busca}%");
            });
        }

        if ($tipo = $request->input('tipo')) {
            $query->where('tipo', $tipo);
        }

        $entidades = $query->paginate(20)->withQueryString();

        return Inertia::render('entidades-externas/index', [
            'entidades' => $entidades,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'tipo' => $request->input('tipo', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('entidades-externas/criar');
    }

    public function store(StoreEntidadeExternaRequest $request): RedirectResponse
    {
        EntidadeExterna::create($request->validated());

        return redirect()->route('entidades-externas.index')
            ->with('success', 'Entidade externa cadastrada com sucesso.');
    }

    /**
     * Cria uma entidade externa a partir do dialog inline (formulário do imóvel).
     * Retorna JSON com a entidade criada para o frontend popular o select.
     */
    public function storeInline(StoreEntidadeExternaRequest $request): JsonResponse
    {
        $entidade = EntidadeExterna::create($request->validated());

        return response()->json($entidade);
    }

    public function edit(EntidadeExterna $entidadeExterna): Response
    {
        return Inertia::render('entidades-externas/editar', [
            'entidade' => $entidadeExterna,
        ]);
    }

    public function update(UpdateEntidadeExternaRequest $request, EntidadeExterna $entidadeExterna): RedirectResponse
    {
        $entidadeExterna->update($request->validated());

        return redirect()->route('entidades-externas.index')
            ->with('success', 'Entidade externa atualizada com sucesso.');
    }

    public function destroy(EntidadeExterna $entidadeExterna): RedirectResponse
    {
        $entidadeExterna->delete();

        return redirect()->route('entidades-externas.index')
            ->with('success', 'Entidade externa excluída com sucesso.');
    }
}
