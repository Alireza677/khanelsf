<?php

namespace App\CMS\Templates\Recipes\Contracts;

interface TemplateRecipe
{
    public function key(): string;

    public function label(): string;

    public function version(): int;

    public function targetType(): string;

    public function description(): string;

    /**
     * @return array{
     *     blocks: array<string, array{min_version: int, capabilities: array<string>}>
     * }
     */
    public function compatibility(): array;

    /**
     * @return array<int, array{
     *     type: string,
     *     data: array{
     *         block_id: null,
     *         schema_version: int,
     *         template: string,
     *         content: array,
     *         settings: array
     *     }
     * }>
     */
    public function blocks(): array;
}
