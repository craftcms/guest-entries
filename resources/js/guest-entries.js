function initSettingsGroups() {
    const HIDDEN_CLASS = 'hidden';

    const $settingsGroups = document.querySelectorAll('[data-section-settings-group]');

    Array.from($settingsGroups).forEach(function($section) {
        const uid = $section.dataset.sectionSettingsGroup;
        const $conditionalFields = $section.querySelector('[data-section-settings-conditional-fields]');
        // Everything in this template is namespaced `settings`, so we don't include that in any input names or identifiers:
        const $allowToggle = document.getElementById(`settings-sections-${uid}-allowGuestSubmissions`);
        const $allowInput = $section.querySelector(`[name="sections[${uid}][allowGuestSubmissions]"]`);

        console.log(uid, $allowToggle, $allowInput);
        function resolveConditionalFieldVisibility() {
            if ($allowInput.value) {
                $conditionalFields.classList.remove(HIDDEN_CLASS);
            } else {
                $conditionalFields.classList.add(HIDDEN_CLASS);
            }
        }

        $allowToggle.addEventListener('click', resolveConditionalFieldVisibility);
        resolveConditionalFieldVisibility();
    });
}

// document.readyState === 'complete'
document.addEventListener('DOMContentLoaded', initSettingsGroups);
