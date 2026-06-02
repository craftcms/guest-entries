<?php

namespace CraftCms\GuestEntries\Http\Requests;

use CraftCms\Cms\Section\Models\Section as SectionModel;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Validation\Rules\HandleRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Twig\Error\Error;

use function CraftCms\Cms\renderObjectTemplate;
use function CraftCms\Cms\t;

class SettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'settings.entryVariable' => ['required', 'string', new HandleRule],
            'settings.sections.*.sectionUid' => [
                'required',
                'uuid',
                Rule::exists(SectionModel::class, 'uid'),
            ],
            'settings.sections.*.allowGuestSubmissions' => ['nullable', 'boolean'],
            'settings.sections.*.enabledByDefault' => ['nullable', 'boolean'],
            'settings.sections.*.runValidation' => ['nullable', 'boolean'],
            'settings.sections.*.authorUid' => Rule::forEach(function (?string $value, string $attribute, array $data, array $context) {
                return [
                    // Bail early so the rest of the rules can be strongly-typed:
                    Rule::excludeUnless((bool) $context['allowGuestSubmissions']),
                    'required',
                    'string',
                    function (string $attribute, string $value, \Closure $fail) {
                        $index = explode('.', $attribute)[2];
                        $section = Sections::getSectionByUid($index);

                        try {
                            // The return value is not important; it just needs to compile!
                            renderObjectTemplate($value, $section);
                        } catch (Error $e) {
                            $fail(t('The template is invalid: {err}', ['err' => $e->getMessage()], 'guest-entries'));
                        }
                    },
                ];
            }),
        ];
    }

    public function messages(): array
    {
        return [
            'settings.entryVariable' => t('This must be a valid Twig variable name.', category: 'guest-entries'),
            'settings.sections.*.sectionUid' => t('A valid section UID is required.', category: 'guest-entries'),
            'settings.sections.*.allowGuestSubmissions' => t('This must be true or false.', category: 'guest-entries'),
            'required.settings.sections.*.authorUid' => t('You must provide a way to determine the default author.', category: 'guest-entries'),
            'settings.sections.*.enabledByDefault' => t('This must be true or false.', category: 'guest-entries'),
            'settings.sections.*.runValidation' => t('This must be true or false.', category: 'guest-entries'),
        ];
    }

    public function attributes(): array
    {
        return [
            'settings.entryVariable' => t('entry variable name', category: 'guest-entries'),
            'settings.sections.*.sectionUid' => t('section UID', category: 'guest-entries'),
            'settings.sections.*.authorUid' => t('author UID template', category: 'guest-entries'),
        ];
    }
}
