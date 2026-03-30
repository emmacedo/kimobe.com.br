<?php

namespace App\Services;

use App\Jobs\EnviarEmailJob;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Envia um email usando um template. Cria o log e despacha o job.
     */
    public function enviar(
        string $chaveTemplate,
        string $email,
        string $nome,
        array $variaveis,
        ?int $tenantId = null,
    ): EmailLog {
        // Buscar template ativo
        $template = EmailTemplate::where('chave', $chaveTemplate)->ativo()->first();

        if (! $template) {
            Log::warning("Template de email não encontrado ou inativo: {$chaveTemplate}");

            // Criar log de falha para auditoria
            return EmailLog::create([
                'template_id' => null,
                'tenant_id' => $tenantId,
                'destinatario_email' => $email,
                'destinatario_nome' => $nome,
                'assunto' => "Template não encontrado: {$chaveTemplate}",
                'chave_template' => $chaveTemplate,
                'variaveis_usadas' => $variaveis,
                'status' => 'falha',
                'erro' => "Template '{$chaveTemplate}' não encontrado ou está inativo.",
                'token_rastreamento' => EmailLog::gerarToken(),
            ]);
        }

        // Renderizar assunto com variáveis
        $assuntoRenderizado = $template->renderAssunto($variaveis);

        // Criar log com status pendente
        $log = EmailLog::create([
            'template_id' => $template->id,
            'tenant_id' => $tenantId,
            'destinatario_email' => $email,
            'destinatario_nome' => $nome,
            'assunto' => $assuntoRenderizado,
            'chave_template' => $chaveTemplate,
            'variaveis_usadas' => $variaveis,
            'status' => 'pendente',
            'token_rastreamento' => EmailLog::gerarToken(),
        ]);

        // Despachar job para envio assíncrono
        dispatch(new EnviarEmailJob($log->id));

        return $log;
    }

    /**
     * Verifica se um email com a mesma chave/destinatário/referência já foi enviado recentemente.
     * Usado para controle de duplicidade em envios automáticos (jobs).
     */
    public function jaEnviou(string $chaveTemplate, string $email, string $referencia, int $diasRecentes = 30): bool
    {
        return EmailLog::where('chave_template', $chaveTemplate)
            ->where('destinatario_email', $email)
            ->whereIn('status', ['enviado', 'pendente'])
            ->where('created_at', '>=', now()->subDays($diasRecentes))
            ->whereRaw("JSON_EXTRACT(variaveis_usadas, '$.referencia') = ?", [json_encode($referencia)])
            ->exists();
    }
}
