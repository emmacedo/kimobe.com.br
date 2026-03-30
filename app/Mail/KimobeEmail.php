<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable genérico que recebe HTML pronto e envia.
 */
class KimobeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $assuntoEmail,
        private string $corpoHtml,
        private ?string $corpoTexto = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assuntoEmail);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->corpoHtml);
    }
}
