<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use J87NL\Mailing\Actions\ConfirmSubscription;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Subscription;

class ConfirmationController extends Controller
{
    public function show(Request $request, string $subscription): Response
    {
        $model = $this->find($subscription);

        if (is_null($model) || ! $request->hasValidSignature()) {
            return $this->view('expired', null, 403);
        }

        if ($model->status === SubscriptionStatus::Subscribed) {
            return $this->view('confirmed', $model);
        }

        if ($model->status !== SubscriptionStatus::Pending) {
            return $this->view('expired', $model, 410);
        }

        return $this->view('confirm', $model, 200, [
            'action' => $request->fullUrl(),
            'autoSubmitAttribute' => ConfirmationScriptController::FORM_ATTRIBUTE,
        ]);
    }

    public function store(Request $request, string $subscription, ConfirmSubscription $confirmSubscription): Response
    {
        $model = $this->find($subscription);

        if (is_null($model) || ! $request->hasValidSignature()) {
            return $this->view('expired', null, 403);
        }

        if ($model->status === SubscriptionStatus::Subscribed || $confirmSubscription->handle($model, $request->ip())) {
            return $this->view('confirmed', $model->refresh());
        }

        if ($model->refresh()->status === SubscriptionStatus::Subscribed) {
            return $this->view('confirmed', $model);
        }

        return $this->view('expired', $model, 410);
    }

    private function find(string $id): ?Subscription
    {
        if (! ctype_digit($id)) {
            return null;
        }

        return Subscription::query()
            ->with('mailingList')
            ->find((int) $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(string $state, ?Subscription $subscription, int $status = 200, array $data = []): Response
    {
        return response()->view('mailing::public.confirm', [
            'state' => $state,
            'listName' => $subscription?->mailingList->name,
            'signupUrl' => is_null($subscription) || ! $subscription->mailingList->is_public ? null : route('mailing.subscribe.show', $subscription->mailingList),
            ...$data,
        ], $status);
    }
}
