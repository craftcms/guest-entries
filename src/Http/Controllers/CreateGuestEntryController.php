<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Http\Controllers;

use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\User\Elements\User;
use CraftCms\GuestEntries\Events\GuestEntrySaved;
use CraftCms\GuestEntries\Events\GuestEntrySaveFailed;
use CraftCms\GuestEntries\Events\GuestEntrySaving;
use CraftCms\GuestEntries\Http\Requests\GuestEntryRequest;
use CraftCms\GuestEntries\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\Error;

use function CraftCms\Cms\renderObjectTemplate;
use function CraftCms\Cms\t;

class CreateGuestEntryController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Settings $settings,
        private readonly Sites $sites,
        private readonly Elements $elements,
    ) {}

    public function __invoke(GuestEntryRequest $request): Response
    {
        $section = $request->resolveSection()
            ?? throw new AuthorizationException(t('Entries can only be created in designated sections.', category: 'guest-entries'));

        $site = $request->filled('siteId')
            ? $this->sites->getSiteById($request->integer('siteId'))
            : $this->sites->getCurrentSite();

        abort_unless($site && isset($section->getSiteSettings()[$site->id]), 400, t('The selected section does not exist in this site.', category: 'guest-entries'));

        $sectionSettings = $this->settings->getSectionConfig($section);
        $types = collect($section->getEntryTypes());

        // Look up based on what was present in the request, preferring the handle. Without either, use the section’s first type:
        $type = match (true) {
            $request->filled('type') => $types->firstWhere('handle', $request->input('type')),
            $request->filled('typeId') => $types->firstWhere('id', $request->integer('typeId')),
            default => $types->first(),
        } ?? throw ValidationException::withMessages([
            ($request->filled('type') ? 'type' : 'typeId') => t('The selected entry type isn’t available in this section.', category: 'guest-entries'),
        ]);

        $entry = new Entry;
        $entry->sectionId = $section->id;
        $entry->siteId = $site->id;
        $entry->typeId = $type->id;

        if ($authorTemplate = $sectionSettings['authorUid'] ?? null) {
            try {
                $entry->setAuthors(User::find()
                    ->uid(renderObjectTemplate($authorTemplate, $section))
                    ->all());
            } catch (Error $e) {
                // We’re only catching Twig rendering errors, here… it’ll be the developer’s responsibility to handle query errors, downstream.
                Log::error(sprintf('Failed to assign authors using template `%s`: %s', $authorTemplate, $e->getMessage()));
            }
        }

        $entry->setAttributes([
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'enabled' => (bool) ($sectionSettings['enabledByDefault'] ?? false),
            'enabledForSite' => $request->boolean('enabledForSite', true),
            'revisionNotes' => t('Created by the Guest Entries plugin.', category: 'guest-entries'),
        ]);

        if (($postDate = $request->input('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }

        if (($expiryDate = $request->input('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        if ($request->filled('parentId')) {
            $entry->setParentId($request->integer('parentId'));
        }

        $entry->setFieldValuesFromRequest($request->string('fieldsLocation', 'fields')->toString());

        // Give the app (and other plugins) a chance to reject the submission:
        event($savingEvent = new GuestEntrySaving($entry));

        if (! $savingEvent->isValid) {
            Log::warning('An event handler prevented a guest entry from being saved.');

            return $this->failure($entry);
        }

        if ($savingEvent->isSpam) {
            Log::info('Guest entry submission suspected to be spam.');

            // Pretend it worked, so the submitter can’t tell that it was dropped:
            return $this->success($entry);
        }

        if ($sectionSettings['runValidation'] ?? false) {
            $entry->ruleset->useScenario(ElementRules::SCENARIO_LIVE);
        }

        if (! $this->elements->saveElement($entry)) {
            return $this->failure($entry);
        }

        event(new GuestEntrySaved($entry));

        return $this->success($entry);
    }

    private function success(Entry $entry): Response
    {
        return $this->asModelSuccess(
            $entry,
            t('The entry was saved successfully.', category: 'guest-entries'),
            $this->settings->entryVariable,
        );
    }

    private function failure(Entry $entry): Response
    {
        event(new GuestEntrySaveFailed($entry));

        return $this->asModelFailure(
            $entry,
            t('Your submission could not be saved. Please review it for errors.', category: 'guest-entries'),
            $this->settings->entryVariable,
        );
    }
}
