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
     * @param string $uid
     * @return SectionSettings
     */
    public function getSection(string $uid): SectionSettings
    {
        return $this->_sections[$uid] ?? new SectionSettings(['sectionUid' => $uid]);
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
            // Watch out for old config data that's not updated yet
            if (!isset($config['sectionUid'])) {
                continue;
            }

            // Ignore sections that don't allow guest submissions
            if ($config['allowGuestSubmissions']) {
                $this->_sections[$config['sectionUid']] = new SectionSettings($config);
            } else {
                unset($this->_sections[$config['sectionUid']]);
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

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['sections'], 'validateSections'],
        ];
    }

    public function validateSections()
    {
        foreach ($this->_sections as $uid => $section) {
            if ($section->allowGuestSubmissions && !$section->validate()) {
                $this->addModelErrors($section, "sections[$uid]");
            }
        }
    }
}
