<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\GuestEntries\Data\SectionSettings;

/**
 * Settings represents the global settings for Guest Entries.
 */
class Settings extends PluginSettings
{
    /**
     * Whether `guest-entries/save` requests should be protected against CSRF attacks.
     */
    public bool $enableCsrfProtection = true;

    /**
     * The name of the variable to return to the template in case there is a validation error.
     */
    public string $entryVariable = 'entry';

    /**
     * Per-section settings, keyed by section UID. Only sections that allow guest submissions are kept.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $sections = [] {
        set(array $sections) => $this->normalizeSections($sections);
    }

    /**
     * Returns a section's settings by its UID.
     */
    public function getSection(string $uid): SectionSettings
    {
        return new SectionSettings($this->sections[$uid] ?? ['sectionUid' => $uid]);
    }

    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'enableCsrfProtection' => ['boolean'],
            'entryVariable' => ['required', 'string'],
            'sections.*.authorUid' => ['required', 'string'],
        ]);
    }

    /**
     * @param  array<string|int, mixed>  $sections
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            // Ignore sections that don't allow guest submissions
            if (! filter_var($config['allowGuestSubmissions'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            // Watch out for old config data that's not keyed by UID yet
            $uid = $config['sectionUid'] ?? (is_string($key) ? $key : null);
            if ($uid === null) {
                continue;
            }

            $normalized[$uid] = [
                'sectionUid' => $uid,
                'allowGuestSubmissions' => true,
                'enabledByDefault' => filter_var($config['enabledByDefault'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'runValidation' => filter_var($config['runValidation'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'authorUid' => $config['authorUid'] ?? null,
            ];
        }

        return $normalized;
    }
}
