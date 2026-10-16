# Upgrading to Guest Entries 5.0 (Craft 6)

This guide lists what changed in 5.0 and what you need to update in your project, templates and custom code.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter` is **not** required. The plugin is fully native and works without it. Your project may still need the adapter if other plugins or your own code do.

## Upgrade steps

1. Upgrade your project to Craft 6 first.
2. Update the plugin: `composer require craftcms/guest-entries:^5.0`.
3. Your existing settings (project config `plugins.guest-entries.settings`) load unchanged. No migration is needed.
4. Review the breaking changes below and update your code.

## Breaking changes

### Events

The Yii-style events on the save controller were removed, along with `craft\guestentries\events\SaveEvent`. They are now plain Laravel event classes, dispatched with `event()`. There is no `sender`, so you listen for the event class.

| Before | After |
| --- | --- |
| `SaveController::EVENT_BEFORE_SAVE_ENTRY` | `CraftCms\GuestEntries\Events\SavingGuestEntry` |
| `SaveController::EVENT_AFTER_SAVE_ENTRY` | `CraftCms\GuestEntries\Events\SavedGuestEntry` |
| `SaveController::EVENT_AFTER_ERROR` | `CraftCms\GuestEntries\Events\GuestEntrySaveFailed` |

Before:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

Event::on(
    SaveController::class,
    SaveController::EVENT_BEFORE_SAVE_ENTRY,
    function (SaveEvent $event) {
        if ($event->entry->title === 'spam') {
            $event->isSpam = true;
        }
        if ($event->entry->title === 'nope') {
            $event->isValid = false;
        }
    }
);
```

After:

```php
use CraftCms\GuestEntries\Events\SavingGuestEntry;
use Illuminate\Support\Facades\Event;

Event::listen(function (SavingGuestEntry $event): void {
    if ($event->entry->title === 'spam') {
        $event->isSpam = true;
    }
    if ($event->entry->title === 'nope') {
        $event->isValid = false;
    }
});
```

- `SavingGuestEntry` has `$entry`, `$isValid` (set to `false` to reject the submission) and `$isSpam` (set to `true` to silently discard it while still reporting success).
- `SavedGuestEntry` has `$entry` and `$isSpam`. It is also dispatched for submissions flagged as spam, as `afterSaveEntry` was.
- `GuestEntrySaveFailed` has `$entry`. It is dispatched when a listener rejects the submission or the entry fails to save.
- The `afterSaveEntry`/`afterError` events used to fire only if a handler existed. The new events are always dispatched.

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\guestentries\Plugin` | `CraftCms\GuestEntries\Plugin` |
| `craft\guestentries\models\Settings` | `CraftCms\GuestEntries\Settings` |
| `craft\guestentries\models\SectionSettings` | `CraftCms\GuestEntries\Data\SectionSettings` |
| `craft\guestentries\controllers\SaveController` | `CraftCms\GuestEntries\Http\Controllers\SaveController` |
| `craft\guestentries\events\SaveEvent` | _removed, see Events_ |

### Settings and config

- `Settings` is now a `CraftCms\Cms\Plugin\PluginSettings` with real public properties (`enableCsrfProtection`, `entryVariable`, `sections`). `getSections()` and `setSections()` were removed. `$settings->sections` is an array keyed by section UID, holding only sections that allow guest submissions. `getSection($uid)` still returns a `SectionSettings`.
- `SectionSettings::$authorUid` is now `?string`, and `SectionSettings` is a `CraftCms\Cms\Component\Component`.
- The settings page is now built with the form builder instead of a Twig template.
- The "Enable CSRF Protection?" setting still works. When off, the `actions/guest-entries/save` route is exempted from Laravel's CSRF check. There is no longer a system-level CSRF setting to defer to.

### Templates and Twig

A failed submission now redirects back to the page and flashes the submitted data, instead of re-rendering the template with an `entry` variable. Update your form templates:

| Before | After |
| --- | --- |
| `{% set entry = entry ?? null %}` | _not needed_ |
| `entry.title` | `old('title')` |
| `entry.body` (custom field) | `old('fields.body')` |
| `entry.getErrors('title')` | `errors.get('title')` |
| `craft.app.sections.getSectionByHandle('x')` | `Sections.getSectionByHandle('x')` |
| _(no flash message)_ | `session().get('error')` after a failure, `session().get('success')` after a redirect on success |

The “Entry Variable Name” setting now only names the entry key in Ajax error responses.

Ajax responses (`Accept: application/json`) now use `message`, `modelName`, `modelClass`, `modelId` (success only), `errors` (failure only) and the entry data nested under `entry`, and the keys `success`, `id`, `title`, `authorUsername`, `dateCreated`, `dateUpdated`, `postDate` and `url` listed in earlier README versions are no longer top-level keys. Use the nested entry data instead.

Redirects with object templates (`{{ redirectInput('thanks?id={id}') }}`) still work.

### Routes and action URLs

`actions/guest-entries/save` is unchanged. It is registered from `routes/actions.php`.

Requests to `admin/actions/guest-entries/save` (the control panel prefix) return 404, as before.

### Database and migrations

The plugin has no tables. Two old settings migrations (`m170710_200301_tweak_settings`, `m181105_160000_craft_31_compatibility`) were removed. Sites must be on Guest Entries 2.1 or later (schema version 2.1.0) before upgrading.

## Removed

- `craft\guestentries\events\SaveEvent` and the `SaveController::EVENT_*` constants (see Events).
- `Settings::getSections()` and `Settings::setSections()`.
- Craft 4 and 5 support. There are no cross-compatible releases.

## Need help?

Open an issue at https://github.com/craftcms/guest-entries/issues.
