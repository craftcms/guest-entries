<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries;

use craft\guestentries\models\Settings;
use CraftCms\Cms\Edition;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\CopyAttribute;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Class Plugin
 *
 * @method Settings getSettings()
 */
class Plugin extends \CraftCms\Cms\Plugin\Plugin
{
    /**
     * @inheritdoc
     */
    public string $schemaVersion = '2.1.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function boot(Routes $routes): void
    {
        // Let classes type-hint the settings instead of looking up the plugin
        $this->app->singleton(Settings::class, fn() => $this->getSettings());

        if (!$this->getSettings()->enableCsrfProtection) {
            PreventRequestForgery::except([
                $routes->actionTriggerUriPrefix() . "/$this->handle/save",
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function settingsForm(FormContext $context = new FormContext): ?Form
    {
        $sections = Sections::getAllSections()
            // No sense in doing this for singles.
            ->reject(fn(Section $section) => $section->type === SectionType::Single);

        if ($sections->isEmpty()) {
            return Form::make([
                Callout::make('no-sections', t('Create a channel or structure section to allow guest submissions.', category: 'guest-entries')),
            ]);
        }

        $currentUser = currentUserElement();
        $proAuthors = Edition::isAtLeast(Edition::Pro);
        $form = Form::make();

        foreach ($sections as $section) {
            $authorOptions = $this->authorOptions($proAuthors || !$currentUser ? $this->sectionAuthors($section) : [$currentUser]);

            $form->add(
                Group::make("section-$section->uid", [
                    Field::make(
                        t('Allow guest submissions', category: 'guest-entries'),
                        Lightswitch::make(['sections', $section->uid, 'allowGuestSubmissions']),
                    ),
                    Field::make(
                        t('Default Author', category: 'guest-entries'),
                        Choice::make(['sections', $section->uid, 'authorUid'])
                            ->options($authorOptions)
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
                ])->label($section->name)->collapsible(),
            );
        }

        return $form;
    }

    /**
     * Returns the users that can publish to the given section.
     *
     * @return User[]
     */
    private function sectionAuthors(Section $section): array
    {
        $authors = [];

        foreach (User::find()->can('createEntries:' . $section->uid)->all() as $author) {
            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * Formats the given list of authors for a select input.
     *
     * @param User[] $authors
     * @return array<int, array{label: string, value: string}>
     */
    private function authorOptions(array $authors): array
    {
        $options = [];

        foreach ($authors as $author) {
            $authorLabel = $author->username;

            if ($fullName = $author->fullName) {
                $authorLabel .= ' (' . $fullName . ')';
            }

            $options[] = ['label' => $authorLabel, 'value' => $author->uid];
        }

        return $options;
    }
}
