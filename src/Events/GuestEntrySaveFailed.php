<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Dispatched when a guest entry is rejected by a {@see GuestEntrySaving} listener, or fails to save.
 */
class GuestEntrySaveFailed
{
    public function __construct(public Entry $entry) {}
}
