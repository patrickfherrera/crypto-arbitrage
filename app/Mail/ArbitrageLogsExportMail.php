<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArbitrageLogsExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $absolutePath,
        public string $filename,
        public int $rowCount,
        public string $rangeLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Arbitrage logs CSV export ('.$this->rangeLabel.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.arbitrage-logs-export',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->absolutePath)
                ->as($this->filename)
                ->withMime('text/csv'),
        ];
    }
}
