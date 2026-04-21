<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\events;

use craft\elements\Entry;
use craft\events\CancelableEvent;

/**
 * SaveEvent class
 */
class SaveEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var Entry The guest entry submission
     */
    public $entry;

    /**
     * @var bool Whether the message appears to be spam, and should not really be sent
     */
    public $isSpam = false;
}
