<?php

namespace CraftCms\GuestEntries\Http\Controllers;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Entry\Entries;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\GuestEntries\Events\SavingGuestEntry;
use CraftCms\GuestEntries\Http\Requests\GuestEntryRequest;
use CraftCms\GuestEntries\Plugin;
use CraftCms\GuestEntries\Settings;
use Illuminate\Support\Facades\DB;
use function CraftCms\Cms\t;

class CreateGuestEntryController
{
    use RespondsWithFlash;

    public function __construct(
        private Sections $sections,
        private Sites $sites,
        private Entries $entries,
    )
    {}

    public function __invoke(GuestEntryRequest $request, Sites $sites)
    {
        $site = $sites->getCurrentSite();
        $section = $request->resolveSection();
        $types = $section->getEntryTypes();

        abort_unless(isset($section->getSiteSettings()[$site->id]), 400, t('The selected section does not exist in this site.', category: 'guest-entries'));

        $entry = new Entry;
        $entry->sectionId = $section->id;
        $entry->siteId = $site->id;

        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $sectionSettings = $settings->getSectionConfig($section);

        // Look up based on what was present in the request, preferring by handle:
        $typeId = match(true) {
            $request->has('type') => collect($types)->firstWhere('handle', $request->input('type')),
            $request->has('typeId') => collect($types)->firstWhere('id', $request->integer('typeId')),
            default => null,
        };

        // Still nothing? Let’s just use the first one available to the section:
        if (! $typeId) {
            $typeId = $types[0]->id;
        }

        $entry->typeId = $typeId;

        $entry->setAttributes([
            'authorIds' => array_filter([
                DB::table(Table::ELEMENTS)->idByUid($sectionSettings['authorUid'])
            ]),
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'enabled' => (bool)$sectionSettings['enabledByDefault'],
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

        $fieldsLocation = $request->string('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        // Give the app (and other plugins) a chance to reject the submission:
        event($saveEvent = new SavingGuestEntry($entry));

        // @todo Historically, we’ve not disclosed to the user when a submission is dropped after being flagged as spam!
        abort_if($saveEvent->isSpam || ! $saveEvent->isValid, 400, t('Your submission could not be saved.', category: 'guest-entries'));

        if ($sectionSettings['runValidation']) {
            $entry->setScenario(Element::SCENARIO_LIVE);
        }

        if (! Elements::saveElement($entry)) {
            return $this->asModelFailure(
                $entry,
                t('Your submission could not be saved. Please review it for errors.', category: 'guest-entries'),
                Plugin::getInstance()->getSettings()->entryVariable,
            );
        }

        return $this->asModelSuccess(
            $entry,
            t('The entry was saved successfully.', category: 'guest-entries'),
            Plugin::getInstance()->getSettings()->entryVariable,
        );
    }
}
