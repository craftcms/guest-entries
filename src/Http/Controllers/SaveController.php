<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Http\Controllers;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\GuestEntries\Data\SectionSettings;
use CraftCms\GuestEntries\Events\GuestEntrySaveFailed;
use CraftCms\GuestEntries\Events\SavedGuestEntry;
use CraftCms\GuestEntries\Events\SavingGuestEntry;
use CraftCms\GuestEntries\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Saves guest entries submitted from the front end.
 */
class SaveController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Settings $settings,
        private readonly Sections $sections,
        private readonly Elements $elements,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Only allow front end requests
        abort_if($request->isCpRequest(), 404);

        // Make sure the section exists
        $sectionHandle = $request->input('sectionHandle');
        $sectionUid = $request->input('sectionUid');

        if ($sectionHandle) {
            $section = $this->sections->getSectionByHandle($sectionHandle);
        } elseif ($sectionUid) {
            $section = $this->sections->getSectionByUid($sectionUid);
        } else {
            $section = $this->sections->getSectionById((int) $request->input('sectionId'));
        }

        abort_unless($section, 400, 'Section does not exist.');

        // Make sure the section allows guest submissions
        $sectionSettings = $this->settings->getSection($section->uid);

        abort_unless($sectionSettings->allowGuestSubmissions, 400, "Section $section->handle does not allow guest submissions.");

        // Populate the entry
        $entry = $this->populateEntry($request, $section, $sectionSettings);

        event($saving = new SavingGuestEntry($entry));

        if (! $saving->isValid) {
            return $this->returnError($entry);
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

        if (! $this->elements->saveElement($entry)) {
            return $this->returnError($entry);
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

    private function returnError(Entry $entry): Response
    {
        event(new GuestEntrySaveFailed($entry));

        return $this->asModelFailure(
            $entry,
            t('Couldn’t save entry.', category: 'guest-entries'),
            $this->settings->entryVariable,
        );
    }

    /**
     * Populates an entry with post data.
     */
    private function populateEntry(Request $request, Section $section, SectionSettings $sectionSettings): Entry
    {
        $entry = new Entry([
            'sectionId' => $section->id,
            'authorId' => DB::table(Table::ELEMENTS)->idByUid($sectionSettings->authorUid),
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
