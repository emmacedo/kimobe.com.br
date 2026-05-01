<?php

namespace App\Mail\Billing;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KicolBillingAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $triggerModule,
        public ?string $currentPlanCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[ALERTA Kimobe] Tenant '.$this->tenant->nome.' precisa de plano custom',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.billing.kicol-billing-alert',
        );
    }
}
