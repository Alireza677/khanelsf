<?php

namespace App\CMS\Collections\Video;

use App\CMS\Collections\Data\CollectionPresentation;

final readonly class VideoArchivePresentation
{
    // Playback stays in the domain presentation; the generic Collection DTO remains unchanged.
    public function __construct(public CollectionPresentation $collection, public array $grid) {}
}
