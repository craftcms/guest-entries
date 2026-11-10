<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Ui\Controls\Concerns\HasTextExpander;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\CopyAttribute;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\HiddenField;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Separator;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Contracts\Config\Repository;

use function CraftCms\Cms\t;

/**
 * Builds the plugin’s settings page in the control panel.
 *
 * @phpstan-import-type TextExpanderTriggers from HasTextExpander
 */
readonly class SettingsUi
{
    private const string CONFIG_FILE = 'config/craft/guest-entries.php';

    public function __construct(
        private Settings $settings,
        private Sections $sections,
        private Repository $config,
    ) {}

    public function make(UiContext $context = new UiContext): Ui
    {
        // Capture anything stored in config files so we can alert the administrator that their changes may be overwritten:
        $overrides = $this->config->get('craft.guest-entries') ?? [];

        // An override at any level clobbers what we’ll do in the settings screen:
        $sectionsMode = Arr::has($overrides, 'sections') ? ControlMode::ReadOnly : ControlMode::Editable;

        $ui = Ui::make([
            Heading::make('section-settings-heading', t('Section Settings', category: 'guest-entries')),
            MarkdownContent::make('section-settings-note', t('Choose which sections should allow guest submissions, and configure section-specific rules.', category: 'guest-entries'))->displayInPane(false),
        ]);

        $sections = $context->values['settings']['sections'] ?? $this->settings->sections;

        if (empty($sections)) {
            $ui->add(MarkdownContent::make('section-settings-empty', t('No sections available.', category: 'guest-entries')));
        }

        if ($sectionsMode === ControlMode::ReadOnly) {
            $ui->add(Callout::make('sections-override-alert', t('Section configuration is being overridden by `{file}`.', ['file' => self::CONFIG_FILE], 'guest-entries'))->variant('warning'));
        }

        foreach ($sections as $sectionSettings) {
            $section = $this->sections->getSectionByUid($sectionSettings['sectionUid']);

            $ui->add($this->sectionGroup($section, (bool) $sectionSettings['allowGuestSubmissions'], $sectionsMode));
            $ui->add(Separator::make("after-section-{$section->uid}"));
        }

        $ui->add(Heading::make('global-settings-header', t('Global Settings', category: 'guest-entries'))->level(2));
        $ui->add(MarkdownContent::make('global-settings-help', t('These settings apply to **all** guest submissions.', category: 'guest-entries'))->displayInPane(false));

        $ui->add(
            Field::make(
                t('Entry Variable Name', category: 'guest-entries'),
                Text::make('entryVariable')->monospace()
            )
                ->instructions(t('The template variable name that entry submissions should be assigned to, if there is a validation error.', category: 'guest-entries'))
                ->when(Arr::has($overrides, 'entryVariable'), function (Field $field) {
                    $field->getControl()->mode(ControlMode::ReadOnly);
                    $field->warning(t('This value was set by the `{key}` key in `{file}`.', ['key' => 'entryVariable', 'file' => self::CONFIG_FILE], 'guest-entries'));
                })
        );

        return $ui;
    }

    private function sectionGroup(Section $section, bool $allowGuestSubmissions, ControlMode $mode): Group
    {
        $group = Group::make("sections.$section->uid", [
            HiddenField::make("sections.$section->uid.sectionUid"),
            Field::make(t('Allow guest submissions?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.allowGuestSubmissions")
                ->mode($mode)
                ->reactive())
                ->instructions(t('When enabled, anonymous users will be able to create entries in this section.', category: 'guest-entries')),
        ])
            ->label($section->name);

        // The rest of the form is only relevant when submissions are allowed:
        if (! $allowGuestSubmissions) {
            return $group;
        }

        return $group->add(
            CopyAttribute::make(sprintf('section-copy-uid-%s', $section->uid), $section->uid),
            Field::make(t('Default Author Template', category: 'guest-entries'), Text::make("sections.$section->uid.authorUid")
                ->monospace()
                ->textExpanderTriggers($this->sectionExpansions())
                ->mode($mode))
                ->instructions(t('An object template that resolves to the UUIDs of one or more authors that entries in this section will be attributed to.', category: 'guest-entries'))
                ->tip(t('Your template has access to all of this section’s properties, like `id`, `handle` and `uid`.', category: 'guest-entries')),
            Field::make(t('Enable entries by default?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.enabledByDefault")
                ->mode($mode)),
            Field::make(t('Run validation?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.runValidation")
                ->mode($mode))
                ->instructions(t('Should validation rules be applied to the entry, when submitted by a guest? This should generally be **On** whenever entries are **Enabled by default**.', category: 'guest-entries')),
        );
    }

    /**
     * @return TextExpanderTriggers
     */
    private function sectionExpansions(): array
    {
        return [
            [
                'trigger' => '{',
                'boundary' => 'anywhere',
                'options' => array_map(fn (string $attribute): array => [
                    'label' => $attribute,
                    'value' => '{'.$attribute.'}',
                ], ['id', 'uid', 'name']),
            ],
        ];
    }
}
