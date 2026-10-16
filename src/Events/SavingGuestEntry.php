<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

/**
 * Dispatched before a guest entry is saved.
 *
 * Set `$isValid` to `false` to reject the submission, or `$isSpam` to `true`
 * to silently discard it while still reporting success to the submitter.
 */
class SavingGuestEntry
{
    use ValidatableEvent;

    /** Whether the submission appears to be spam. */
    public bool $isSpam = false;

    public function __construct(public Entry $entry) {}
}
