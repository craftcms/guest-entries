<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries\events;

use craft\elements\Entry;
use yii\base\Event;

/**
 * SendEvent class
 */
class SendEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether the guest entry submission is valid.
     */
    public $isValid = true;

    /**
     * @var bool Whether we should pretend the submission went through, but it really didn't.
     */
    public $fakeIt = false;

    /**
     * @var Entry The guest entry submission.
     */
    public $entry;

    /**
     * @var bool Whether this submission was faked or not.
     */
    public $faked;

}


