<?php

namespace App\Jobs;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Services\EmailNotificationService;
use App\Services\TenantService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processa notificações de cobrança e contrato para todos os tenants ativos.
 */
class ProcessarNotificacoesCobrancas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $resultado = ['lembretes' => 0, 'contratos_inquilino' => 0, 'contratos_admin' => 0];

    public function handle(): void
    {
        $tenantService = app(TenantService::class);
        $emailService = app(EmailNotificationService::class);

        $tenants = Tenant::withoutGlobalScopes()
            ->whereIn('status', ['ativo'])
            ->get();

        foreach ($tenants as $tenant) {
            $tenantService->setTenant($tenant);

            $this->enviarLembretesCobranca($tenant, $emailService);
            $this->enviarAvisosContratoInquilino($tenant, $emailService);
            $this->enviarAvisosContratoAdmin($tenant, $emailService);
        }

        $tenantService->clearTenant();
        Log::info('NotificacoesCobrancas: '.json_encode($this->resultado));
    }

    private function enviarLembretesCobranca(Tenant $tenant, EmailNotificationService $es): void
    {
        $diasAviso = 3; // dias antes do vencimento

        $cobrancas = Fatura::where('status', 'pendente')
            ->whereDate('data_vencimento', Carbon::today()->addDays($diasAviso))
            ->with(['contrato.inquilino.user', 'contrato.imovel'])
            ->get();

        foreach ($cobrancas as $cob) {
            $email = $cob->contrato->getEmailInquilino();
            if (! $email || $es->jaEnviou('admin.lembrete_vencimento_cobranca', $email, $cob->referencia)) {
                continue;
            }

            $es->enviar('admin.lembrete_vencimento_cobranca', $email, $cob->contrato->getNomeInquilino() ?? '', [
                'nome' => $cob->contrato->getNomeInquilino(),
                'imovel_endereco' => $cob->contrato->getEnderecoResumido(),
                'referencia' => $cob->referencia,
                'valor_total' => number_format($cob->valor_total, 2, ',', '.'),
                'data_vencimento' => $cob->data_vencimento->format('d/m/Y'),
                'dias' => $diasAviso,
            ], $tenant->id);

            $this->resultado['lembretes']++;
        }
    }

    private function enviarAvisosContratoInquilino(Tenant $tenant, EmailNotificationService $es): void
    {
        $contratos = Contrato::where('status', 'ativo')
            ->whereBetween('data_fim', [Carbon::today(), Carbon::today()->addDays(30)])
            ->with(['inquilino.user', 'imovel'])
            ->get();

        foreach ($contratos as $c) {
            $email = $c->getEmailInquilino();
            $dias = (int) Carbon::today()->diffInDays($c->data_fim);
            $ref = "contrato_{$c->id}";

            if (! $email || $es->jaEnviou('admin.contrato_vencendo', $email, $ref)) {
                continue;
            }

            $es->enviar('admin.contrato_vencendo', $email, $c->getNomeInquilino() ?? '', [
                'nome' => $c->getNomeInquilino(),
                'imovel_endereco' => $c->getEnderecoResumido(),
                'data_fim' => $c->data_fim->format('d/m/Y'),
                'dias' => $dias,
            ], $tenant->id);

            $this->resultado['contratos_inquilino']++;
        }
    }

    private function enviarAvisosContratoAdmin(Tenant $tenant, EmailNotificationService $es): void
    {
        $contratos = Contrato::where('status', 'ativo')
            ->whereBetween('data_fim', [Carbon::today(), Carbon::today()->addDays(30)])
            ->with(['inquilino.user', 'imovel'])
            ->get();

        $admin = $tenant->getAdminPrincipal();
        if (! $admin) {
            return;
        }

        foreach ($contratos as $c) {
            $ref = "contrato_{$c->id}";
            if ($es->jaEnviou('admin.contrato_vencendo_admin', $admin->email, $ref)) {
                continue;
            }

            $dias = (int) Carbon::today()->diffInDays($c->data_fim);

            $es->enviar('admin.contrato_vencendo_admin', $admin->email, $admin->name, [
                'imovel_endereco' => $c->getEnderecoResumido(),
                'inquilino_nome' => $c->getNomeInquilino(),
                'data_fim' => $c->data_fim->format('d/m/Y'),
                'dias' => $dias,
            ], $tenant->id);

            $this->resultado['contratos_admin']++;
        }
    }
}
