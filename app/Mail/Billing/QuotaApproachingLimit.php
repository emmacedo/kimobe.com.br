<?php

namespace App\Mail\Billing;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Kicol\FullFlow\Models\FullFlowPlan;

class QuotaApproachingLimit extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $moduleSlug,
        public int $threshold,         // 80 ou 95
        public int $currentValue,
        public int $limitValue,
        public ?FullFlowPlan $nextPlan = null,
        public bool $autoUpgradeEnabled = true,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->moduleLabel();
        $subject = $this->threshold >= 95
            ? "Atenção: {$label} a 5% do limite do plano"
            : "Aviso: {$label} a 80% do limite do plano";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.billing.quota-approaching-limit',
            with: [
                'moduleLabel' => $this->moduleLabel(),
                'percentUsed' => $this->limitValue > 0
                    ? (int) round(($this->currentValue / $this->limitValue) * 100)
                    : 0,
            ],
        );
    }

    private function moduleLabel(): string
    {
        return match ($this->moduleSlug) {
            'imoveis' => 'imóveis cadastrados',
            default => $this->moduleSlug,
        };
    }
}
