<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;
use TranquilTools\FormBuilder\FormConfig;

abstract class Controller extends BaseController
{
    protected function toast(string $message): void
    {
        Inertia::flash((string) config('mailing.toast_flash_key', 'toast'), [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    protected function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    protected function page(string $key): string
    {
        return (string) config(sprintf('mailing.pages.%s', $key));
    }

    /**
     * @param  list<array{title: string, href: string}>  $breadcrumbs
     */
    protected function form(string $title, ?string $description, array $breadcrumbs, string $backUrl, FormConfig $form): Response
    {
        return Inertia::render($this->page('form'), [
            'title' => $title,
            'description' => $description,
            'breadcrumbs' => $breadcrumbs,
            'backUrl' => $backUrl,
            'form' => $form,
        ]);
    }
}
