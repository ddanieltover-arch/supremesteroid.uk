<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $bodyLines
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $headline = '',
        public array $bodyLines = [],
        public ?string $orderNumber = null,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
        if ($this->headline === '') {
            $this->headline = $this->subjectLine;
        }

        if ($this->bodyLines === [] && $this->bodyText !== '') {
            $this->bodyLines = [$this->bodyText];
        }
    }

    public function envelope(): Envelope
    {
        $support = (string) (config('store.support_email') ?: 'info@supremesteroid.uk');

        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [
                new Address($support, (string) (config('store.name') ?: 'Supreme Steroids')),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer',
            with: [
                'headline' => $this->headline,
                'bodyText' => $this->bodyText,
                'bodyLines' => $this->bodyLines,
                'orderNumber' => $this->orderNumber,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'storeName' => (string) (config('store.name') ?: 'Supreme Steroids'),
                'supportEmail' => (string) (config('store.support_email') ?: 'info@supremesteroid.uk'),
                'logoUrl' => $this->absoluteLogoUrl(),
                'businessAddress' => $this->formattedAddress(),
            ],
        );
    }

    protected function absoluteLogoUrl(): ?string
    {
        $logo = config('store.logo_url');
        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($logo, '/');
    }

    protected function formattedAddress(): string
    {
        $address = config('store.business_address');
        if (! is_array($address)) {
            return '';
        }

        $parts = array_filter([
            $address['street'] ?? null,
            $address['locality'] ?? null,
            $address['city'] ?? null,
            $address['postal_code'] ?? null,
            $address['country'] ?? null,
        ]);

        return implode(', ', $parts);
    }
}
