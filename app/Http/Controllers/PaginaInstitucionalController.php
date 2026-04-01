<?php

namespace App\Http\Controllers;

use App\Models\PaginaInstitucional;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exibe páginas institucionais públicas (termos de uso, privacidade, etc.).
 */
class PaginaInstitucionalController extends Controller
{
    public function show(string $slug): Response
    {
        $pagina = PaginaInstitucional::getBySlug($slug);

        if (! $pagina) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('public/pagina-institucional', [
            'titulo' => $pagina->titulo,
            'conteudo' => $pagina->conteudo,
            'meta_description' => $pagina->meta_description,
            'updated_at' => $pagina->updated_at->format('d/m/Y'),
        ]);
    }
}
