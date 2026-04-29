<?php

namespace CraftCms\GuestEntries\Http\Requests;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Events\SectionResolutionFailed;
use CraftCms\GuestEntries\Plugin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use function CraftCms\Cms\t;

class GuestEntryRequest extends FormRequest
{
    public function rules(): array
    {
        $baseRules = [
            'type' => 'sometimes|string',
            'typeId' => 'sometimes|int',
        ];

        $exclusiveIdentifiers = collect([
            'sectionId' => ['integer'],
            'sectionHandle' => ['string'],
            'sectionUid' => ['uuid'],
        ]);

        return [
            ...$baseRules,
            ...$exclusiveIdentifiers->mapWithKeys(fn ($rules, $param) => [
                $param => [
                    sprintf('required_without_all:%s', $exclusiveIdentifiers->keys()->diff([$param])->join(',')),
                    ...$rules,
                ],
            ])->all(),
        ];
    }

    public function attributes()
    {
        return [
            'sectionId' => t('section ID', category: 'guest-entries'),
            'sectionUid' => t('section UID', category: 'guest-entries'),
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'required_without_all' => t('A section must be specified by ID, handle, or UID.', category: 'guest-entries'),
        ]);
    }

    /**
     * @return Collection<int, Section>
     */
    public function getAllowedSections(): Collection
    {
        return collect(Plugin::getInstance()->getSettings()->sections)
            ->where('allowGuestSubmissions')
            ->pluck('sectionUid')
            ->map(Sections::getSectionByUid(...));
    }

    public function resolveSection(): ?Section
    {
        $candidates = $this->getAllowedSections();
        $checks = [
            'sectionId' => 'id',
            'sectionUid' => 'uid',
            'sectionHandle' => 'handle',
        ];

        foreach ($checks as $param => $attribute) {
            $value = $this->validated($param);

            if ($section = $candidates->firstWhere($attribute, $value)) {
                return $section;
            }
        }

        // Last chance! Let the app determine what section:
        event($failEvent = new SectionResolutionFailed($this));

        return $failEvent->section;
    }
}
