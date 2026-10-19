<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use J87NL\Mailing\Actions\SuppressAddress;
use J87NL\Mailing\Actions\Unsubscribe;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\Subscription;

class UnsubscribeController extends Controller
{
    public const string SCOPE_LIST = 'list';

    public const string SCOPE_ALL = 'all';

    public function show(string $token): Response
    {
        $subscription = $this->find($token);

        return response()->view('mailing::public.unsubscribe', [
            'state' => $subscription->isActive() ? 'choose' : 'done',
            'scope' => self::SCOPE_LIST,
            'listName' => $subscription->mailingList->name,
            'action' => route('mailing.unsubscribe.store', [
                'token' => $token,
            ]),
        ]);
    }

    public function store(Request $request, string $token, Unsubscribe $unsubscribe, SuppressAddress $suppressAddress): Response
    {
        $subscription = $this->find($token);
        $scope = $request->input('scope') === self::SCOPE_ALL ? self::SCOPE_ALL : self::SCOPE_LIST;
        $oneClick = $request->input('List-Unsubscribe') === 'One-Click';
        $source = $oneClick ? 'one-click' : 'unsubscribe page';

        if ($scope === self::SCOPE_ALL && ! $oneClick) {
            $suppressAddress->handle($subscription->subscriber->email, SuppressionReason::UnsubscribeAll, $source, null, null, $request->ip());
        }

        if ($scope === self::SCOPE_LIST || $oneClick) {
            $unsubscribe->handle($subscription, $source, $request->ip());
        }

        return response()->view('mailing::public.unsubscribe', [
            'state' => 'done',
            'scope' => $oneClick ? self::SCOPE_LIST : $scope,
            'listName' => $subscription->mailingList->name,
            'action' => null,
        ]);
    }

    private function find(string $token): Subscription
    {
        $subscription = Subscription::query()
            ->with([
                'subscriber',
                'mailingList',
            ])
            ->where('unsubscribe_token', $token)
            ->first();

        if (is_null($subscription)) {
            abort(404);
        }

        return $subscription;
    }
}
