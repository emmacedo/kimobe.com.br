<?php

namespace App\Jobs;

use App\Models\Fatura;
use App\Services\EmailNotificationService;
use App\Services\NotificacaoAdminService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Atualiza cobranças pendentes vencidas para "atrasado" e envia notificações.
 */
class AtualizarCobrancasAtrasadas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $cobrancas = Fatura::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->where('data_vencimento', '<', now()->startOfDay())
            ->with(['contrato.inquilino.user', 'contrato.imovel'])
            ->get();

        $emailService = app(EmailNotificationService::class);
        $notifService = app(NotificacaoAdminService::class);

        foreach ($cobrancas as $cob) {
            $cob->update(['status' => 'atrasado']);

            $contrato = $cob->contrato;
            $email = $contrato?->getEmailInquilino();
            $diasAtraso = (int) Carbon::today()->diffInDays($cob->data_vencimento);

            // Notificar inquilino
            if ($email && ! $emailService->jaEnviou('admin.cobranca_atrasada', $email, $cob->referencia)) {
                try {
                    $emailService->enviar('admin.cobranca_atrasada', $email, $contrato->getNomeInquilino() ?? '', [
                        'nome' => $contrato->getNomeInquilino(),
                        'imovel_endereco' => $contrato->getEnderecoResumido(),
                        'referencia' => $cob->referencia,
                        'valor_total' => number_format($cob->valor_total, 2, ',', '.'),
                        'data_vencimento' => $cob->data_vencimento->format('d/m/Y'),
                        'dias_atraso' => $diasAtraso, 'valor_multa' => '—', 'valor_juros' => '—',
                    ], $cob->tenant_id);
                } catch (\Throwable $e) {
                    Log::warning("Falha notificação atraso inquilino: {$e->getMessage()}");
                }
            }

            // Notificar proprietários do imóvel
            if ($contrato?->imovel) {
                foreach ($contrato->imovel->getTitularesParaNotificacao() as $t) {
                    if ($emailService->jaEnviou('admin.cobranca_inquilino_atrasada_proprietario', $t['email'], $cob->referencia)) {
                        continue;
                    }
                    try {
                        $emailService->enviar('admin.cobranca_inquilino_atrasada_proprietario', $t['email'], $t['nome'], [
                            'nome' => $t['nome'],
                            'imovel_endereco' => $contrato->getEnderecoResumido(),
                            'inquilino_nome' => $contrato->getNomeInquilino(),
                            'referencia' => $cob->referencia,
                            'valor_total' => number_format($cob->valor_total, 2, ',', '.'),
                            'dias_atraso' => $diasAtraso,
                        ], $cob->tenant_id);
                    } catch (\Throwable $e) { /* silencioso */
                    }
                }
            }
        }

        Log::info("AtualizarCobrancasAtrasadas: {$cobrancas->count()} cobrança(s) marcada(s) como atrasada(s).");
    }
}
