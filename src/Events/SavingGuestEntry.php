<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace CraftCms\GuestEntries\Events;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class SavingGuestEntry
{
    use ValidatableEvent;

    /**
     * @var bool Whether the message appears to be spam. Set to `true` to suppress notifications.
     */
    public bool $isSpam = false;

    public function __construct(public Entry $entry)
    {}
}
