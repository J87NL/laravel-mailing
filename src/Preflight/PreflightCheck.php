<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use J87NL\Mailing\Contracts\DnsResolver;
use J87NL\Mailing\Contracts\SpamScorer;
use J87NL\Mailing\Drivers\DriverRegistry;
use J87NL\Mailing\Mail\CampaignMail;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Senders\ListSender;
use J87NL\Mailing\Support\EmailAddress;

class PreflightCheck
{
    private const int MIN_TEXT_LENGTH = 20;

    public function __construct(
        private readonly DnsResolver $dns,
        private readonly SpamScorer $spamScorer,
        private readonly DriverRegistry $drivers,
        private readonly ListSender $sender,
        private readonly RawMessageRenderer $renderer,
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
            $this->spf($domain, $driver),
            $this->dkim($domain, $driver),
            $this->dmarc($domain),
            $this->alignment($domain, $driver),
            ...($driver === 'postfix' ? [$this->blocklists()] : []),
            $this->spamScore($campaign),
            $this->links($campaign),
            $this->text($campaign),
        ];
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

    private function spf(string $domain, string $driver): PreflightItem
    {
        $record = $this->record($this->dns->txt($domain), 'v=spf1');

        if (is_null($record)) {
            return $this->item('spf', PreflightItem::ERROR, 'spf.missing', ['domain' => $domain]);
        }

        $required = config(sprintf('mailing.preflight.spf.%s', $driver), []);
        $missing = array_values(array_filter(
            is_array($required) ? $required : [],
            fn (mixed $mechanism): bool => is_string($mechanism) && ! str_contains(strtolower($record), strtolower($mechanism)),
        ));

        if ($missing !== []) {
            return $this->item('spf', PreflightItem::ERROR, 'spf.incomplete', ['missing' => implode(', ', $missing)]);
        }

        return $this->item('spf', PreflightItem::OK, 'spf.ok', ['record' => $record]);
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
                return $this->item('dkim', PreflightItem::ERROR, 'dkim.missing', ['selector' => $selector]);
            }
        }

        return $this->item('dkim', PreflightItem::OK, 'dkim.ok', ['selectors' => implode(', ', $selectors)]);
    }

    private function dmarc(string $domain): PreflightItem
    {
        $record = $this->record($this->dns->txt(sprintf('_dmarc.%s', $domain)), 'v=DMARC1');

        if (is_null($record)) {
            return $this->item('dmarc', PreflightItem::ERROR, 'dmarc.missing', ['domain' => $domain]);
        }

        return $this->item('dmarc', PreflightItem::OK, 'dmarc.ok', ['record' => $record]);
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
            return $this->item('alignment', PreflightItem::OK, 'alignment.ok', ['domain' => $bounceDomain]);
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
            return $this->item('blocklists', PreflightItem::ERROR, 'blocklists.listed', ['listed' => implode(', ', array_unique($listed))]);
        }

        if ($unclear !== []) {
            return $this->item('blocklists', PreflightItem::WARNING, 'blocklists.refused', ['zones' => implode(', ', array_unique($unclear))]);
        }

        return $this->item('blocklists', PreflightItem::OK, 'blocklists.ok', ['ips' => implode(', ', $ips)]);
    }

    private function spamScore(Campaign $campaign): PreflightItem
    {
        $raw = $this->renderer->render(new CampaignMail($campaign), 'preflight@example.org');
        $score = $this->spamScorer->score($raw);

        if (is_null($score)) {
            return $this->item('spam_score', PreflightItem::WARNING, 'spam_score.unavailable');
        }

        $threshold = (float) config('mailing.preflight.spam_threshold', 5.0);

        if ($score >= $threshold) {
            return $this->item('spam_score', PreflightItem::ERROR, 'spam_score.high', ['score' => number_format($score, 1)]);
        }

        return $this->item('spam_score', PreflightItem::OK, 'spam_score.ok', ['score' => number_format($score, 1)]);
    }

    private function links(Campaign $campaign): PreflightItem
    {
        preg_match_all('#<a\b[^>]*href=(["\'])(.*?)\1#i', $campaign->body_html, $matches);

        $urls = array_values(array_unique(array_map(
            fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5),
            $matches[2],
        )));
        $broken = [];

        foreach ($urls as $url) {
            if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
                continue;
            }

            if (! str_starts_with($url, 'https://')) {
                $broken[] = $url;

                continue;
            }

            if (! $this->answers($url)) {
                $broken[] = $url;
            }
        }

        if ($broken !== []) {
            return $this->item('links', PreflightItem::ERROR, 'links.broken', ['urls' => implode(', ', $broken)]);
        }

        return $this->item('links', PreflightItem::OK, 'links.ok', ['count' => count($urls)]);
    }

    private function text(Campaign $campaign): PreflightItem
    {
        $visible = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($campaign->body_html), ENT_QUOTES | ENT_HTML5)));

        if (mb_strlen($visible) < self::MIN_TEXT_LENGTH || trim($campaign->body_text) === '') {
            return $this->item('text', PreflightItem::ERROR, 'text.missing');
        }

        return $this->item('text', PreflightItem::OK, 'text.ok');
    }

    private function answers(string $url): bool
    {
        try {
            $response = Http::timeout(8)
                ->withoutRedirecting()
                ->head($url);

            if ($response->status() === 405) {
                $response = Http::timeout(8)
                    ->withoutRedirecting()
                    ->get($url);
            }
        } catch (ConnectionException) {
            return false;
        }

        return $response->status() === 200;
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
