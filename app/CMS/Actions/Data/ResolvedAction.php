<?php

namespace App\CMS\Actions\Data;

use App\CMS\Actions\Enums\ActionResolutionStatus;

final readonly class ResolvedAction
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    private function __construct(
        public ActionResolutionStatus $status,
        public ?string $actionType,
        public ?string $url,
        public ?string $target,
        public ?string $rel,
        public ?string $reason,
        public array $metadata,
    ) {}

    /** @param array<string, scalar|null> $metadata */
    public static function resolved(
        string $actionType,
        string $url,
        bool $openInNewTab = false,
        array $metadata = [],
    ): self {
        return new self(
            ActionResolutionStatus::Resolved,
            $actionType,
            $url,
            $openInNewTab ? '_blank' : null,
            $openInNewTab ? 'noopener noreferrer' : null,
            null,
            $metadata,
        );
    }

    /** @param array<string, scalar|null> $metadata */
    public static function unresolved(string $actionType, string $reason, array $metadata = []): self
    {
        return self::failure(ActionResolutionStatus::Unresolved, $actionType, $reason, $metadata);
    }

    /** @param array<string, scalar|null> $metadata */
    public static function invalid(?string $actionType, string $reason, array $metadata = []): self
    {
        return self::failure(ActionResolutionStatus::Invalid, $actionType, $reason, $metadata);
    }

    /** @param array<string, scalar|null> $metadata */
    public static function unavailable(string $actionType, string $reason, array $metadata = []): self
    {
        return self::failure(ActionResolutionStatus::Unavailable, $actionType, $reason, $metadata);
    }

    public function shouldRender(): bool
    {
        return $this->status === ActionResolutionStatus::Resolved
            && $this->url !== null
            && $this->url !== '';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'action_type' => $this->actionType,
            'url' => $this->url,
            'target' => $this->target,
            'rel' => $this->rel,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string, scalar|null> $metadata */
    private static function failure(
        ActionResolutionStatus $status,
        ?string $actionType,
        string $reason,
        array $metadata,
    ): self {
        return new self(
            $status,
            $actionType,
            null,
            null,
            null,
            self::safeReason($reason),
            $metadata,
        );
    }

    private static function safeReason(string $reason): string
    {
        $reason = trim($reason);

        return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $reason) === 1
            ? $reason
            : 'resolution_failed';
    }
}
