<?php

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\GuestEntries\Http\Requests\GuestEntryRequest;

class SectionResolutionFailed
{
    public ?Section $section = null;

    public function __construct(GuestEntryRequest $request) {}
}
