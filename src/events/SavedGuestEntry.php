<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Dispatched after a guest entry is saved, or after a spam submission is discarded.
 */
class SavedGuestEntry
{
    public function __construct(
        public Entry $entry,
        public bool $isSpam = false,
    ) {}
}
