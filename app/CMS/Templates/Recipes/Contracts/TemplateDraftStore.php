<?php

namespace App\CMS\Templates\Recipes\Contracts;

use App\Models\Template;

interface TemplateDraftStore
{
    public function persist(Template $template): Template;
}
