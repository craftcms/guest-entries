# Upgrading Guest Entries for Craft 6

This guide lists what changed when Guest Entries moved to Craft 6, and what to update in your project, templates and custom code.

## Requirements

- Craft CMS 6.0.0-alpha.1 or later
- PHP 8.5 or later
- The plugin no longer needs `craftcms/yii2-adapter`. Your project may still use it for other plugins and modules.

## Upgrade steps

1. Upgrade your project to Craft 6 first.
2. Require the Craft 6 version of the plugin: `composer require craftcms/guest-entries`.
3. Your saved settings carry over unchanged. Check them under **Settings** → **Plugins** → **Guest Entries**.
4. Update any event listeners, templates and PHP that reference the plugin, using the changes below.

## Breaking changes

### Events

The three Yii-style events on `craft\guestentries\controllers\SaveController` were replaced by Laravel events, and `craft\guestentries\events\SaveEvent` was removed.

| Before | After |
| --- | --- |
| `SaveController::EVENT_BEFORE_SAVE_ENTRY` (`beforeSaveEntry`) | `craft\guestentries\events\SavingGuestEntry` |
| `SaveController::EVENT_AFTER_SAVE_ENTRY` (`afterSaveEntry`) | `craft\guestentries\events\SavedGuestEntry` |
| `SaveController::EVENT_AFTER_ERROR` (`afterError`) | `craft\guestentries\events\GuestEntrySaveFailed` |

Before:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

Event::on(
    SaveController::class,
    SaveController::EVENT_BEFORE_SAVE_ENTRY,
    function(SaveEvent $e) {
        if (str_contains($e->entry->title, 'synergy')) {
            $e->isSpam = true;
        }
    }
);
```

After:

```php
use craft\guestentries\events\SavingGuestEntry;
use Illuminate\Support\Facades\Event;

Event::listen(function(SavingGuestEntry $event): void {
    if (str_contains($event->entry->title, 'synergy')) {
        $event->isSpam = true;
    }
});
```

Things that behave differently:

- Listeners receive the event object directly. There is no `sender`, and no `Event::on()` target class.
- `SavingGuestEntry` replaces the cancelable event: set `$event->isValid = false` to reject the submission, or `$event->isSpam = true` to pretend it worked and discard the entry.
- `SavedGuestEntry` has `$entry` and `$isSpam`. `GuestEntrySaveFailed` has `$entry`.
- `$entry` is now a `CraftCms\Cms\Entry\Elements\Entry`. Get validation errors with `$entry->errors()->getMessages()`, not `getErrors()`.
- Register listeners in a service provider’s `boot()` method (for a project, `app/Providers/AppServiceProvider.php`).

### Templates

After a failed non-Ajax submission, Craft redirects back to the page and flashes the result. The `entry` variable is no longer available in the template.

| Before | After |
| --- | --- |
| `entry.title` | `old('title')` |
| `entry.body` (custom field) | `old('fields.body')` |
| `entry.getErrors('title')` | `errors.get('title')` |
| `{% set entry = entry ?? null %}` | not needed |
| `craft.app.sections.getSectionByHandle('x')` | `Sections.getSectionByHandle('x')` |

The flash message is available as `session().get('error')`. The submitted entry’s data is flashed as an array under `session().get('entry')` (or the name set by the `entryVariable` setting); prefer `old()` and `errors`.

`csrfInput()`, `actionInput('guest-entries/save')` and `redirectInput()` work as before, and `actions/guest-entries/save` is still the action URL.

### Ajax responses

The response is the standard Craft 6 model response, with the HTTP status (`200` or `400`) indicating the outcome:

- `message`, `modelName`, `modelClass`, `modelId` and `entry` (all of the entry’s data) for a saved entry.
- `message`, `modelName`, `modelClass` and `entry` for a failed one, plus `errors` when the entry had validation errors.

Update any client code that reads other keys.

### Settings and configuration

- The settings page was rebuilt with Craft’s form builder. The saved settings keep the same shape.
- To override settings per project, use `config/craft/guest-entries.php`, which returns an array of settings (for example `return ['entryVariable' => 'submittedEntry'];`). Craft 5 projects used `config/guest-entries.php`.
- The `enableCsrfProtection` setting still controls CSRF verification for the submission route.

### Classes

The `craft\guestentries` namespace is unchanged, but these classes changed:

| Class | Change |
| --- | --- |
| `craft\guestentries\Plugin` | Extends `CraftCms\Cms\Plugin\Plugin`. `init()` and `createSettingsModel()` are replaced by `boot()` and `createSettings()`. |
| `craft\guestentries\models\Settings` | Extends `CraftCms\Cms\Plugin\PluginSettings`. Validation uses `getRules()` instead of `defineRules()`. `sections` is a plain array keyed by section UID. `getSection()` and `getSections()` still return `SectionSettings` objects. |
| `craft\guestentries\models\SectionSettings` | Extends `CraftCms\Cms\Component\Component` instead of `craft\base\Model`. |
| `craft\guestentries\controllers\SaveController` | An invokable controller with no base class. It no longer has `actionIndex()` or Yii events. |

## Removed

- The migrations that upgraded settings from versions before 2.1.0. Upgrade to 4.x first if you are on an older version.
- `src/templates/_settings.html`, replaced by the form builder.
