<?php

namespace craft\guestentries;


use Craft;
use craft\elements\User;
use craft\guestentries\models\Settings;


/**
 * Class GuestEntriesPlugin
 *
 * @package Craft
 */
class Plugin extends \craft\base\Plugin
{
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
    }


    /**
     * @return mixed
     */
    protected function settingsHtml()
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
                // 2.x on Personal Edition.
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

            $editableSections[$handle] = array_merge($editableSections[$handle], ['authorOptions' => $authorOptions]);
        }

        return Craft::$app->getView()->renderTemplate('guest-entries/_settings', [
            'settings' => $this->getSettings(),
            'editableSections' => $editableSections,
        ]);
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }


}

