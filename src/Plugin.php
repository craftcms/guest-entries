<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries;

use Craft;
use craft\elements\User;
use craft\guestentries\models\Settings;

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
    public $hasCpSettings = true;

    // Protected Methods
    // =========================================================================

    /**
     * @return mixed
     */
    protected function settingsHtml(): string
    {
        $editableSections = [];
        $allSections = Craft::$app->sections->getAllSections();

        foreach ($allSections as $section) {
            // No sense in doing this for singles.
            if ($section->type !== 'single') {
                $editableSections[$section->handle] = ['section' => $section];
            }
        }

        // Let's construct the potential default users for each section.
        foreach ($editableSections as $handle => $value) {

            // If we're running on Client Edition, add both accounts.
            if (Craft::$app->getEdition() === Craft::Client) {
                $defaultAuthorOptionQuery = User::find();
                $authorOptions = $defaultAuthorOptionQuery->all();
            } else if (Craft::$app->getEdition() === Craft::Pro) {
                $defaultAuthorOptionQuery = User::find();
                $defaultAuthorOptionQuery->can = 'createEntries:'.$value['section']->id;
                $authorOptions = $defaultAuthorOptionQuery->all();
            } else {
                // Personal Edition.
                $authorOptions = [Craft::$app->getUser()];
            }

            foreach ($authorOptions as $key => $authorOption) {
                $authorLabel = $authorOption->username;
                $authorFullName = $authorOption->getFullName();

                if ($authorFullName) {
                    $authorLabel .= ' ('.$authorFullName.')';
                }

                $authorOptions[$key] = ['label' => $authorLabel, 'value' => $authorOption->id];
            }

            array_unshift($authorOptions, ['label' => 'Don’t Allow', 'value' => 'none']);

            /** @noinspection SlowArrayOperationsInLoopInspection */
            $editableSections[$handle] = array_merge($editableSections[$handle], ['authorOptions' => $authorOptions]);
        }

        return Craft::$app->getView()->renderTemplate('guest-entries/_settings', [
            'settings' => $this->getSettings(),
            'editableSections' => $editableSections,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}

