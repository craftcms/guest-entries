<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Dispatched when a guest entry submission was rejected or could not be saved.
 */
class GuestEntrySaveFailed
{
    public function __construct(public Entry $entry) {}
}
