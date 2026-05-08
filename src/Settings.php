<?php

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Edition;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Models\Section as SectionModel;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use function CraftCms\Cms\t;

/**
 * Settings represents the global settings for Guest Entries.
 */
class Settings extends PluginSettings
{
    /**
     * @deprecated 5.0.0 This setting has no effect. You may enable or disable CSRF at the application level.
     */
    public bool $enableCsrfProtection = true;

    /**
     * @var int|null The number of requests allowed per IP, per minute. Use `null` to disable limiting. Note that this only applies to the default route defined in `routes/web.php` ({@see $endpoint}), and must be applied to any aliases created in the host application.
     */
    public ?int $rateLimit = null;

    /**
     * @var string The URI your front-end forms should POST to.
     *
     * You may create additional routes in your app’s `routes/web.php` file:
     *
     * ```php
     * use CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController;
     * use Illuminate\Routing\Route;
     *
     * Route::post('another/path', CreateGuestEntryController::class);
     * ```
     *
     * Keep in mind that this bypasses any built-in rate limiting on the default endpoint.
     */
    public string $endpoint = 'guest-entries/save';

    /**
     * @var string The name of the variable to return to the template in case there is a validation error.
     */
    public string $entryVariable = 'entry';

    /**
     * @var array Per-section settings. We “fill” this with all sections in the system.
     */
    public array $sections {
        get => $this->getSectionSettings();
        set {
            $this->setSectionSettings($value);
        }
    }

    private ?array $_sectionSettings = null;

    public function getRules(): array
    {
        return [
            'sections.*.sectionUid' => [
                'required',
                'uuid',
                Rule::exists(SectionModel::class, 'uid'),
            ],
            'sections.*.allowGuestSubmissions' => ['nullable', 'boolean'],
            'sections.*.enabledByDefault' => ['nullable', 'boolean'],
            'sections.*.runValidation' => ['nullable', 'boolean'],
            'sections.*.authorUid' => [
                'required',
                'uuid',
                function (string $attribute, string $value, \Closure $fail, Validator $validator) {
                    $index = explode('.', $attribute)[1];
                    $sectionUid = data_get($validator->getData(), "sections.{$index}.sectionUid");
                    $allowedAuthors = $this->getDefaultAuthorOptions($sectionUid)
                        ->pluck('uid')
                        ->all();

                    if (! $validator->validateIn($attribute, $value, $allowedAuthors)) {
                        $fail(t('The selected author must have the correct permissions for this section.', category: 'guest-entries'));
                    }
                },
            ],
        ];
    }

    public function getMessages(): array
    {
        return [
            'sections.*.sectionUid' => t('A valid section UID is required.', category: 'guest-entries'),
            'sections.*.allowGuestSubmissions' => t('This must be true or false.', category: 'guest-entries'),
            'sections.*.enabledByDefault' => t('This must be true or false.', category: 'guest-entries'),
            'sections.*.runValidation' => t('This must be true or false.', category: 'guest-entries'),
            'uuid.sections.*.authorUid' => t('You must specify the default author as a UUID.', category: 'guest-entries'),
            'sections.*.authorUid' => t('You must select an author with permissions to create entries in this section.', category: 'guest-entries'),
        ];
    }

    public function getSectionSettings(): array
    {
        if (! isset($this->_sectionSettings)) {
            // If we haven’t loaded config get, just apply an empty set:
            $this->setSectionSettings([]);
        }

        return $this->_sectionSettings;
    }

    public function setSectionSettings(array $settings): void
    {
        $settings = Arr::keyBy($settings, 'sectionUid');

        // “Fill” the incoming data with real section information, then overlay the configuration:
        $this->_sectionSettings = Sections::getAllSections()
            ->where('type', '!==', SectionType::Single)
            ->map(function (Section $s) use ($settings) {
                return ($settings[$s->uid] ?? []) + [
                    'sectionUid' => $s->uid,
                    'allowGuestSubmissions' => false,
                    'enabledByDefault' => false,
                    'runValidation' => false,
                    'authorUid' => null,
                ];
            })
            ->values()
            ->all();
    }

    public function getDefaultAuthorOptions(string $sectionUid): Collection
    {
        // Only multi-user installations need to query by permissions:
        if (Edition::get()->value < Edition::Team->value) {
            return collect([Auth::user()]);
        }

        return User::find()
            ->can('createEntries:'.$sectionUid)
            ->get();
    }

    public function getSectionConfig(Section $section): ?array
    {
        foreach ($this->sections as $config) {
            if ($section->uid === $config['sectionUid']) {
                return $config;
            }
        }

        return null;
    }
}
