<?php

declare(strict_types=1);

use CraftCms\Cms\Entry\Models\EntryType;
use CraftCms\Cms\Field\Models\Field;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Models\Section as SectionModel;
use CraftCms\Cms\Site\Models\Site;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\User\Elements\User;
use CraftCms\GuestEntries\Settings;
use CraftCms\GuestEntries\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Creates a channel with `submission` and `question` entry types (each with a required `body` field), and
 * configures it for guest submissions.
 *
 * @param  array<string, mixed>  $settings  Section settings to override
 */
function guestSection(string $handle = 'submissions', array $settings = [], Site ...$sites): Section
{
    $body = Field::query()->where('handle', 'body')->first()
        ?? Field::factory()->create(['name' => 'Body', 'handle' => 'body', 'type' => PlainText::class]);

    $types = collect(['submission', 'question'])->map(
        fn (string $type) => EntryType::query()->where('handle', $type)->first()
            ?? EntryType::factory()->withField($body, required: true)->create(['handle' => $type, 'name' => ucfirst($type)]),
    );

    $model = SectionModel::factory()->withEntryTypes(...$types)->withSites(...$sites)->create(['handle' => $handle, 'name' => ucfirst($handle)]);
    $section = Sections::getSectionById($model->id);

    allowGuests($section, $settings);

    return $section;
}

/**
 * @param  array<string, mixed>  $settings
 */
function allowGuests(Section $section, array $settings = []): void
{
    $plugin = app(Settings::class);
    $plugin->sections = [
        ...$plugin->sections,
        $section->uid => [
            'sectionUid' => $section->uid,
            'allowGuestSubmissions' => true,
            'enabledByDefault' => true,
            'runValidation' => true,
            'authorUid' => User::find()->admin()->one()->uid,
            ...$settings,
        ],
    ];
}
