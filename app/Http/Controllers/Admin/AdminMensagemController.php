<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MensagemContato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminMensagemController extends Controller
{
    public function index(): Response
    {
        $mensagens = MensagemContato::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('admin/mensagens/index', [
            'mensagens' => $mensagens,
            'nao_lidas' => MensagemContato::where('lida', false)->count(),
        ]);
    }

    public function show(MensagemContato $mensagem): JsonResponse
    {
        return response()->json($mensagem);
    }

    public function marcarLida(MensagemContato $mensagem): RedirectResponse
    {
        $mensagem->update(['lida' => true]);

        return redirect()->route('admin.mensagens.index')->with('success', 'Mensagem marcada como lida.');
    }

    public function marcarRespondida(MensagemContato $mensagem): RedirectResponse
    {
        $mensagem->update(['respondida' => true, 'respondida_em' => now()]);

        return redirect()->route('admin.mensagens.index')->with('success', 'Mensagem marcada como respondida.');
    }
}
