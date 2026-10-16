<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Edition;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\CopyAttribute;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Section\Sections;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.1.0';

    public bool $hasCpSettings = true;

    public function boot(Routes $routes): void
    {
        $this->app->singleton(Settings::class, fn () => $this->getSettings());

        if (! $this->getSettings()->enableCsrfProtection) {
            PreventRequestForgery::except([$routes->actionTriggerUriPrefix()."/$this->handle/save"]);
        }
    }

    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }

    public function settingsForm(FormContext $context = new FormContext): ?Form
    {
        $form = Form::make();
        $sections = app(Sections::class)->getAllSections()
            // No sense in doing this for singles.
            ->reject(fn (Section $section) => $section->type === SectionType::Single);

        if ($sections->isEmpty()) {
            $form->add(Callout::make('no-sections', t('No sections available.', category: 'guest-entries')));
        }

        // Only Pro can attribute entries to any author who can create them
        $allAuthors = Edition::isAtLeast(Edition::Pro) ? null : $this->formatAuthorOptions(array_filter([currentUserElement()]));

        foreach ($sections as $section) {
            $form->add(
                Group::make("section-$section->uid", [
                    Field::make(
                        t('Allow guest submissions', category: 'guest-entries'),
                        Lightswitch::make(['sections', $section->uid, 'allowGuestSubmissions']),
                    ),
                    Field::make(
                        t('Default Author', category: 'guest-entries'),
                        Choice::make(['sections', $section->uid, 'authorUid'])
                            ->options($allAuthors ?? $this->getSectionAuthorOptions($section))
                            ->placeholder(''),
                    )->instructions(t('Who the guest entries should be attributed to by default.', category: 'guest-entries')),
                    Field::make(
                        t('Default Status', category: 'guest-entries'),
                        Lightswitch::make(['sections', $section->uid, 'enabledByDefault']),
                    )->instructions(t('Whether the guest entries should be enabled by default.', category: 'guest-entries')),
                    Field::make(
                        t('Validate?', category: 'guest-entries'),
                        Lightswitch::make(['sections', $section->uid, 'runValidation']),
                    )->instructions(t('Whether the guest entries should be validated when submitted.', category: 'guest-entries')),
                    CopyAttribute::make("section-uid-$section->uid", $section->uid),
                ])->label(t($section->name, category: 'site'))->collapsible(),
            );
        }

        $form->add(
            Field::make(
                t('Enable CSRF Protection?', category: 'guest-entries'),
                Lightswitch::make('enableCsrfProtection'),
            )->instructions(t('Whether `guest-entries/save` requests should be protected against CSRF attacks.', category: 'guest-entries')),
            Field::make(
                t('Entry Variable Name', category: 'guest-entries'),
                Text::make('entryVariable'),
            )->instructions(t('The template variable name that entry submissions should be assigned to, if there is a validation error.', category: 'guest-entries')),
        );

        return $form;
    }

    /**
     * Returns the authors that can publish to the given section.
     *
     * @return list<array{label: string, value: string}>
     */
    private function getSectionAuthorOptions(Section $section): array
    {
        return $this->formatAuthorOptions(
            User::find()->can('createEntries:'.$section->uid)->all(),
        );
    }

    /**
     * Formats the given list of authors for a select input.
     *
     * @param  iterable<User>  $authors
     * @return list<array{label: string, value: string}>
     */
    private function formatAuthorOptions(iterable $authors): array
    {
        $options = [];

        foreach ($authors as $author) {
            $label = $author->username;

            if ($fullName = $author->fullName) {
                $label .= " ($fullName)";
            }

            $options[] = ['label' => $label, 'value' => $author->uid];
        }

        return $options;
    }
}
