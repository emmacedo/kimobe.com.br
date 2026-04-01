<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaginaInstitucional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gerenciamento de páginas institucionais pelo super admin.
 */
class AdminPaginaController extends Controller
{
    /**
     * Lista todas as páginas institucionais.
     */
    public function index(): Response
    {
        $paginas = PaginaInstitucional::with('atualizadoPor')
            ->orderBy('titulo')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'titulo' => $p->titulo,
                'publicado' => $p->publicado,
                'updated_at' => $p->updated_at->format('d/m/Y H:i'),
                'atualizado_por_nome' => $p->atualizadoPor?->nome,
            ]);

        return Inertia::render('admin/paginas/index', [
            'paginas' => $paginas,
        ]);
    }

    /**
     * Editor de uma página institucional.
     */
    public function edit(PaginaInstitucional $pagina): Response
    {
        return Inertia::render('admin/paginas/editar', [
            'pagina' => [
                'id' => $pagina->id,
                'slug' => $pagina->slug,
                'titulo' => $pagina->titulo,
                'conteudo' => $pagina->conteudo,
                'meta_description' => $pagina->meta_description,
                'publicado' => $pagina->publicado,
                'updated_at' => $pagina->updated_at->format('d/m/Y H:i'),
                'atualizado_por_nome' => $pagina->atualizadoPor?->nome,
            ],
        ]);
    }

    /**
     * Salva alterações na página institucional.
     */
    public function update(Request $request, PaginaInstitucional $pagina): RedirectResponse
    {
        $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'publicado' => ['required', 'boolean'],
        ]);

        $pagina->update([
            ...$request->only(['titulo', 'conteudo', 'meta_description', 'publicado']),
            'atualizado_por' => Auth::guard('admin')->id(),
        ]);

        return back()->with('success', 'Página atualizada com sucesso.');
    }
}
