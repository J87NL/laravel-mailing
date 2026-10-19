<?php

declare(strict_types=1);

namespace J87NL\Mailing\Drivers;

use Illuminate\Http\Request;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Models\Send;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

class LettermintDriver implements MailingDriver
{
    private const array RECIPIENT_KEYS = [
        'recipient',
        'value',
    ];

    public const string SIGNATURE_HEADER = 'X-Lettermint-Signature';

    public const string DELIVERY_HEADER = 'X-Lettermint-Delivery';

    private const array EVENTS = [
        'message.delivered' => DeliveryEventType::Delivered,
        'message.soft_bounced' => DeliveryEventType::SoftBounce,
        'message.hard_bounced' => DeliveryEventType::HardBounce,
        'message.spam_complaint' => DeliveryEventType::Complained,
        'message.failed' => DeliveryEventType::Failed,
        'message.suppressed' => DeliveryEventType::Failed,
        'message.policy_rejected' => DeliveryEventType::Failed,
        'message.unsubscribed' => DeliveryEventType::Unsubscribed,
        'suppression.added' => DeliveryEventType::SuppressionAdded,
        'suppression.removed' => DeliveryEventType::SuppressionRemoved,
    ];

    public function key(): string
    {
        return 'lettermint';
    }

    public function prepare(Email $message, Send $send): void
    {
        $headers = $message->getHeaders();

        $headers->add(new MetadataHeader('send_id', (string) $send->id));
        $headers->add(new TagHeader(sprintf('campaign-%d', $send->campaign_id)));
        $headers->addTextHeader('Idempotency-Key', sprintf('mailing-send-%d', $send->id));
    }

    public function hasWebhooks(): bool
    {
        return true;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('mailing.drivers.lettermint.webhook_secret');
        $header = (string) $request->header(self::SIGNATURE_HEADER, '');
        $body = $request->getContent();

        if (! is_string($secret) || $secret === '' || $header === '' || $body === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $signatures[] = strtolower($value);
            }
        }

        if (is_null($timestamp) || (string) $request->header(self::DELIVERY_HEADER, '') !== $timestamp) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > (int) config('mailing.drivers.lettermint.tolerance', 300)) {
            return false;
        }

        $expected = hash_hmac('sha256', sprintf('%s.%s', $timestamp, $body), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['event'] ?? null) || ! is_string($payload['id'] ?? null)) {
            return [];
        }

        $type = self::EVENTS[$payload['event']] ?? null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (is_null($type)) {
            return [];
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $sendId = $metadata['send_id'] ?? null;

        return [
            new DeliveryEvent(
                type: $type,
                eventId: $payload['id'],
                sendId: is_numeric($sendId) ? (int) $sendId : null,
                messageId: is_string($data['message_id'] ?? null) ? $data['message_id'] : null,
                recipient: $this->recipient($data),
                diagnostic: $this->diagnostic($data),
            ),
        ];
    }

    public function reportsDelivery(): bool
    {
        return true;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function recipient(array $data): ?string
    {
        foreach (self::RECIPIENT_KEYS as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function diagnostic(array $data): ?string
    {
        $response = is_array($data['response'] ?? null) ? $data['response'] : [];

        $parts = array_filter([
            is_scalar($response['status_code'] ?? null) ? (string) $response['status_code'] : null,
            is_string($response['enhanced_status_code'] ?? null) ? $response['enhanced_status_code'] : null,
            is_string($response['content'] ?? null) ? $response['content'] : null,
            is_string($data['reason'] ?? null) ? $data['reason'] : null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
