<?php

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;

class SavedGuestEntry
{
    public function __construct(public Entry $entry) {}
}
