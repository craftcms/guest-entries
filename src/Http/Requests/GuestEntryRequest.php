<?php

namespace CraftCms\GuestEntries\Http\Requests;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sections;
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
            ...$exclusiveIdentifiers->mapWithKeys(fn($rules, $param) => [
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
        if ($sectionId = $this->validated('sectionId')) {
            return $this->getAllowedSections()->firstWhere('id', $sectionId);
        }

        if ($sectionHandle = $this->validated('sectionHandle')) {
            return $this->getAllowedSections()->firstWhere('handle', $sectionHandle);
        }

        if ($sectionUid = $this->validated('sectionUid')) {
            return $this->getAllowedSections()->firstWhere('uid', $sectionUid);
        }

        return null;
    }
}
