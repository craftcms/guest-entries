<?php

namespace CraftCms\GuestEntries\Http\Requests;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Plugin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class GuestEntryRequest extends FormRequest
{
    public function rules(): array
    {
        $allowedSections = $this->getAllowedSections();

        $rules = [
            'type' => 'sometimes|string',
            'typeId' => 'sometimes|int',
        ];

        // Define base rules for each type of section identifier:
        $sectionRules = collect([
            'sectionId' => [Rule::in($allowedSections->pluck('id'))],
            'sectionHandle' => [Rule::in($allowedSections->pluck('handle'))],
            'sectionUid' => [Rule::in($allowedSections->pluck('uid'))],
        ]);

        foreach ($sectionRules as $param => $baseRules) {
            $rules[$param] = [
                sprintf('required_without_all:%s', $sectionRules->keys()->diff([$param])->join(',')),
                ...$baseRules,
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'required_without_all' => t('A section must be specified by ID, handle, or UID.', category: 'guest-entries'),
        ]);
    }

    public function authorize(): bool
    {
        // Some of our “validation” and controller logic should probably go here.
        // Rules can make sure data is present and of the expected type; `authorize()` can actually inspect it and reject incompatible input.
        // Examples: The chosen entry type must be allowed in the section; the section must be enabled in the chosen/current site; ...
        return true;
    }

    /**
     * @return Collection<Section>
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
        if ($sectionId = $this->integer('sectionId')) {
            return Sections::getSectionById($sectionId);
        }

        if ($sectionHandle = $this->input('sectionHandle')) {
            return Sections::getSectionByHandle($sectionHandle);
        }

        if ($sectionUid = $this->input('sectionUid')) {
            return Sections::getSectionByUid($sectionUid);
        }

        return null;
    }
}
