<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\GuestEntries\Http\Requests\GuestEntryRequest;

/**
 * Dispatched when the submitted section identifiers don’t match a section that allows guest submissions.
 */
class SectionResolutionFailed
{
    /**
     * @var Section|null Set this to create the entry in a section, even if it doesn’t allow guest submissions.
     */
    public ?Section $section = null;

    public function __construct(public GuestEntryRequest $request) {}
}
