<?php

namespace App\CMS\Blocks\Project;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use Filament\Forms;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Component;

abstract class AbstractProjectBlock extends AbstractBlock implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function version(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate('default', 'پیش‌فرض', "partials.blocks.{$this->key()}"),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function capabilities(): array
    {
        return ['project_context', 'dynamic_data'];
    }

    public function filamentBlock(string $context): Block
    {
        return parent::filamentBlock($context)
            ->label('پروژه: '.$this->label())
            ->columns(2);
    }

    public function filamentSchema(string $context): array
    {
        if ($context !== HeroBlock::CONTEXT_TEMPLATE) {
            throw new \InvalidArgumentException("Project block [{$this->key()}] is only available in the template editor.");
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default(self::SCHEMA_VERSION),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            ...$this->schema(),
            HeadingLevel::field(default: $this->defaultHeadingLevel()),
        ];
    }

    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
            'template' => ($data['template'] ?? null) === $this->defaultTemplate()
                ? $data['template']
                : $this->defaultTemplate(),
            'content' => $this->normalizeContent($content),
            'settings' => [
                ...$this->normalizeSettings($settings),
                'heading_tag' => HeadingLevel::normalize($settings['heading_tag'] ?? null, $this->defaultHeadingLevel()),
            ],
        ];
    }

    /** @return array<Component> */
    abstract protected function schema(): array;

    abstract protected function normalizeContent(array $content): array;

    abstract protected function normalizeSettings(array $settings): array;

    protected function defaultHeadingLevel(): string
    {
        return HeadingLevel::DEFAULT;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function boolean(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    protected function integerBetween(mixed $value, int $minimum, int $maximum, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($minimum, min((int) $value, $maximum));
    }
}
