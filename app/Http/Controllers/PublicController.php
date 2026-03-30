<?php

namespace App\Http\Controllers;

use App\Models\MensagemContato;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $planos = Plano::ativo()->ordenado()->get();

        return Inertia::render('public/home', ['planos' => $planos]);
    }

    public function planos(): Response
    {
        return Inertia::render('public/planos', ['planos' => Plano::ativo()->ordenado()->get()]);
    }

    public function faq(): Response
    {
        return Inertia::render('public/faq');
    }

    public function contato(): Response
    {
        return Inertia::render('public/contato');
    }

    public function enviarContato(Request $request): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'assunto' => ['required', 'string', 'max:100'],
            'mensagem' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'nome.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu email.',
            'assunto.required' => 'Selecione o assunto.',
            'mensagem.required' => 'Escreva sua mensagem.',
            'mensagem.min' => 'A mensagem deve ter pelo menos 10 caracteres.',
        ]);

        MensagemContato::create([
            ...$request->only(['nome', 'email', 'telefone', 'assunto', 'mensagem']),
            'ip' => $request->ip(),
        ]);

        // TODO: enviar email de notificação para a equipe Kimobe

        return redirect()->route('contato')
            ->with('success', 'Mensagem enviada com sucesso! Retornaremos em até 24 horas.');
    }
}
