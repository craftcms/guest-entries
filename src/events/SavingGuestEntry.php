<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\events;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

/**
 * Dispatched before a guest entry is saved.
 *
 * Set `$isValid` to `false` to stop the entry from being saved, or `$isSpam` to `true` to silently discard it.
 */
class SavingGuestEntry
{
    use ValidatableEvent;

    public bool $isSpam = false;

    public function __construct(public Entry $entry) {}
}
