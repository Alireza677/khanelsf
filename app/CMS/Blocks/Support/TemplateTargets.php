<?php

namespace App\CMS\Blocks\Support;

/** Editor groups of existing canonical template targets, not new target types. */
final class TemplateTargets
{
    public const COLLECTIONS = [
        'blog_index', 'post_category',
        'projects_index', 'project_category',
        'shop_index', 'product_category',
        'service_index',
        'galleries_index', 'gallery_category',
    ];

    public const ARCHIVES = [...self::COLLECTIONS, 'project_discovery_index'];

    // The generic single blocks consume the legacy model/title/content contract.
    // Service detail has its own block definitions and content contract.
    public const GENERIC_SINGLE = [
        'post_single', 'project_single', 'product_single', 'gallery_single',
    ];
}
