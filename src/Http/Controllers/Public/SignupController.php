<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use J87NL\Mailing\Actions\RequestSubscription;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Signup\SignupForm;
use J87NL\Mailing\Signup\SignupGuard;
use J87NL\Mailing\Signup\SignupLimiter;

class SignupController extends Controller
{
    public function __construct(
        private readonly SignupGuard $guard,
        private readonly SignupLimiter $limiter,
        private readonly SignupForm $form,
    ) {}

    public function show(Request $request, MailingList $list): Response
    {
        abort_unless($list->is_public, 404);

        return $this->page($request, $list);
    }

    public function store(Request $request, MailingList $list, RequestSubscription $requestSubscription): Response|RedirectResponse
    {
        abort_unless($list->is_public, 404);

        $returnTo = $request->input('return_to');
        $returnTo = is_string($returnTo) && SignupForm::isLocalPath($returnTo) ? $returnTo : null;

        if ($this->limiter->tooManyFromIp($request)) {
            return $this->page($request, $list, new MessageBag([
                'form' => __('mailing::signup.too_many'),
            ]), 429)->header('Retry-After', (string) $this->limiter->retryAfterForIp($request));
        }

        if (! $this->guard->looksHuman($request, $list->id)) {
            $this->limiter->hitIp($request);

            return redirect(SignupForm::successUrl($list, $returnTo), 303);
        }

        $validator = Validator::make($request->only(['email', 'name']), [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'name' => ['nullable', 'string', 'max:120'],
        ], [], [
            'email' => __('mailing::signup.email'),
            'name' => __('mailing::signup.name'),
        ]);

        if ($validator->fails()) {
            return $this->page($request, $list, $validator->errors(), 422);
        }

        $email = (string) $validator->validated()['email'];
        $name = $validator->validated()['name'] ?? null;

        $this->limiter->hitIp($request);

        if (! $this->limiter->claimAddress($email)) {
            return redirect(SignupForm::successUrl($list, $returnTo), 303);
        }

        if (! $this->limiter->claimOverall()) {
            return $this->page($request, $list, new MessageBag([
                'form' => __('mailing::signup.too_many'),
            ]), 429)->header('Retry-After', (string) $this->limiter->retryAfterOverall());
        }

        $requestSubscription->handle(
            $list,
            $email,
            is_string($name) ? $name : null,
            $request->ip(),
            sprintf('signup: %s', $returnTo ?? route('mailing.subscribe.show', $list, false)),
        );

        return redirect(SignupForm::successUrl($list, $returnTo), 303);
    }

    private function page(Request $request, MailingList $list, ?MessageBag $errors = null, int $status = 200): Response
    {
        return response()->view('mailing::public.subscribe', [
            'list' => $list,
            'form' => $this->form->data(
                $list,
                $request,
                null,
                $errors,
                [
                    'email' => (string) $request->input('email', ''),
                    'name' => (string) $request->input('name', ''),
                ],
                '',
                (string) $list->description,
            ),
        ], $status);
    }
}
