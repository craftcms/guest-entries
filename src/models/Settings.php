<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
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
     * @var bool Whether `guest-entries/save` requests should be protected against CSRF attacks.
     * Note this will be ignored if CSRF protection has been disabled at the system level.
     */
    public $enableCsrfProtection = true;

    /**
     * @var string The name of the variable to return to the template in case there is a validation error.
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
        $names = parent::attributes();
        $names[] = 'sections';
        return $names;
    }
}
