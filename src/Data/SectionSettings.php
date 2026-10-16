<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace CraftCms\GuestEntries\Data;

use CraftCms\Cms\Component\Component;

/**
 * Settings for a single section.
 */
class SectionSettings extends Component
{
    /**
     * @var string|null The section’s UID
     */
    public ?string $sectionUid = null;

    /**
     * @var bool Whether guests may submit entries to the section
     */
    public bool $allowGuestSubmissions = false;

    /**
     * @var bool Whether new guest entries are enabled by default
     */
    public bool $enabledByDefault = false;

    /**
     * @var bool Whether guest entries are validated as if they were being published
     */
    public bool $runValidation = false;

    /**
     * @var string|null The UID of the user that new guest entries are attributed to
     */
    public ?string $authorUid = null;
}
