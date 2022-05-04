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
     * @var string|null The section UID
     */
    public $sectionUid;

    /**
     * @var bool Whether the section allows guest entry submissions
     */
    public $allowGuestSubmissions = false;

    /**
     * @var bool Whether guest entry submissions should be enabled by default
     */
    public $enabledByDefault = false;

    /**
     * @var bool Whether guest entry submissions should be validated
     */
    public $runValidation = false;

    /**
     * @var string|null The UID of the author that guest entries should be attributed to
     */
    public $authorUid;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['authorUid'], 'required'],
        ];
    }
}
