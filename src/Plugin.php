<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries;

use Craft;
use craft\elements\User;
use craft\guestentries\models\Settings;
use craft\models\Section;

/**
 * Class Plugin
 *
 * @property Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends \craft\base\Plugin
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $schemaVersion = '2.0.0';

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->settings->setSections();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return mixed
     */
    protected function settingsHtml(): string
    {
        $sections = [];
        $craftEdition = Craft::$app->getEdition();

        if ($craftEdition !== Craft::Pro) {
            $authors = [Craft::$app->getUser()->getIdentity()];
            $authorOptions = $this->_formatAuthorOptions($authors);
        }

        foreach (Craft::$app->sections->getAllSections() as $section) {
            // No sense in doing this for singles.
            if ($section->type === Section::TYPE_SINGLE) {
                continue;
            }

            $sections[] = [
                'section' => $section,
                'authorOptions' => $authorOptions ?? $this->_getSectionAuthorOptions($section)
            ];
        }

        return Craft::$app->getView()->renderTemplate('guest-entries/_settings', [
            'settings' => $this->getSettings(),
            'sections' => $sections,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the authors that can publish to the given section.
     *
     * @param Section $section
     * @return User[]
     */
    private function _getSectionAuthorOptions(Section $section): array
    {
        $authors = User::find()
            ->can('createEntries:'.$section->id)
            ->all();
        return $this->_formatAuthorOptions($authors);
    }


    /**
     * Formats the given list of authors for a select input.
     *
     * @param User[] $authors
     * @return array
     */
    private function _formatAuthorOptions(array $authors): array
    {
        $options = [];

        foreach ($authors as $author) {
            $authorLabel = $author->username;

            if ($fullName = $author->getFullName()) {
                $authorLabel .= ' ('.$fullName.')';
            }

            $options[] = ['label' => $authorLabel, 'value' => $author->id];
        }

        return $options;
    }
}
