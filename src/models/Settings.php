<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries\models;

use craft\base\Model;

/**
 * Settings represents the global settings for Guest Entries.
 *
 * @property SectionSettings[] $sections The section settings
 */
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
     * @var SectionSettings[]
     */
    private $_sections = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns a section’s settings by its ID.
     *
     * @param int $id
     *
     * @return SectionSettings
     */
    public function getSection(int $id): SectionSettings
    {
        return $this->_sections[$id] ?? new SectionSettings(['sectionId' => $id]);
    }

    /**
     * Returns the section settings.
     *
     * @return SectionSettings[]
     */
    public function getSections(): array
    {
        return $this->_sections;
    }

    /**
     * Sets the section settings.
     *
     * @param array $sections
     */
    public function setSections(array $sections)
    {
        foreach ($sections as $key => $config) {
            // Ignore sections that don't allow guest submissions
            if ($config['allowGuestSubmissions']) {
                $this->_sections[$config['sectionId']] = new SectionSettings($config);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $attributes = parent::attributes();
        $attributes[] = 'sections';
        return $attributes;
    }
}
