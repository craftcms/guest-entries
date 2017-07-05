<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * The name of the variable to return to the template in case there is a validation error.
     *
     * @var string
     */
    public $entryVariable = 'entry';

    /**
     * Whether to allow front-end guest entry submissions or not.
     *
     * @var bool
     */
    public $allowGuestSubmissions = false;

    /**
     * The list of default authors for a given section.
     *
     * @var string[]
     */
    public $defaultAuthors = [];

    /**
     * Whether guest entry submissions are enabled by default or not.
     *
     * @var bool[]
     */
    public $enabledByDefault = [];

    /**
     * Whether to run validation on guest entry submissions or not.
     *
     * @var bool[]
     */
    public $validateEntry = [];



}
