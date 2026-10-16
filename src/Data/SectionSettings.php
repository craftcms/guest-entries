<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries\Data;

use CraftCms\Cms\Component\Component;

class SectionSettings extends Component
{
    /** The section UID. */
    public ?string $sectionUid = null;

    /** Whether the section allows guest entry submissions. */
    public bool $allowGuestSubmissions = false;

    /** Whether guest entry submissions should be enabled by default. */
    public bool $enabledByDefault = false;

    /** Whether guest entry submissions should be validated. */
    public bool $runValidation = false;

    /** The UID of the author that guest entries should be attributed to. */
    public ?string $authorUid = null;
}
