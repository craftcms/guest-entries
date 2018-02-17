<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\models;

use craft\base\Model;

class SectionSettings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int|null The section ID
     */
    public $sectionId;

    /**
     * @var bool Whether the section allows guest entry submissions
     */
    public $allowGuestSubmissions = false;

    /**
     * @var bool Whether guest entry submissions should be enabled by default
     */
    public $enabledByDefault = false;

    /**
     * @var bool[] Whether guest entry submissions should be validated
     */
    public $runValidation = false;

    /**
     * @var int|null The ID of the author that guest entries should be attributed to
     */
    public $authorId;
}
