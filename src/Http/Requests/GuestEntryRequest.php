<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Http\Requests;

use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Events\SectionResolutionFailed;
use CraftCms\GuestEntries\Settings;
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
            'siteId' => 'sometimes|nullable|int',
        ];

        $exclusiveIdentifiers = collect([
            'sectionId' => ['nullable', 'integer'],
            'sectionHandle' => ['nullable', 'string'],
            'sectionUid' => ['nullable', 'uuid'],
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

    public function attributes(): array
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
        return collect(app(Settings::class)->sections)
            ->where('allowGuestSubmissions')
            ->pluck('sectionUid')
            ->map(Sections::getSectionByUid(...))
            ->filter()
            ->values();
    }

    public function resolveSection(): ?Section
    {
        $checks = [
            'sectionId' => 'id',
            'sectionUid' => 'uid',
            'sectionHandle' => 'handle',
        ];

        // Only the first identifier that was sent is used; the rest are ignored:
        $param = collect(array_keys($checks))->first(fn (string $param) => $this->filled($param));

        if ($param && $section = $this->getAllowedSections()->firstWhere($checks[$param], $this->validated($param))) {
            return $section;
        }

        // Last chance! Let the app determine what section:
        event($failEvent = new SectionResolutionFailed($this));

        return $failEvent->section;
    }
}
