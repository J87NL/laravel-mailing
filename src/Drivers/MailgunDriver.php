<?php

declare(strict_types=1);

namespace J87NL\Mailing\Drivers;

use Illuminate\Http\Request;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Enums\DeliveryEventType;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

class MailgunDriver implements MailingDriver
{
    public const string SEND_VARIABLE = 'mailing_send_id';

    private const string PERMANENT = 'permanent';

    private const string TEMPORARY = 'temporary';

    private const array EVENTS = [
        'delivered' => DeliveryEventType::Delivered,
        'complained' => DeliveryEventType::Complained,
        'unsubscribed' => DeliveryEventType::Unsubscribed,
    ];

    public function key(): string
    {
        return 'mailgun';
    }

    public function prepare(Email $message, Send $send): void
    {
        $headers = $message->getHeaders();

        $headers->add(new MetadataHeader(self::SEND_VARIABLE, (string) $send->id));
        $headers->add(new TagHeader(sprintf('campaign-%d', $send->campaign_id)));
    }

    public function prepareTest(Email $message, Campaign $campaign): void
    {
        $message->getHeaders()->add(new TagHeader(sprintf('campaign-%d-test', $campaign->id)));
    }

    public function hasWebhooks(): bool
    {
        return true;
    }

    public function verifyWebhook(Request $request): bool
    {
        $key = config('mailing.drivers.mailgun.webhook_signing_key');
        $signature = $this->payload($request)['signature'] ?? null;

        if (! is_string($key) || $key === '' || ! is_array($signature)) {
            return false;
        }

        $timestamp = is_int($signature['timestamp'] ?? null) ? (string) $signature['timestamp'] : ($signature['timestamp'] ?? null);
        $token = $signature['token'] ?? null;
        $given = $signature['signature'] ?? null;

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($token) || $token === '' || ! is_string($given)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > (int) config('mailing.drivers.mailgun.tolerance', 300)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', sprintf('%s%s', $timestamp, $token), $key), strtolower($given));
    }

    public function parseWebhook(Request $request): array
    {
        $data = $this->payload($request)['event-data'] ?? null;

        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['event'] ?? null)) {
            return [];
        }

        $sendId = $this->sendId($data);
        $type = $this->type($data);

        if (is_null($sendId) || is_null($type)) {
            return [];
        }

        return [
            new DeliveryEvent(
                type: $type,
                eventId: $data['id'],
                sendId: $sendId,
                messageId: $this->messageId($data),
                recipient: is_string($data['recipient'] ?? null) && $data['recipient'] !== '' ? $data['recipient'] : null,
                diagnostic: $this->diagnostic($data),
            ),
        ];
    }

    public function reportsDelivery(): bool
    {
        return true;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function type(array $data): ?DeliveryEventType
    {
        if ($data['event'] !== 'failed') {
            return self::EVENTS[$data['event']] ?? null;
        }

        $severity = $data['severity'] ?? null;

        if ($severity === self::TEMPORARY) {
            return DeliveryEventType::SoftBounce;
        }

        if ($severity !== self::PERMANENT) {
            return null;
        }

        $code = $this->deliveryStatus($data)['code'] ?? null;

        if (is_numeric($code) && (int) $code >= 500 && (int) $code < 600) {
            return DeliveryEventType::HardBounce;
        }

        return DeliveryEventType::Failed;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function sendId(array $data): ?int
    {
        $variables = is_array($data['user-variables'] ?? null) ? $data['user-variables'] : [];
        $sendId = $variables[self::SEND_VARIABLE] ?? null;

        if (! is_numeric($sendId) || (int) $sendId < 1) {
            return null;
        }

        return (int) $sendId;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function messageId(array $data): ?string
    {
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $headers = is_array($message['headers'] ?? null) ? $message['headers'] : [];

        return is_string($headers['message-id'] ?? null) && $headers['message-id'] !== '' ? $headers['message-id'] : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function deliveryStatus(array $data): array
    {
        return is_array($data['delivery-status'] ?? null) ? $data['delivery-status'] : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function diagnostic(array $data): ?string
    {
        $status = $this->deliveryStatus($data);

        $parts = array_filter([
            is_scalar($status['code'] ?? null) ? (string) $status['code'] : null,
            is_string($status['message'] ?? null) ? trim($status['message']) : null,
            is_string($status['description'] ?? null) ? trim($status['description']) : null,
            is_string($data['reason'] ?? null) && $data['event'] === 'failed' ? sprintf('(%s)', $data['reason']) : null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
