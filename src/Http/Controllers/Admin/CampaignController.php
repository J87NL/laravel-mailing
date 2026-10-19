<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailing\Actions\ChangeCampaignStatus;
use J87NL\Mailing\Actions\SaveCampaign;
use J87NL\Mailing\Drivers\DriverRegistry;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Forms\CampaignForm;
use J87NL\Mailing\Forms\ScheduleForm;
use J87NL\Mailing\Forms\TestSendForm;
use J87NL\Mailing\Mail\CampaignMail;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Preflight\PreflightCheck;
use J87NL\Mailing\Preflight\PreflightItem;
use J87NL\Mailing\Support\MailingTime;
use J87NL\Mailing\Tables\CampaignTable;
use J87NL\Mailing\Tables\SendTable;
use TranquilTools\FormBuilder\FormConfig;

class CampaignController extends Controller
{
    private const int MAX_TEST_RECIPIENTS = 5;

    private const int TEST_SENDS_PER_HOUR = 10;

    private const array UNSTARTED = [
        CampaignStatus::Draft,
        CampaignStatus::Scheduled,
    ];

    private const array RETRYABLE = [
        CampaignStatus::Sending,
        CampaignStatus::Paused,
        CampaignStatus::Sent,
    ];

    private const array CANCELLABLE = [
        CampaignStatus::Scheduled,
        CampaignStatus::Sending,
        CampaignStatus::Paused,
    ];

    public function index(): Response
    {
        return Inertia::render($this->page('campaigns_index'), [
            'campaigns' => CampaignTable::build(),
            'createUrl' => route('mailing.campaigns.create'),
            'createDisabledReason' => MailingList::query()
                ->exists() ? null : __('mailing::campaigns.errors.no_lists'),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(): Response
    {
        return $this->composer(__('mailing::campaigns.create.title'), route('mailing.campaigns.create'), CampaignForm::make()
            ->action(route('mailing.campaigns.store'))
            ->method('POST'));
    }

    public function store(Request $request, SaveCampaign $saveCampaign): RedirectResponse
    {
        $campaign = new Campaign;

        $saveCampaign->handle($campaign, CampaignForm::make()->validate($request), $this->userId($request));

        $this->toast(__('mailing::campaigns.flash.saved'));

        return to_route('mailing.campaigns.show', $campaign);
    }

    public function edit(Campaign $campaign): Response|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return $this->locked($campaign);
        }

        return $this->composer($campaign->subject, route('mailing.campaigns.edit', $campaign), CampaignForm::make()
            ->action(route('mailing.campaigns.update', $campaign))
            ->method('PUT')
            ->fill([
                'list_id' => (string) $campaign->list_id,
                'subject' => $campaign->subject,
                'preheader' => $campaign->preheader,
                'body_html' => $campaign->body_html,
                'body_text' => $campaign->body_text,
            ]));
    }

    public function update(Request $request, Campaign $campaign, SaveCampaign $saveCampaign): RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return $this->locked($campaign);
        }

        if (! $saveCampaign->handle($campaign, CampaignForm::make()->validate($request), $this->userId($request))) {
            return $this->locked($campaign->refresh());
        }

        $this->toast(__('mailing::campaigns.flash.saved'));

        return to_route('mailing.campaigns.show', $campaign);
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            return $this->locked($campaign);
        }

        $campaign->delete();

        $this->toast(__('mailing::campaigns.flash.deleted'));

        return to_route('mailing.campaigns.index');
    }

    public function show(Request $request, Campaign $campaign, PreflightCheck $preflight, DriverRegistry $drivers): Response
    {
        $campaign->loadMissing('mailingList');

        $user = $request->user();
        $canEdit = (bool) $user?->can(MailingPermission::CampaignsEdit->value);
        $canSend = (bool) $user?->can(MailingPermission::CampaignsSend->value);
        $items = $campaign->isEditable() ? $preflight->cached($campaign) : [];
        $recipients = $this->recipientCount($campaign);
        $counts = $this->counts($campaign);
        $driver = $drivers->for($campaign->mailingList);

        $scheduleBlocked = match (true) {
            ! $canSend => (string) __('mailing::campaigns.errors.needs_send'),
            PreflightCheck::blocks($items) => (string) __('mailing::campaigns.errors.preflight'),
            $recipients === 0 && $campaign->status === CampaignStatus::Draft => (string) __('mailing::campaigns.errors.no_recipients'),
            default => null,
        };

        return Inertia::render($this->page('campaigns_show'), [
            'campaign' => [
                'subject' => $campaign->subject,
                'list' => $campaign->mailingList->name,
                'status' => $campaign->status->value,
                'statusLabel' => $campaign->status->label(),
                'scheduledAt' => MailingTime::format($campaign->scheduled_at, 'D MMMM YYYY HH:mm'),
                'startedAt' => MailingTime::format($campaign->started_at, 'D MMMM YYYY HH:mm'),
                'finishedAt' => MailingTime::format($campaign->finished_at, 'D MMMM YYYY HH:mm'),
                'recipients' => in_array($campaign->status, self::UNSTARTED, true) ? $recipients : array_sum($counts),
                'estimatedFinish' => $this->estimatedFinish($campaign, $counts),
                'transport' => $driver->reportsDelivery() ? __('mailing::campaigns.transport.provider') : __('mailing::campaigns.transport.postfix'),
            ],
            'counts' => array_map(
                fn (SendStatus $status): array => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'count' => $counts[$status->value] ?? 0,
                ],
                SendStatus::cases(),
            ),
            'preflight' => array_map(fn (PreflightItem $item): array => $item->toArray(), $items),
            'actions' => $this->actions($campaign, $canEdit, $canSend),
            'scheduleForm' => $canSend && $campaign->status === CampaignStatus::Draft && is_null($scheduleBlocked) ? ScheduleForm::make()
                ->action(route('mailing.campaigns.schedule', $campaign))
                ->method('POST') : null,
            'scheduleBlockedReason' => $campaign->status === CampaignStatus::Draft ? $scheduleBlocked : null,
            'testForm' => $canSend && $campaign->isEditable() ? TestSendForm::make()
                ->action(route('mailing.campaigns.test', $campaign))
                ->method('POST') : null,
            'sends' => in_array($campaign->status, self::UNSTARTED, true) ? null : SendTable::build($campaign->id),
            'breadcrumbs' => [
                ...$this->breadcrumbs(),
                [
                    'title' => $campaign->subject,
                    'href' => route('mailing.campaigns.show', $campaign),
                ],
            ],
        ]);
    }

    public function schedule(Request $request, Campaign $campaign, PreflightCheck $preflight, ChangeCampaignStatus $status): RedirectResponse
    {
        $validated = ScheduleForm::make()->validate($request);

        if (PreflightCheck::blocks($preflight->run($campaign))) {
            return to_route('mailing.campaigns.show', $campaign)->withErrors([
                'campaign' => __('mailing::campaigns.errors.preflight'),
            ]);
        }

        if ($this->recipientCount($campaign) === 0) {
            return to_route('mailing.campaigns.show', $campaign)->withErrors([
                'campaign' => __('mailing::campaigns.errors.no_recipients'),
            ]);
        }

        $at = is_string($validated['scheduled_at'] ?? null) && $validated['scheduled_at'] !== '' ? CarbonImmutable::parse($validated['scheduled_at']) : CarbonImmutable::now();

        return $this->transitioned($campaign, $status->schedule($campaign, $at->max(CarbonImmutable::now())), 'scheduled');
    }

    public function unschedule(Campaign $campaign, ChangeCampaignStatus $status): RedirectResponse
    {
        return $this->transitioned($campaign, $status->unschedule($campaign), 'unscheduled');
    }

    public function pause(Campaign $campaign, ChangeCampaignStatus $status): RedirectResponse
    {
        return $this->transitioned($campaign, $status->pause($campaign), 'paused');
    }

    public function resume(Campaign $campaign, ChangeCampaignStatus $status): RedirectResponse
    {
        return $this->transitioned($campaign, $status->resume($campaign), 'resumed');
    }

    public function cancel(Campaign $campaign, ChangeCampaignStatus $status): RedirectResponse
    {
        return $this->transitioned($campaign, $status->cancel($campaign), 'cancelled');
    }

    public function retry(Campaign $campaign, ChangeCampaignStatus $status): RedirectResponse
    {
        $retried = $status->retryFailed($campaign);

        $this->toast(trans_choice('mailing::campaigns.flash.retried', $retried, [
            'count' => $retried,
        ]));

        return to_route('mailing.campaigns.show', $campaign);
    }

    public function test(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = TestSendForm::make()->validate($request);
        $recipients = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $validated['recipients']) ?: [])));

        $validator = Validator::make([
            'recipients' => $recipients,
        ], [
            'recipients' => [
                'required',
                'array',
                sprintf('max:%d', self::MAX_TEST_RECIPIENTS),
            ],
            'recipients.*' => [
                'email:rfc',
            ],
        ]);

        if ($validator->fails()) {
            return to_route('mailing.campaigns.show', $campaign)->withErrors([
                'recipients' => __('mailing::campaigns.errors.test_recipients', [
                    'max' => self::MAX_TEST_RECIPIENTS,
                ]),
            ]);
        }

        $recipients = array_values(array_filter($recipients, fn (string $recipient): bool => ! Suppression::covers($recipient)));

        if ($recipients === []) {
            return to_route('mailing.campaigns.show', $campaign)->withErrors([
                'recipients' => __('mailing::campaigns.errors.test_suppressed'),
            ]);
        }

        $limiterKey = sprintf('mailing-test-send:%d', $this->userId($request) ?? 0);

        if (RateLimiter::remaining($limiterKey, self::TEST_SENDS_PER_HOUR) < count($recipients)) {
            return to_route('mailing.campaigns.show', $campaign)->withErrors([
                'recipients' => __('mailing::campaigns.errors.test_limit', [
                    'max' => self::TEST_SENDS_PER_HOUR,
                ]),
            ]);
        }

        $campaign->loadMissing('mailingList');

        foreach ($recipients as $recipient) {
            RateLimiter::hit($limiterKey, 3600);

            Mail::mailer($campaign->mailingList->mailer)
                ->to($recipient)
                ->send(new CampaignMail($campaign));
        }

        $this->toast(__('mailing::campaigns.flash.test_sent', [
            'recipients' => implode(', ', $recipients),
        ]));

        return to_route('mailing.campaigns.show', $campaign);
    }

    /**
     * @return list<array{label: string, url: string, method: string, confirm: string|null, disabledReason: string|null, variant: string}>
     */
    private function actions(Campaign $campaign, bool $canEdit, bool $canSend): array
    {
        $needsSend = $canSend ? null : (string) __('mailing::campaigns.errors.needs_send');
        $needsEdit = $canEdit ? null : (string) __('mailing::campaigns.errors.needs_edit');
        $actions = [];

        if ($campaign->isEditable()) {
            $actions[] = $this->action(__('mailing::common.edit'), route('mailing.campaigns.edit', $campaign), 'get', null, $needsEdit);
        }

        if ($campaign->status === CampaignStatus::Scheduled) {
            $actions[] = $this->action(__('mailing::campaigns.actions.unschedule'), route('mailing.campaigns.unschedule', $campaign), 'post', null, $needsSend);
        }

        if ($campaign->status === CampaignStatus::Sending) {
            $actions[] = $this->action(__('mailing::campaigns.actions.pause'), route('mailing.campaigns.pause', $campaign), 'post', null, $needsSend);
        }

        if ($campaign->status === CampaignStatus::Paused) {
            $actions[] = $this->action(__('mailing::campaigns.actions.resume'), route('mailing.campaigns.resume', $campaign), 'post', null, $needsSend);
        }

        if (in_array($campaign->status, self::RETRYABLE, true) && $this->hasRetryableSends($campaign)) {
            $actions[] = $this->action(__('mailing::campaigns.actions.retry'), route('mailing.campaigns.retry', $campaign), 'post', __('mailing::campaigns.actions.retry_confirm'), $needsSend);
        }

        if (in_array($campaign->status, self::CANCELLABLE, true)) {
            $actions[] = $this->action(__('mailing::campaigns.actions.cancel'), route('mailing.campaigns.cancel', $campaign), 'post', __('mailing::campaigns.actions.cancel_confirm'), $needsSend, 'destructive');
        }

        if ($campaign->status === CampaignStatus::Draft) {
            $actions[] = $this->action(__('mailing::common.delete'), route('mailing.campaigns.destroy', $campaign), 'delete', __('mailing::campaigns.actions.delete_confirm'), $needsEdit, 'destructive');
        }

        return $actions;
    }

    /**
     * @return array{label: string, url: string, method: string, confirm: string|null, disabledReason: string|null, variant: string}
     */
    private function action(string $label, string $url, string $method, ?string $confirm, ?string $disabledReason, string $variant = 'default'): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'method' => $method,
            'confirm' => $confirm,
            'disabledReason' => $disabledReason,
            'variant' => $variant,
        ];
    }

    private function hasRetryableSends(Campaign $campaign): bool
    {
        return $campaign->sends()
            ->where('status', SendStatus::Failed)
            ->whereNull('handed_over_at')
            ->exists();
    }

    /**
     * @return array<string, int>
     */
    private function counts(Campaign $campaign): array
    {
        return $campaign->sends()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function estimatedFinish(Campaign $campaign, array $counts): ?string
    {
        if ($campaign->status !== CampaignStatus::Sending) {
            return null;
        }

        $remaining = ($counts[SendStatus::Queued->value] ?? 0) + ($counts[SendStatus::Deferred->value] ?? 0) + ($counts[SendStatus::Sending->value] ?? 0);
        $perMinute = max(1, $campaign->mailingList->throttle_per_minute);
        $minutes = (int) ceil($remaining / $perMinute);
        $cap = $campaign->mailingList->daily_cap;

        if (! is_null($cap) && $cap > 0 && $remaining > $cap) {
            $minutes += (int) floor($remaining / $cap) * 24 * 60;
        }

        return MailingTime::format(CarbonImmutable::now()->addMinutes($minutes), 'D MMMM YYYY HH:mm');
    }

    private function recipientCount(Campaign $campaign): int
    {
        return $campaign->mailingList->subscriptions()
            ->where('status', SubscriptionStatus::Subscribed)
            ->whereNotNull('confirmed_at')
            ->count();
    }

    private function transitioned(Campaign $campaign, bool $changed, string $action): RedirectResponse
    {
        if (! $changed) {
            return $this->locked($campaign);
        }

        $this->toast(__(sprintf('mailing::campaigns.flash.%s', $action)));

        return to_route('mailing.campaigns.show', $campaign);
    }

    private function locked(Campaign $campaign): RedirectResponse
    {
        return to_route('mailing.campaigns.show', $campaign)->withErrors([
            'campaign' => __('mailing::campaigns.errors.status', [
                'status' => $campaign->status->label(),
            ]),
        ]);
    }

    private function composer(string $title, string $url, FormConfig $form): Response
    {
        return $this->form($title, __('mailing::campaigns.create.description'), [
            ...$this->breadcrumbs(),
            [
                'title' => $title,
                'href' => $url,
            ],
        ], route('mailing.campaigns.index'), $form);
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(): array
    {
        return [
            [
                'title' => __('mailing::campaigns.index.title'),
                'href' => route('mailing.campaigns.index'),
            ],
        ];
    }
}
