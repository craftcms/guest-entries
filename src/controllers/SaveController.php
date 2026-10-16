<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\controllers;

use craft\guestentries\events\GuestEntrySaveFailed;
use craft\guestentries\events\SavedGuestEntry;
use craft\guestentries\events\SavingGuestEntry;
use craft\guestentries\models\SectionSettings;
use craft\guestentries\models\Settings;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Support\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Saves entries submitted by guests from the front end.
 */
class SaveController
{
    use RespondsWithFlash;

    public function __invoke(
        Request $request,
        Sections $sections,
        Elements $elements,
        Settings $settings,
    ): Response {
        // Only allow front end requests
        abort_if($request->isCpRequest(), 404);

        // Make sure the section exists
        $sectionId = $request->input('sectionId');
        $sectionUid = $request->input('sectionUid');
        $sectionHandle = $request->input('sectionHandle');

        if ($sectionHandle) {
            $section = $sections->getSectionByHandle($sectionHandle);
        } elseif ($sectionUid) {
            $section = $sections->getSectionByUid($sectionUid);
        } else {
            $section = $sections->getSectionById((int)$sectionId);
        }

        abort_if(!$section, 400, 'Section does not exist.');

        // Make sure the section allows guest submissions
        $sectionSettings = $settings->getSection($section->uid);

        abort_if(!$sectionSettings->allowGuestSubmissions, 400, "Section $section->handle does not allow guest submissions.");

        // Populate the entry
        $entry = $this->populateEntry($request, $section, $sectionSettings);

        // Give listeners a chance to veto the entry or flag it as spam
        event($saving = new SavingGuestEntry($entry));

        if (!$saving->isValid) {
            return $this->returnError($settings, $entry);
        }

        if ($saving->isSpam) {
            Log::info('Guest entry submission suspected to be spam.');
            // Pretend it worked.
            return $this->returnSuccess($entry, true);
        }

        // Try to save it
        if ($sectionSettings->runValidation) {
            $entry->ruleset->useScenario(ElementRules::SCENARIO_LIVE);
        }

        if (!$elements->saveElement($entry)) {
            return $this->returnError($settings, $entry);
        }

        return $this->returnSuccess($entry);
    }

    private function returnSuccess(Entry $entry, bool $isSpam = false): Response
    {
        event(new SavedGuestEntry($entry, $isSpam));

        return $this->asModelSuccess(
            $entry,
            t('Entry saved.', category: 'guest-entries'),
            'entry',
        );
    }

    private function returnError(Settings $settings, Entry $entry): Response
    {
        event(new GuestEntrySaveFailed($entry));

        return $this->asModelFailure(
            $entry,
            t('Couldn’t save entry.', category: 'guest-entries'),
            $settings->entryVariable,
        );
    }

    /**
     * Creates an entry and populates it with the submitted data.
     */
    private function populateEntry(Request $request, Section $section, SectionSettings $sectionSettings): Entry
    {
        $entry = new Entry([
            'sectionId' => $section->id,
            'authorId' => DB::table(Table::ELEMENTS)->idByUid((string)$sectionSettings->authorUid),
            'siteId' => $request->input('siteId'),
            'typeId' => $request->input('typeId') ?? $section->getEntryTypes()[0]->id,
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'enabled' => $sectionSettings->enabledByDefault,
            'enabledForSite' => $request->boolean('enabledForSite', true),
        ]);

        if (($postDate = $request->input('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }

        if (($expiryDate = $request->input('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        if (($newParentId = $request->input('parentId')) !== null) {
            $entry->setParentId($newParentId);
        }

        $entry->setFieldValuesFromRequest($request->input('fieldsLocation', 'fields'));

        return $entry;
    }
}
