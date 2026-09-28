<?php

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Form\Controls\Concerns\HasTextExpander;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\CopyAttribute;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Http\Requests\SettingsRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use function CraftCms\Cms\t;

/**
 * @phpstan-import-type TextExpanderTriggers from HasTextExpander
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.1.0';

    public bool $hasCpSettings = true;

    public bool $config = true;

    public function boot(): void
    {
        RateLimiter::for('guest-entries', function (Request $request) {
            $limit = $this->getSettings()->rateLimit;

            // `null` has special significance, but isn't handled by other limit methods:
            if ($limit === null) {
                return Limit::none();
            }

            return Limit::perMinute($limit)->by($request->getClientIp());
        });
    }

    public function settingsForm(FormContext $context = new FormContext): ?Form
    {
        // Capture anything stored in config files so we can alert the administrator that their changes may be overwritten:
        $overrides = config(sprintf('craft.%s', $this->handle));

        $settingsForm = Form::make([
            Heading::make('section-settings-heading', t('Section Settings', category: 'aws-s3')),
            MarkdownContent::make('section-settings-note', t('Choose which sections should allow guest submissions, and configure section-specific rules.', category: 'aws-s3'))->displayInPane(false),
        ]);

        $sections = $context->values['settings']['sections'] ?? $this->getSettings()->sections;

        if (empty($sections)) {
            $settingsForm->add(MarkdownContent::make('section-settings-empty', t('No sections available.', category: 'guest-entries')));
        }

        /** @var TextExpanderTriggers $sectionExpansions */
        $sectionExpansions = [
            [
                'trigger' => '{',
                'boundary' => 'anywhere',
                'options' => array_map(fn (string $a): array => [
                    'label' => $a,
                    'value' => '{'.$a.'}',
                ], ['id', 'uid', 'name']),
            ],
        ];

        // An override at any level clobbers what we’ll do in the settings screen:
        $sectionsOverride = Arr::has($overrides, 'sections');

        if ($sectionsOverride) {
            $settingsForm->add(Callout::make('sections-override-alert', t('Section configuration is being overridden by `{file}`.', ['file' => 'config/craft/guest-entries.php'], 'guest-entries'))->variant('warning'));
        }

        foreach ($sections as $sectionSettings) {
            $section = Sections::getSectionByUid($sectionSettings['sectionUid']);

            $sectionGroup = Group::make("sections.$section->uid", [
                HiddenField::make("sections.$section->uid.sectionUid"),
                Heading::make("sections.$section->uid.heading", t($section->name, category: 'site'))->level(3),
                Field::make(t('Allow guest submissions?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.allowGuestSubmissions")
                    ->mode($sectionsOverride ? ControlMode::ReadOnly : ControlMode::Editable)
                    ->reactive())
                    ->instructions(t('When enabled, anonymous users will be able to create entries in this section.', category: 'guest-entries')),
            ]);

            // The rest of the form is only relevant when submissions are allowed:
            if ($sectionSettings['allowGuestSubmissions']) {
                $sectionGroup->add(
                    CopyAttribute::make(sprintf('section-copy-uid-%s', $section->uid), $section->uid),
                    Field::make(t('Default Author Template', category: 'guest-entries'), Text::make("sections.$section->uid.authorUid")
                        ->monospace()
                        ->textExpanderTriggers($sectionExpansions)
                        ->mode($sectionsOverride ? ControlMode::ReadOnly : ControlMode::Editable))
                        ->instructions(t('An object template that resolves to the UUIDs of one or more authors that entries in this section will be attributed to.', category: 'guest-entries'))
                        ->tip(t('Your template has access to all of this section’s properties, like `id`, `handle` and `uid`. The default behavior is equivalent to a query that finds the first user matching `.can("createEntries:#{uid}")`.', category: 'guest-entries')),
                    Field::make(t('Enable entries by default?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.enabledByDefault")
                        ->mode($sectionsOverride ? ControlMode::ReadOnly : ControlMode::Editable)),
                    Field::make(t('Run validation?', category: 'guest-entries'), Lightswitch::make("sections.$section->uid.runValidation")
                        ->mode($sectionsOverride ? ControlMode::ReadOnly : ControlMode::Editable))
                        ->instructions(t('Should validation rules be applied to the entry, when submitted by a guest? This should generally be **On** whenever entries are **Enabled by default**.', category: 'guest-entries')),
                );
            }

            $settingsForm->add($sectionGroup);
        }

        // Global settings

        $settingsForm->add(Heading::make('global-settings-header', t('Global Settings', category: 'guest-entries'))->level(2));
        $settingsForm->add(MarkdownContent::make('global-settings-help', t('These settings apply to **all** guest submissions.', category: 'guest-entries'))->displayInPane(false));

        $settingsForm->add(
            Field::make(
                t('Entry Variable Name', category: 'guest-entries'),
                Text::make('entryVariable')->monospace()
            )
                ->instructions(t('The template variable name that entry submissions should be assigned to, if there is a validation error.', category: 'guest-entries'))
                ->when(Arr::has($overrides, 'entryVariable'), function ($field) {
                    $field->getControl()->mode(ControlMode::ReadOnly);
                    $field->warning(t('This value was set by the `{key}` key in `{file}`.', ['key' => 'entryVariable', 'file' => 'config/craft/guest-entries.php'], 'guest-entries'));
                })
        );

        return $settingsForm;
    }

    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    public function getSettingsRequestClass(): ?string
    {
        return SettingsRequest::class;
    }
}
