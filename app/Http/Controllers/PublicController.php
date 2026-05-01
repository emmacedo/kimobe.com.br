<?php

namespace App\Http\Controllers;

use App\Models\MensagemContato;
use App\Models\Plano;
use App\Support\Sanitize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    /**
     * TTL do cache de planos públicos (10 minutos). Páginas anônimas servem
     * a mesma listagem para todos os visitantes; cache reduz pressão no DB.
     * Invalidar via PlanoObserver quando admin altera/cria/remove plano.
     */
    private const PLANOS_CACHE_KEY = 'public.planos.ativos_ordenados';

    private const PLANOS_CACHE_TTL = 600;

    public function home(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('public/home', ['planos' => $this->planosCacheados()]);
    }

    public function planos(): Response
    {
        return Inertia::render('public/planos', ['planos' => $this->planosCacheados()]);
    }

    private function planosCacheados()
    {
        return Cache::remember(
            self::PLANOS_CACHE_KEY,
            self::PLANOS_CACHE_TTL,
            fn () => Plano::ativo()->ordenado()->get(),
        );
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
        // Sanitiza telefone com máscara antes de validar
        if ($request->telefone) {
            $request->merge(['telefone' => Sanitize::telefone($request->telefone)]);
        }

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
