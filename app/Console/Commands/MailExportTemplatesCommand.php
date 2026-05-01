<?php

namespace App\Console\Commands;

use App\Mail\Billing\AutoUpgradePerformed;
use App\Mail\Billing\KicolBillingAlert;
use App\Mail\Billing\QuotaApproachingLimit;
use App\Mail\Billing\TopPlanOverageNotice;
use App\Mail\KimobeEmail;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Kicol\FullFlow\Models\FullFlowPlan;

class MailExportTemplatesCommand extends Command
{
    protected $signature = 'mail:export-templates {email}';

    protected $description = 'Envia todos os modelos de email do Kimobe para o destinatário informado (1 email por template)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $this->info("Enviando todos os modelos de email para {$email}...");

        $this->forcarSmtpHostinger();

        $vars = $this->variaveisDeExemplo();
        $totalEnviados = 0;
        $falhas = [];

        // 1) Templates do banco (EmailTemplate)
        $templates = EmailTemplate::ativo()->orderBy('chave')->get();
        $this->info("Encontrados {$templates->count()} templates ativos no banco.");

        foreach ($templates as $template) {
            try {
                $assuntoOriginal = $template->renderAssunto($vars);
                $assunto = "[MODELO #{$template->id}] {$template->chave} — {$assuntoOriginal}";
                $html = $this->envoltorio($template->chave, $template->renderCorpoHtml($vars));
                Mail::to($email)->send(new KimobeEmail($assunto, $html));
                $this->line("  ✓ {$template->chave}");
                $totalEnviados++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$template->chave}: ".$e->getMessage());
                $falhas[] = $template->chave;
            }
        }

        // 2) Mailables de billing FullFlow (instâncias em memória)
        $tenantFake = $this->fakeTenant();
        $planoStarter = $this->fakePlan('kimobe_starter', 'Starter', 39.90);
        $planoProfissional = $this->fakePlan('kimobe_profissional', 'Profissional', 129.90);

        $billingMailables = [
            'AutoUpgradePerformed' => new AutoUpgradePerformed(
                tenant: $tenantFake,
                fromPlan: $planoStarter,
                toPlan: $planoProfissional,
                triggerModule: 'imoveis',
                prorationAmount: 75.50,
            ),
            'TopPlanOverageNotice' => new TopPlanOverageNotice(
                tenant: $tenantFake,
                triggerModule: 'imoveis',
            ),
            'KicolBillingAlert' => new KicolBillingAlert(
                tenant: $tenantFake,
                triggerModule: 'imoveis',
                currentPlanCode: 'kimobe_business',
            ),
            'QuotaApproachingLimit' => new QuotaApproachingLimit(
                tenant: $tenantFake,
                moduleSlug: 'imoveis',
                threshold: 95,
                currentValue: 48,
                limitValue: 50,
                nextPlan: $planoProfissional,
                autoUpgradeEnabled: true,
            ),
        ];

        foreach ($billingMailables as $nome => $mailable) {
            try {
                Mail::to($email)->send($mailable);
                $this->line("  ✓ Billing\\{$nome}");
                $totalEnviados++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Billing\\{$nome}: ".$e->getMessage());
                $falhas[] = "Billing\\{$nome}";
            }
        }

        $this->newLine();
        $this->info("Total enviado: {$totalEnviados}");
        if ($falhas) {
            $this->warn('Falhas: '.implode(', ', $falhas));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function forcarSmtpHostinger(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => 'smtp.hostinger.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ]);

        // Limpa instâncias resolvidas do mail manager
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstances();

        $this->line('  • Mailer forçado: smtp.hostinger.com:465 ssl');
    }

    private function fakeTenant(): Tenant
    {
        $tenant = new Tenant;
        $tenant->id = 999;
        $tenant->nome = 'Imobiliária Exemplo Ltda';
        $tenant->legal_name = 'Imobiliária Exemplo Comércio e Serviços Imobiliários Ltda';
        $tenant->tipo = 'imobiliaria';
        $tenant->tipo_documento = 'cnpj';
        $tenant->documento = '12.345.678/0001-90';
        $tenant->status = 'ativo';
        $tenant->is_exempt_from_subscription = false;
        $tenant->auto_upgrade_enabled = true;

        return $tenant;
    }

    private function fakePlan(string $code, string $name, float $amount): FullFlowPlan
    {
        $plan = new FullFlowPlan;
        $plan->code = $code;
        $plan->name = $name;
        $plan->amount = $amount;
        $plan->billing_cycle = 'mensal';
        $plan->trial_days = 14;
        $plan->description = 'Plano '.$name.' do Kimobe';

        return $plan;
    }

    private function variaveisDeExemplo(): array
    {
        return [
            'nome' => 'Eduardo Macedo',
            'email' => 'eduardo@exemplo.com',
            'nome_empresa' => 'Imobiliária Exemplo Ltda',
            'plano_nome' => 'Profissional',
            'link_sistema' => 'https://kimobe.com.br/dashboard',
            'tipo_tenant' => 'imobiliaria',
            'documento' => '12.345.678/0001-90',
            'motivo' => 'Pagamento em atraso há mais de 15 dias',

            'imovel_endereco' => 'Rua das Flores, 123 — Centro, São Paulo/SP',
            'inquilino_nome' => 'Maria Silva Santos',
            'referencia' => 'Aluguel referente a Janeiro/2026',
            'data_inicio' => '01/01/2026',
            'data_fim' => '31/12/2026',
            'data_encerramento' => '31/12/2025',

            'valor' => 'R$ 2.500,00',
            'valor_total' => 'R$ 2.687,50',
            'valor_aluguel' => 'R$ 2.500,00',
            'valor_pago' => 'R$ 2.687,50',
            'valor_multa' => 'R$ 187,50',
            'valor_juros' => 'R$ 25,00',
            'valor_liquido' => 'R$ 2.250,00',

            'data_vencimento' => '10/05/2026',
            'data_pagamento' => '08/05/2026',
            'data_envio' => '01/05/2026',
            'data_prevista' => '15/05/2026',
            'data_realizada' => '14/05/2026',
            'dias_atraso' => '5',
            'dias' => '7',
            'dias_para_bloqueio' => '3',

            'metodo_pagamento' => 'PIX',
            'banco_nome' => 'Banco do Brasil',
            'conta' => 'Ag. 1234-5 / Cc. 67890-1',
        ];
    }

    private function envoltorio(string $chave, string $html): string
    {
        $cabecalho = '<div style="background:#0A4F5C;color:#fff;padding:16px 24px;font-family:Arial,sans-serif;border-radius:8px 8px 0 0;">'
            .'<div style="font-size:11px;letter-spacing:1px;opacity:0.8;text-transform:uppercase;">Modelo de Email · Kimobe</div>'
            .'<div style="font-size:16px;font-weight:600;margin-top:4px;">Chave: '.htmlspecialchars($chave).'</div>'
            .'</div>';
        $rodape = '<div style="background:#F1F4F2;color:#5A6864;padding:12px 24px;font-family:Arial,sans-serif;font-size:11px;border-radius:0 0 8px 8px;border-top:1px solid #D8DCDA;">'
            .'Este é um exemplo do modelo "<strong>'.htmlspecialchars($chave).'</strong>" usado pelo Kimobe. Variáveis renderizadas com dados de demonstração.'
            .'</div>';

        return '<div style="max-width:680px;margin:24px auto;font-family:Arial,sans-serif;">'
            .$cabecalho
            .'<div style="background:#fff;padding:24px;border-left:1px solid #D8DCDA;border-right:1px solid #D8DCDA;">'.$html.'</div>'
            .$rodape
            .'</div>';
    }
}
