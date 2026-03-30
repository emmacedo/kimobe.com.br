<?php

namespace App\Jobs;

use App\Models\ConfiguracaoCobrancaKimobe;
use App\Models\FaturaKimobe;
use App\Models\Tenant;
use App\Models\Vinculo;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarInadimplenciaKimobe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $resultado = [
        'avisos_vencimento' => 0, 'lembretes_dia' => 0,
        'faturas_atrasadas' => 0, 'avisos_bloqueio' => 0, 'tenants_bloqueados' => 0,
    ];

    public function handle(): void
    {
        $config = ConfiguracaoCobrancaKimobe::getConfig();
        $emailService = app(EmailNotificationService::class);

        $this->enviarAvisosVencimento($config, $emailService);
        $this->enviarLembretesDia($config, $emailService);
        $this->marcarAtrasadas($emailService);
        $this->enviarAvisosBloqueio($config, $emailService);
        $this->bloquearInadimplentes($config, $emailService);

        Log::info('Inadimplência processada: ' . json_encode($this->resultado));
    }

    private function enviarAvisosVencimento($config, EmailNotificationService $es): void
    {
        $faturas = FaturaKimobe::where('status', 'pendente')
            ->whereDate('data_vencimento', Carbon::today()->addDays($config->dias_aviso_antes_vencimento))
            ->with('tenant')->get();

        foreach ($faturas as $f) {
            $admin = $this->getAdmin($f->tenant);
            if (! $admin || $es->jaEnviou('kimobe.aviso_vencimento', $admin->email, $f->referencia)) continue;
            $es->enviar('kimobe.aviso_vencimento', $admin->email, $admin->name, [
                'nome' => $admin->name, 'nome_empresa' => $f->tenant->nome,
                'referencia' => $f->referencia, 'valor' => number_format($f->valor, 2, ',', '.'),
                'data_vencimento' => $f->data_vencimento->format('d/m/Y'), 'dias' => $config->dias_aviso_antes_vencimento,
            ], $f->tenant_id);
            $this->resultado['avisos_vencimento']++;
        }
    }

    private function enviarLembretesDia($config, EmailNotificationService $es): void
    {
        if (! $config->aviso_no_dia_vencimento) return;
        $faturas = FaturaKimobe::where('status', 'pendente')->whereDate('data_vencimento', Carbon::today())->with('tenant')->get();
        foreach ($faturas as $f) {
            $admin = $this->getAdmin($f->tenant);
            if (! $admin || $es->jaEnviou('kimobe.lembrete_vencimento', $admin->email, $f->referencia)) continue;
            $es->enviar('kimobe.lembrete_vencimento', $admin->email, $admin->name, [
                'nome' => $admin->name, 'nome_empresa' => $f->tenant->nome,
                'referencia' => $f->referencia, 'valor' => number_format($f->valor, 2, ',', '.'),
                'data_vencimento' => $f->data_vencimento->format('d/m/Y'),
            ], $f->tenant_id);
            $this->resultado['lembretes_dia']++;
        }
    }

    private function marcarAtrasadas(EmailNotificationService $es): void
    {
        $faturas = FaturaKimobe::where('status', 'pendente')->where('data_vencimento', '<', Carbon::today())->with('tenant')->get();
        foreach ($faturas as $f) {
            $f->update(['status' => 'atrasado']);
            $admin = $this->getAdmin($f->tenant);
            if ($admin && ! $es->jaEnviou('kimobe.cobranca_atrasada', $admin->email, $f->referencia)) {
                $es->enviar('kimobe.cobranca_atrasada', $admin->email, $admin->name, [
                    'nome' => $admin->name, 'nome_empresa' => $f->tenant->nome,
                    'referencia' => $f->referencia, 'valor' => number_format($f->valor, 2, ',', '.'),
                    'data_vencimento' => $f->data_vencimento->format('d/m/Y'),
                    'dias_atraso' => (int) Carbon::today()->diffInDays($f->data_vencimento),
                ], $f->tenant_id);
            }
            $this->resultado['faturas_atrasadas']++;
        }
    }

    private function enviarAvisosBloqueio($config, EmailNotificationService $es): void
    {
        $faturas = FaturaKimobe::where('status', 'atrasado')
            ->whereDate('data_vencimento', Carbon::today()->subDays($config->dias_aviso_bloqueio))
            ->with('tenant')->get();
        foreach ($faturas as $f) {
            $admin = $this->getAdmin($f->tenant);
            if (! $admin || $es->jaEnviou('kimobe.aviso_bloqueio', $admin->email, $f->referencia)) continue;
            $dias = (int) Carbon::today()->diffInDays($f->data_vencimento);
            $es->enviar('kimobe.aviso_bloqueio', $admin->email, $admin->name, [
                'nome' => $admin->name, 'nome_empresa' => $f->tenant->nome,
                'referencia' => $f->referencia, 'valor' => number_format($f->valor, 2, ',', '.'),
                'dias_atraso' => $dias, 'dias_para_bloqueio' => max(0, $config->dias_graca_apos_vencimento - $dias),
            ], $f->tenant_id);
            $this->resultado['avisos_bloqueio']++;
        }
    }

    private function bloquearInadimplentes($config, EmailNotificationService $es): void
    {
        $faturas = FaturaKimobe::where('status', 'atrasado')
            ->where('data_vencimento', '<=', Carbon::today()->subDays($config->dias_graca_apos_vencimento))
            ->with('tenant')->get();
        foreach ($faturas as $f) {
            if (! $f->tenant || $f->tenant->status !== 'ativo') continue;
            $dias = (int) Carbon::today()->diffInDays($f->data_vencimento);
            $motivo = "Fatura {$f->referencia} vencida há {$dias} dias";
            $f->tenant->update(['status' => 'bloqueado', 'bloqueado_em' => now(), 'motivo_bloqueio' => $motivo]);
            if ($config->aviso_ao_bloquear) {
                $admin = $this->getAdmin($f->tenant);
                if ($admin) $es->enviar('kimobe.bloqueio', $admin->email, $admin->name, [
                    'nome' => $admin->name, 'nome_empresa' => $f->tenant->nome,
                    'referencia' => $f->referencia, 'valor' => number_format($f->valor, 2, ',', '.'), 'motivo' => $motivo,
                ], $f->tenant->id);
            }
            $this->resultado['tenants_bloqueados']++;
            Log::info("Tenant {$f->tenant->nome} bloqueado: {$motivo}");
        }
    }

    private function getAdmin(?Tenant $t): ?\App\Models\User
    {
        return $t ? Vinculo::where('tenant_id', $t->id)->where('papel', 'admin')->where('status', 'ativo')->with('user')->first()?->user : null;
    }
}
