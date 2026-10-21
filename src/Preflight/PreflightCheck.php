<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use J87NL\Mailing\Attachments\CampaignAttachments;
use J87NL\Mailing\Contracts\DnsResolver;
use J87NL\Mailing\Contracts\HtmlSanitizer;
use J87NL\Mailing\Contracts\SpamScorer;
use J87NL\Mailing\Drivers\DriverRegistry;
use J87NL\Mailing\Mail\CampaignMail;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Senders\ListSender;
use J87NL\Mailing\Support\EmailAddress;
use Symfony\Component\Mime\Email;

class PreflightCheck
{
    private const int MIN_TEXT_LENGTH = 20;

    private const int CACHE_SECONDS = 600;

    private const int MAX_REDIRECTS = 3;

    private const int CONNECT_TIMEOUT = 3;

    private const int REQUEST_TIMEOUT = 5;

    private const int UNREACHABLE = 0;

    private const string SAFE_URL = '#^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::443)?(?:[/?\#][^\s\\\\]*)?$#i';

    public function __construct(
        private readonly DnsResolver $dns,
        private readonly SpamScorer $spamScorer,
        private readonly DriverRegistry $drivers,
        private readonly ListSender $sender,
        private readonly RawMessageRenderer $renderer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly CampaignAttachments $attachmentRules,
    ) {}

    /**
     * @return list<PreflightItem>
     */
    public function run(Campaign $campaign): array
    {
        $list = $campaign->mailingList;
        $driver = $this->drivers->for($list)->key();
        $domain = EmailAddress::domain($this->sender->for($list)->email);

        return [
            $this->spf($this->envelopeDomain($domain, $driver), $driver),
            $this->dkim($domain, $driver),
            $this->dmarc($domain),
            $this->alignment($domain, $driver),
            ...($driver === 'postfix' ? [$this->blocklists()] : []),
            $this->spamScore($campaign),
            $this->links($campaign),
            $this->text($campaign),
            ...($campaign->attachments()->exists() ? [$this->attachments($campaign)] : []),
        ];
    }

    /**
     * @return list<PreflightItem>
     */
    public function cached(Campaign $campaign): array
    {
        $key = sprintf('mailing:preflight:%d:%s', $campaign->id, $campaign->updated_at?->getTimestamp() ?? 0);

        $items = Cache::remember($key, self::CACHE_SECONDS, fn (): array => array_map(
            fn (PreflightItem $item): array => [
                'key' => $item->key,
                'status' => $item->status,
                'message' => $item->message,
            ],
            $this->run($campaign),
        ));

        return array_map(
            fn (array $item): PreflightItem => new PreflightItem($item['key'], $item['status'], $item['message']),
            $items,
        );
    }

    /**
     * @param  list<PreflightItem>  $items
     */
    public static function blocks(array $items): bool
    {
        foreach ($items as $item) {
            if ($item->blocks()) {
                return true;
            }
        }

        return false;
    }

    private function envelopeDomain(string $fromDomain, string $driver): string
    {
        $bounceDomain = config('mailing.bounces.domain');

        if ($driver !== 'postfix' || ! is_string($bounceDomain) || $bounceDomain === '') {
            return $fromDomain;
        }

        return $bounceDomain;
    }

    private function spf(string $domain, string $driver): PreflightItem
    {
        $record = $this->record($this->dns->txt($domain), 'v=spf1');

        if (is_null($record)) {
            return $this->item('spf', PreflightItem::ERROR, 'spf.missing', [
                'domain' => $domain,
            ]);
        }

        $required = config(sprintf('mailing.preflight.spf.%s', $driver), []);
        $missing = array_values(array_filter(
            is_array($required) ? $required : [],
            fn (mixed $mechanism): bool => is_string($mechanism) && ! str_contains(strtolower($record), strtolower($mechanism)),
        ));

        if ($missing !== []) {
            return $this->item('spf', PreflightItem::ERROR, 'spf.incomplete', [
                'missing' => implode(', ', $missing),
            ]);
        }

        return $this->item('spf', PreflightItem::OK, 'spf.ok', [
            'record' => $record,
        ]);
    }

    private function dkim(string $domain, string $driver): PreflightItem
    {
        $selectors = config(sprintf('mailing.preflight.dkim_selectors.%s', $driver), []);
        $selectors = is_array($selectors) ? array_values(array_filter($selectors, 'is_string')) : [];

        if ($selectors === []) {
            return $this->item('dkim', PreflightItem::WARNING, 'dkim.unknown');
        }

        foreach ($selectors as $selector) {
            $record = $this->record($this->dns->txt(sprintf('%s._domainkey.%s', $selector, $domain)), 'v=DKIM1')
                ?? $this->record($this->dns->txt(sprintf('%s._domainkey.%s', $selector, $domain)), 'p=');

            if (is_null($record)) {
                return $this->item('dkim', PreflightItem::ERROR, 'dkim.missing', [
                    'selector' => $selector,
                ]);
            }
        }

        return $this->item('dkim', PreflightItem::OK, 'dkim.ok', [
            'selectors' => implode(', ', $selectors),
        ]);
    }

    private function dmarc(string $domain): PreflightItem
    {
        $record = $this->record($this->dns->txt(sprintf('_dmarc.%s', $domain)), 'v=DMARC1');

        if (is_null($record)) {
            return $this->item('dmarc', PreflightItem::ERROR, 'dmarc.missing', [
                'domain' => $domain,
            ]);
        }

        return $this->item('dmarc', PreflightItem::OK, 'dmarc.ok', [
            'record' => $record,
        ]);
    }

    private function alignment(string $domain, string $driver): PreflightItem
    {
        if ($driver !== 'postfix') {
            return $this->item('alignment', PreflightItem::WARNING, 'alignment.provider');
        }

        $bounceDomain = config('mailing.bounces.domain');

        if (! is_string($bounceDomain) || $bounceDomain === '') {
            return $this->item('alignment', PreflightItem::OK, 'alignment.from_domain');
        }

        if ($bounceDomain === $domain || str_ends_with($bounceDomain, sprintf('.%s', $domain))) {
            return $this->item('alignment', PreflightItem::OK, 'alignment.ok', [
                'domain' => $bounceDomain,
            ]);
        }

        return $this->item('alignment', PreflightItem::ERROR, 'alignment.mismatch', [
            'bounce' => $bounceDomain,
            'from' => $domain,
        ]);
    }

    private function blocklists(): PreflightItem
    {
        $ips = config('mailing.preflight.sending_ips', []);
        $zones = config('mailing.preflight.blocklists', []);
        $ips = is_array($ips) ? array_values(array_filter($ips, fn (mixed $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)) : [];
        $zones = is_array($zones) ? array_values(array_filter($zones, 'is_string')) : [];

        if ($ips === []) {
            return $this->item('blocklists', PreflightItem::WARNING, 'blocklists.unknown');
        }

        $listed = [];
        $unclear = [];

        foreach ($ips as $ip) {
            $reversed = implode('.', array_reverse(explode('.', $ip)));

            foreach ($zones as $zone) {
                foreach ($this->dns->a(sprintf('%s.%s', $reversed, $zone)) as $answer) {
                    if (str_starts_with($answer, '127.255.255.')) {
                        $unclear[] = $zone;

                        continue;
                    }

                    if (str_starts_with($answer, '127.')) {
                        $listed[] = sprintf('%s (%s)', $ip, $zone);
                    }
                }
            }
        }

        if ($listed !== []) {
            return $this->item('blocklists', PreflightItem::ERROR, 'blocklists.listed', [
                'listed' => implode(', ', array_unique($listed)),
            ]);
        }

        if ($unclear !== []) {
            return $this->item('blocklists', PreflightItem::WARNING, 'blocklists.refused', [
                'zones' => implode(', ', array_unique($unclear)),
            ]);
        }

        return $this->item('blocklists', PreflightItem::OK, 'blocklists.ok', [
            'ips' => implode(', ', $ips),
        ]);
    }

    private function spamScore(Campaign $campaign): PreflightItem
    {
        $driver = $this->drivers->for($campaign->mailingList);
        $mail = (new CampaignMail($campaign))->withSymfonyMessage(fn (Email $message) => $driver->prepareTest($message, $campaign));
        $raw = $this->renderer->render($mail, 'preflight@example.org');
        $score = $this->spamScorer->score($raw);

        if (is_null($score)) {
            return $this->item('spam_score', PreflightItem::WARNING, 'spam_score.unavailable');
        }

        $threshold = (float) config('mailing.preflight.spam_threshold', 5.0);

        if ($score >= $threshold) {
            return $this->item('spam_score', PreflightItem::ERROR, 'spam_score.high', [
                'score' => number_format($score, 1),
            ]);
        }

        return $this->item('spam_score', PreflightItem::OK, 'spam_score.ok', [
            'score' => number_format($score, 1),
        ]);
    }

    private function links(Campaign $campaign): PreflightItem
    {
        preg_match_all('#<a\b[^>]*href=(["\'])(.*?)\1#i', $this->sanitizer->sanitize($campaign->body_html), $matches);

        $urls = array_values(array_unique(array_map(
            fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5),
            $matches[2],
        )));
        $deadline = microtime(true) + (float) config('mailing.preflight.link_budget_seconds', 20);
        $broken = [];
        $unverified = [];

        foreach ($urls as $url) {
            if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
                continue;
            }

            if (microtime(true) >= $deadline) {
                $unverified[] = $url;

                continue;
            }

            $status = $this->finalStatus($url, $deadline);

            if (is_null($status)) {
                $broken[] = $url;

                continue;
            }

            if ($status !== 200) {
                $unverified[] = $url;
            }
        }

        if ($broken !== []) {
            return $this->item('links', PreflightItem::ERROR, 'links.broken', [
                'urls' => implode(', ', $broken),
            ]);
        }

        if ($unverified !== []) {
            return $this->item('links', PreflightItem::WARNING, 'links.unverified', [
                'urls' => implode(', ', $unverified),
            ]);
        }

        return $this->item('links', PreflightItem::OK, 'links.ok', [
            'count' => count($urls),
        ]);
    }

    private function text(Campaign $campaign): PreflightItem
    {
        $visible = $this->visibleText($campaign);

        if (mb_strlen($visible) < self::MIN_TEXT_LENGTH || trim($campaign->body_text) === '') {
            return $this->item('text', PreflightItem::ERROR, 'text.missing');
        }

        return $this->item('text', PreflightItem::OK, 'text.ok');
    }

    private function attachments(Campaign $campaign): PreflightItem
    {
        $attached = (int) $campaign->attachments()
            ->where('as_link', false)
            ->sum('size');
        $warnAt = $this->attachmentRules->warnTotalBytes();

        if ($attached > $warnAt) {
            return $this->item('attachments', PreflightItem::WARNING, 'attachments.large', [
                'size' => CampaignAttachments::size($attached),
                'max' => CampaignAttachments::size($warnAt),
            ]);
        }

        $minText = max(0, (int) config('mailing.attachments.min_text_characters', 200));

        if ($attached > 0 && mb_strlen($this->visibleText($campaign)) < $minText) {
            return $this->item('attachments', PreflightItem::WARNING, 'attachments.mostly_attachment', [
                'min' => $minText,
            ]);
        }

        return $this->item('attachments', PreflightItem::OK, 'attachments.ok', [
            'size' => CampaignAttachments::size($attached),
        ]);
    }

    private function visibleText(Campaign $campaign): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($this->sanitizer->sanitize($campaign->body_html)), ENT_QUOTES | ENT_HTML5)));
    }

    private function finalStatus(string $url, float $deadline): ?int
    {
        $status = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (preg_match(self::SAFE_URL, $url) !== 1) {
                return $status;
            }

            $address = $this->publicAddress((string) parse_url($url, PHP_URL_HOST));

            if (is_null($address)) {
                return null;
            }

            $response = $this->head($url, $address, $deadline);

            if (is_null($response)) {
                return self::UNREACHABLE;
            }

            $status = $response->status();
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $status;
            }

            $url = $this->absolute($url, $location);
        }

        return $status;
    }

    private function head(string $url, string $address, float $deadline): ?ClientResponse
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $remaining = $deadline - microtime(true);

        if ($remaining <= 0) {
            return null;
        }

        $request = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(max(1, (int) min(self::REQUEST_TIMEOUT, ceil($remaining))))
            ->withoutRedirecting()
            ->withOptions([
                'stream' => true,
                'curl' => [
                    CURLOPT_RESOLVE => [
                        sprintf('%s:443:%s', $host, $address),
                    ],
                ],
            ]);

        try {
            $response = $request->head($url);

            if ($response->status() === 405) {
                $response = $request->get($url);
            }
        } catch (ConnectionException) {
            return null;
        }

        return $response;
    }

    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        $scheme = (string) parse_url($base, PHP_URL_SCHEME);
        $host = (string) parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);
        $origin = is_null($port) ? sprintf('%s://%s', $scheme, $host) : sprintf('%s://%s:%d', $scheme, $host, $port);

        if (str_starts_with($location, '//')) {
            return sprintf('%s:%s', $scheme, $location);
        }

        if (str_starts_with($location, '/')) {
            return sprintf('%s%s', $origin, $location);
        }

        $path = (string) parse_url($base, PHP_URL_PATH);

        if (str_starts_with($location, '?') || str_starts_with($location, '#')) {
            return sprintf('%s%s%s', $origin, $path === '' ? '/' : $path, $location);
        }

        $directory = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/') + 1) : '/';

        return sprintf('%s%s%s', $origin, $directory, $location);
    }

    private function publicAddress(string $host): ?string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) === false ? $this->dns->a($host) : [$host];

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * @param  list<string>  $records
     */
    private function record(array $records, string $prefix): ?string
    {
        foreach ($records as $record) {
            if (stripos(trim($record, '" '), $prefix) !== false) {
                return trim($record, '" ');
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function item(string $key, string $status, string $message, array $replace = []): PreflightItem
    {
        return new PreflightItem($key, $status, (string) __(sprintf('mailing::preflight.messages.%s', $message), $replace));
    }
}
