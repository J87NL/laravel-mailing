<?php

declare(strict_types=1);

namespace J87NL\Mailing\Attachments;

use J87NL\Mailing\Models\CampaignAttachment;
use RuntimeException;

class MissingAttachmentFile extends RuntimeException
{
    public static function for(CampaignAttachment $attachment): self
    {
        return new self(sprintf('The file of attachment "%s" (id %d) is missing: %s on disk "%s".', $attachment->name, $attachment->id, $attachment->path, $attachment->disk));
    }
}
