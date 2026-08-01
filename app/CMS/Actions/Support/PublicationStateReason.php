<?php

namespace App\CMS\Actions\Support;

use App\CMS\Actions\Enums\ActionResolutionReason;
use Carbon\CarbonInterface;

final class PublicationStateReason
{
    public static function for(
        ?string $status,
        mixed $publishedAt,
    ): ActionResolutionReason {
        return match ($status) {
            'inactive' => ActionResolutionReason::EntityInactive,
            'archived' => ActionResolutionReason::EntityArchived,
            'published' => self::isFuture($publishedAt)
                ? ActionResolutionReason::EntityScheduled
                : ActionResolutionReason::EntityUnpublished,
            default => ActionResolutionReason::EntityUnpublished,
        };
    }

    private static function isFuture(mixed $publishedAt): bool
    {
        return $publishedAt instanceof CarbonInterface && $publishedAt->isFuture();
    }
}
