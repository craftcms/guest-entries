<?php

namespace craft\guestentries\migrations;

use Craft;
use craft\db\Migration;

/**
 * m170710_200301_tweak_settings migration.
 */
class m170710_200301_tweak_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.guest-entries.schemaVersion', true);
        if (version_compare($schemaVersion, '2.1.0', '>=')) {
            return;
        }

        $oldSettings = $projectConfig->get('plugins.guest-entries.settings');

        // If no settings were saved yet, we're done
        if (!$oldSettings) {
            return;
        }

        $sections = [];

        // allowGuestSubmissions is no longer a thing, but respect its previous value
        if ($oldSettings['allowGuestSubmissions'] && !empty($oldSettings['defaultAuthors'])) {
            // Update the settings for any sections that allowed guest submissions
            $sectionsService = Craft::$app->getSections();
            foreach ($oldSettings['defaultAuthors'] as $sectionHandle => $authorId) {
                if ($authorId !== 'none' && ($section = $sectionsService->getSectionByHandle($sectionHandle)) !== null) {
                    $sections[$section->id] = [
                        'sectionId' => $section->id,
                        'allowGuestSubmissions' => true,
                        'authorId' => (int)$authorId,
                        'enabledByDefault' => (bool)($oldSettings['enabledByDefault'][$sectionHandle] ?? false),
                        'runValidation' => (bool)($oldSettings['validateEntry'][$sectionHandle] ?? false),
                    ];
                }
            }
        }

        // Update the settings
        $projectConfig->set('plugins.guest-entries.settings', ['sections' => $sections]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m170710_200301_tweak_settings cannot be reverted.\n";
        return false;
    }
}
