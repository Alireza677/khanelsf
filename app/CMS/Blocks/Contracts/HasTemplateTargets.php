<?php

namespace App\CMS\Blocks\Contracts;

/** Optional, additive editor metadata; never used by the runtime. */
interface HasTemplateTargets
{
    /**
     * Canonical keys from Template::TYPES. Null allows every target; [] allows none.
     *
     * @return array<string>|null
     */
    public function templateTargets(): ?array;
}
