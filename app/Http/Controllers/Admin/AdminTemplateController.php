<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\EmailBaseTemplate;
use App\Services\EmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = EmailTemplate::orderBy('modulo')->orderBy('id')->get();

        // Adicionar contagem de envios a cada template
        $templates->each(function ($t) {
            $t->envios_count = EmailLog::where('chave_template', $t->chave)->count();
        });

        return Inertia::render('admin/templates/index', ['templates' => $templates]);
    }

    public function edit(EmailTemplate $template): Response
    {
        $template->envios_count = EmailLog::where('chave_template', $template->chave)->count();

        return Inertia::render('admin/templates/editar', ['template' => $template]);
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'assunto' => ['required', 'string', 'max:255'],
            'corpo_html' => ['required', 'string'],
            'corpo_texto' => ['nullable', 'string'],
            'ativo' => ['required', 'boolean'],
        ]);

        $template->update($request->only(['nome', 'assunto', 'corpo_html', 'corpo_texto', 'ativo']));

        return redirect()->route('admin.templates.edit', $template)->with('success', 'Template atualizado.');
    }

    public function toggleStatus(EmailTemplate $template): RedirectResponse
    {
        $template->update(['ativo' => ! $template->ativo]);

        return redirect()->route('admin.templates.index')
            ->with('success', $template->ativo ? 'Template ativado.' : 'Template desativado.');
    }

    public function enviarTeste(EmailTemplate $template): JsonResponse
    {
        $admin = Auth::guard('admin')->user();

        // Variáveis de exemplo
        $varExemplo = [];
        foreach ($template->variaveis_disponiveis as $v) {
            $varExemplo[$v['var']] = match ($v['var']) {
                'nome' => $admin->nome,
                'email' => $admin->email,
                'nome_empresa' => 'Empresa Teste',
                'plano_nome' => 'Profissional',
                'referencia' => '04/2026',
                'valor', 'valor_total', 'valor_pago', 'valor_liquido', 'valor_aluguel' => '2.400,00',
                'data_vencimento', 'data_pagamento', 'data_prevista', 'data_realizada', 'data_inicio', 'data_fim', 'data_encerramento', 'data_envio' => '15/04/2026',
                'dias', 'dias_atraso', 'dias_para_bloqueio' => '5',
                'imovel_endereco' => 'Rua das Flores, 123 — Apt 302',
                'inquilino_nome' => 'João da Silva',
                'banco_nome' => 'Itaú', 'conta' => '12345-6',
                'metodo_pagamento' => 'PIX',
                'motivo' => 'Fatura 03/2026 vencida há 10 dias',
                'documento' => '12.345.678/0001-90', 'tipo_tenant' => 'Imobiliária',
                'link_sistema' => url('/dashboard'),
                'valor_multa' => '68,60', 'valor_juros' => '22,86',
                default => "({$v['var']})",
            };
        }

        app(EmailNotificationService::class)->enviar(
            $template->chave, $admin->email, $admin->nome, $varExemplo
        );

        return response()->json(['message' => "Email de teste enviado para {$admin->email}."]);
    }

    public function preview(Request $request, EmailTemplate $template): JsonResponse
    {
        $varExemplo = [];
        foreach ($template->variaveis_disponiveis as $v) {
            $varExemplo[$v['var']] = $request->input("variaveis.{$v['var']}", "({$v['var']})");
        }

        $corpoHtml = $request->input('corpo_html', $template->corpo_html);
        // Substituir variáveis manualmente
        foreach ($varExemplo as $k => $v) {
            $corpoHtml = str_replace("{{{$k}}}", $v, $corpoHtml);
        }

        $htmlCompleto = EmailBaseTemplate::render($corpoHtml);

        return response()->json(['html' => $htmlCompleto]);
    }
}
