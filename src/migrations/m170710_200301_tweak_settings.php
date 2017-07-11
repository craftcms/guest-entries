<?php

namespace craft\guestentries\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

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
        // Get the old settings
        $oldSettings = (new Query())
            ->select(['settings'])
            ->from(['{{%plugins}}'])
            ->where(['handle' => 'guest-entries'])
            ->scalar();

        // If no settings were saved yet, we're done
        if (!$oldSettings) {
            return;
        }

        $oldSettings = Json::decode($oldSettings);
        $sections = [];

        // allowGuestSubmissions is no longer a thing, but respect its previous value
        if ($oldSettings['allowGuestSubmissions'] && !empty($oldSettings['defaultAuthors'])) {
            // Update the settings for any sections that allowed guest submissions
            $sectionsService = Craft::$app->getSections();
            foreach ($oldSettings['defaultAuthors'] as $sectionHandle => $authorId) {
                if ($authorId !== 'none' && ($section = $sectionsService->getSectionByHandle($sectionHandle)) !== null) {
                    $sections[] = [
                        'sectionId' => $section->id,
                        'allowGuestSubmissions' => true,
                        'authorId' => (int) $authorId,
                        'enabledByDefault' => (bool)($oldSettings['enabledByDefault'][$sectionHandle] ?? false),
                        'runValidation' => (bool)($oldSettings['validateEntry'][$sectionHandle] ?? false),
                    ];
                }
            }
        }

        // Update the settings
        $newSettings = ['sections' => $sections];
        $this->update('{{%plugins}}', ['settings' => Json::encode($newSettings)], ['handle' => 'guest-entries']);
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
