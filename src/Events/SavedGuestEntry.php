<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Dispatched after a guest entry was saved, or after a submission flagged as spam was discarded.
 */
class SavedGuestEntry
{
    public function __construct(
        public Entry $entry,
        public bool $isSpam = false,
    ) {}
}
