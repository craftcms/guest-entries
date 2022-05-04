<?php

namespace craft\guestentries\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;

/**
 * m181105_160000_craft_31_compatibility migration.
 */
class m181105_160000_craft_31_compatibility extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.guest-entries.schemaVersion', true);
        if (version_compare($schemaVersion, '2.1.0', '>=')) {
            return;
        }

        $sectionSettings = $projectConfig->get('plugins.guest-entries.settings.sections');
        $newSectionSettings = [];

        if (is_array($sectionSettings)) {
            $sectionMap = Db::uidsByIds('{{%sections}}', array_keys($sectionSettings));

            foreach ($sectionSettings as $sectionId => $settings) {
                if (isset($sectionMap[$sectionId]) && $uid = $sectionMap[$sectionId]) {
                    $settings['sectionUid'] = $uid;
                    $settings['authorUid'] = Db::uidById('{{%users}}', $settings['authorId']);
                    unset($settings['sectionId'], $settings['authorId']);
                    $newSectionSettings[$uid] = $settings;
                }
            }

            $sectionSettings = $projectConfig->set('plugins.guest-entries.settings.sections', $newSectionSettings);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181105_160000_craft_31_compatibility cannot be reverted.\n";
        return false;
    }
}
