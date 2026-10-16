<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\models;

use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Support\Str;

use function CraftCms\Cms\t;

/**
 * Settings represents the global settings for Guest Entries.
 */
class Settings extends PluginSettings
{
    /**
     * @var bool Whether CSRF protection is enabled for the front-end submission route
     */
    public bool $enableCsrfProtection = true;

    /**
     * @var string The name of the variable that the submitted entry is made available as, when a submission fails
     */
    public string $entryVariable = 'entry';

    /**
     * @var array<string, array{sectionUid: string, allowGuestSubmissions: bool, enabledByDefault: bool, runValidation: bool, authorUid: ?string}>
     * Settings for sections that allow guest submissions, keyed by section UID.
     */
    public array $sections = [] {
        set(array $sections) => $this->normalizeSections($sections);
    }

    /**
     * Returns the settings for a section, whether or not it allows guest submissions.
     */
    public function getSection(string $uid): SectionSettings
    {
        return new SectionSettings($this->sections[$uid] ?? ['sectionUid' => $uid]);
    }

    /**
     * Returns the settings for each section that allows guest submissions, keyed by section UID.
     *
     * @return SectionSettings[]
     */
    public function getSections(): array
    {
        return array_map(fn(array $config) => new SectionSettings($config), $this->sections);
    }

    /**
     * @inheritdoc
     */
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'enableCsrfProtection' => ['boolean'],
            'entryVariable' => ['required', 'string'],
            'sections' => ['array'],
            'sections.*.authorUid' => ['required', 'string'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getMessages(): array
    {
        return [
            'sections.*.authorUid.required' => t('A default author is required for sections that allow guest submissions.', category: 'guest-entries'),
        ];
    }

    /**
     * Keeps only sections that allow guest submissions, keyed by UID, with a consistent shape.
     *
     * @param array<array-key, array<string, mixed>> $sections
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            // Ignore old config data (keyed by section ID) that isn't updated yet
            $uid = $config['sectionUid'] ?? (Str::isUuid((string)$key) ? (string)$key : null);

            if ($uid === null || !filter_var($config['allowGuestSubmissions'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $normalized[$uid] = [
                'sectionUid' => $uid,
                'allowGuestSubmissions' => true,
                'enabledByDefault' => filter_var($config['enabledByDefault'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'runValidation' => filter_var($config['runValidation'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'authorUid' => ($config['authorUid'] ?? '') !== '' ? $config['authorUid'] : null,
            ];
        }

        return $normalized;
    }
}
