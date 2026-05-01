<?php

namespace App\Mail\Billing;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TopPlanOverageNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $triggerModule,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Você atingiu o limite máximo do nosso maior plano',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.billing.top-plan-overage-notice',
            with: [
                'moduleLabel' => $this->moduleLabel(),
            ],
        );
    }

    private function moduleLabel(): string
    {
        return match ($this->triggerModule) {
            'imoveis' => 'imóveis cadastrados',
            'gestao_imoveis' => 'gestão de imóveis',
            'integracao_meio_pagamento' => 'integração com meio de pagamento',
            'dominio_proprio' => 'domínio próprio',
            default => $this->triggerModule,
        };
    }
}
