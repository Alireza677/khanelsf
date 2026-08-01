<?php

namespace App\CMS\Actions\Data;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;

final readonly class ActionDestination
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public ?string $type,
        public ?int $referenceId = null,
        public ?string $value = null,
        public ?string $display = null,
        public bool $openInNewTab = false,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {}

    public static function fromArray(array $data): self
    {
        return (new ActionDestinationNormalizer)->normalize($data);
    }

    public function coreType(): ?CoreActionType
    {
        return CoreActionType::fromInput($this->type);
    }

    /**
     * Compact, deterministic JSON storage shape.
     *
     * @return array<string, int|string|bool>
     */
    public function toArray(): array
    {
        $data = [
            'schema_version' => $this->schemaVersion,
        ];

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        $type = $this->coreType();

        if ($type?->usesReference() && $this->referenceId !== null) {
            $data['reference_id'] = $this->referenceId;
        }

        if ($type?->usesValue() && $this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($type === CoreActionType::Form && $this->display !== null) {
            $data['display'] = $this->display;
        }

        $data['open_in_new_tab'] = $this->openInNewTab;

        return $data;
    }

    /**
     * Fixed internal DTO shape. This is not the persisted JSON contract.
     *
     * @return array<string, int|string|bool|null>
     */
    public function toInternalArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'type' => $this->type,
            'reference_id' => $this->referenceId,
            'value' => $this->value,
            'display' => $this->display,
            'open_in_new_tab' => $this->openInNewTab,
        ];
    }
}
