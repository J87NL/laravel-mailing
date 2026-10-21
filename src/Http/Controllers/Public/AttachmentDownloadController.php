<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use J87NL\Mailing\Models\CampaignAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    public function __invoke(string $token): StreamedResponse
    {
        $attachment = CampaignAttachment::query()
            ->where('token', $token)
            ->first();

        if (is_null($attachment) || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
