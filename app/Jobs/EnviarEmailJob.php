<?php

namespace App\Jobs;

use App\Mail\KimobeEmail;
use App\Models\EmailLog;
use App\Services\EmailBaseTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private int $emailLogId,
    ) {}

    public function handle(): void
    {
        $log = EmailLog::find($this->emailLogId);
        if (! $log) {
            return;
        }

        try {
            $template = $log->template;

            // Renderizar corpo com variáveis
            $variaveis = $log->variaveis_usadas ?? [];
            $corpoHtml = $template
                ? $template->renderCorpoHtml($variaveis)
                : ($variaveis['_corpo_html'] ?? '');

            // Wrapar com template base e inserir pixel
            $htmlCompleto = EmailBaseTemplate::render($corpoHtml);
            $htmlCompleto = $this->inserirPixel($htmlCompleto, $log->token_rastreamento);

            // Enviar
            Mail::to($log->destinatario_email, $log->destinatario_nome)
                ->send(new KimobeEmail($log->assunto, $htmlCompleto));

            $log->update([
                'status' => 'enviado',
                'enviado_em' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Falha ao enviar email [{$log->chave_template}] para {$log->destinatario_email}: {$e->getMessage()}");

            $log->update([
                'status' => 'falha',
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function inserirPixel(string $html, string $token): string
    {
        $pixelUrl = url("/email/pixel/{$token}");
        $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none" />';

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $pixel . '</body>', $html);
        }

        return $html . $pixel;
    }
}
