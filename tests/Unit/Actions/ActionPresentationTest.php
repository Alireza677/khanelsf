<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Presentation\ActionPresentation;
use PHPUnit\Framework\TestCase;

class ActionPresentationTest extends TestCase
{
    public function test_only_exact_custom_url_placeholder_prevents_default_navigation(): void
    {
        $presentation = new ActionPresentation;

        $placeholder = $presentation->present(ResolvedAction::resolved('custom_url', '#'));
        $normalUrl = $presentation->present(ResolvedAction::resolved('custom_url', '/contact'));
        $normalAnchor = $presentation->present(ResolvedAction::resolved('anchor', '#contact'));

        $this->assertSame('#', $placeholder['href']);
        $this->assertTrue($placeholder['prevent_default']);
        $this->assertFalse($normalUrl['prevent_default']);
        $this->assertFalse($normalAnchor['prevent_default']);
    }
}
