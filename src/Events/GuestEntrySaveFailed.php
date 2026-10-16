<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Dispatched when a guest entry could not be saved, or a listener rejected it.
 */
class GuestEntrySaveFailed
{
    public function __construct(public Entry $entry) {}
}
