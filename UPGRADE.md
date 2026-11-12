# Upgrading to Guest Entries 5.0 (Craft 6)

This guide lists what changed in 5.0 and what you need to update in your project, templates and custom code.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter` is no longer needed. The plugin is native to Craft 6. Your project only needs the adapter if other plugins or your own code do.

## Upgrade steps

1. Upgrade your project to Craft 6 first.
2. Update the plugin: `composer require craftcms/guest-entries:^5.0`.
3. Your settings in project config (`plugins.guest-entries.settings`) are used as they are. The plugin has no tables, so no migrations run.
4. If you overrode settings in `config/guest-entries.php`, move the file to `config/craft/guest-entries.php`. Run `php artisan vendor:publish --tag=guest-entries` to start from the plugin’s stub.
5. Review the breaking changes below and update your templates and custom code.

## Breaking changes

### Events

The Yii events on the save controller were removed, along with `craft\guestentries\events\SaveEvent`. They are now Laravel event classes. There is no `sender`, so you listen for the event class.

| Before | After |
| --- | --- |
| `SaveController::EVENT_BEFORE_SAVE_ENTRY` | `CraftCms\GuestEntries\Events\GuestEntrySaving` |
| `SaveController::EVENT_AFTER_SAVE_ENTRY` | `CraftCms\GuestEntries\Events\GuestEntrySaved` |
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
        if (str_contains($event->entry->title, 'synergy')) {
            $event->isSpam = true;
        }
    }
);
```

After:

```php
use CraftCms\GuestEntries\Events\GuestEntrySaving;
use Illuminate\Support\Facades\Event;

Event::listen(function (GuestEntrySaving $event): void {
    if (str_contains($event->entry->title, 'synergy')) {
        $event->isSpam = true;
    }
});
```

- `GuestEntrySaving` has `$entry`, `$isValid` and `$isSpam`, as `SaveEvent` did. Setting `$isValid` to `false` rejects the submission. Setting `$isSpam` to `true` drops it but still responds as though it was saved.
- `GuestEntrySaved` has `$entry`. It is only dispatched for entries that were saved, so it no longer fires for spam and has no `$isSpam` property. Check for spam in a `GuestEntrySaving` listener instead.
- `GuestEntrySaveFailed` has `$entry`. It is dispatched when a `GuestEntrySaving` listener rejects the submission, or when the entry fails to save.
- Added `SectionResolutionFailed`, dispatched when no section allowing guest submissions matches the submitted section parameters. A listener can set `$event->section`, using `$event->request` to decide. The section doesn’t need to allow guest submissions, so never pick it from user input.

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\guestentries\Plugin` | `CraftCms\GuestEntries\Plugin` |
| `craft\guestentries\models\Settings` | `CraftCms\GuestEntries\Settings` |
| `craft\guestentries\models\SectionSettings` | _Removed. Section settings are arrays, see below._ |
| `craft\guestentries\controllers\SaveController` | `CraftCms\GuestEntries\Http\Controllers\CreateGuestEntryController` |
| `craft\guestentries\events\SaveEvent` | _Removed, see [Events](#events)._ |

The plugin’s settings are bound in the container, so you can type-hint `CraftCms\GuestEntries\Settings` instead of calling `Plugin::getInstance()->getSettings()`.

### Settings and config

- `Settings::$sections` is an array keyed by section UID, with an entry for every section that isn’t a single. Each one has the `sectionUid`, `allowGuestSubmissions`, `enabledByDefault`, `runValidation` and `authorUid` keys. `getSection()`, `getSections()` and `setSections()` were removed. Use `$settings->sections` or `$settings->getSectionConfig($section)`.
- `authorUid` is now the **Default Author Template**: an object template, rendered with the section, that outputs one or more user UIDs. A saved UID is a valid template, so existing settings keep working.
- `enableCsrfProtection` no longer has an effect. Guest submissions use the app’s CSRF protection. To exempt them, add the routes to your app’s `bootstrap/app.php`:

  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->preventRequestForgery(except: ['actions/guest-entries/save', 'guest-entries/save']);
  })
  ```

- Added `rateLimit`, the number of submissions allowed per IP address per minute (`null`, the default, disables it), and `endpoint`, the front-end URI that accepts submissions (`guest-entries/save` by default).
- Settings in `config/craft/guest-entries.php` override project config, and the settings page shows them as read-only. A `sections` key replaces all section settings.

### Routes and action URLs

- `actions/guest-entries/save` still works, and so do forms that post to their own page with `{{ actionInput('guest-entries/save') }}`.
- Submissions are also accepted at the `endpoint` setting (`/guest-entries/save` by default), so forms can use `action="{{ url('guest-entries/save') }}"`.
- Posting to a section that doesn’t allow guest submissions, or doesn’t exist, now responds with 403 instead of 400. Leaving out `sectionId`, `sectionUid` and `sectionHandle` is a validation error.
- The section is chosen by the first of `sectionId`, `sectionUid` and `sectionHandle` that isn’t blank, in that order, and the others are ignored. Before, the order was `sectionHandle`, `sectionUid`, `sectionId`, so forms that send more than one may now resolve to a different section.
- The entry type can also be chosen by handle, with a `type` parameter. A `type` or `typeId` that isn’t one of the section’s entry types is now a validation error.
- `siteId` must be a site the section is enabled for, otherwise the response is 400.

### Templates and Twig

A failed submission now redirects back to the form instead of rendering it again with an `entry` variable. The submitted values, errors and messages are flashed to the session:

| Before | After |
| --- | --- |
| `entry.title` | `old('title')` |
| `entry.myField` | `old('fields.myField')` |
| `entry.getErrors('title')` | `errors.get('title')` |
| `entry` | `session().get('entry')`, an array of the entry’s attributes. The key is the **Entry Variable Name** setting. |
| `craft.app.session.getFlash('error')` | `session().get('error')` |

The default messages changed to “The entry was saved successfully.” and “Your submission could not be saved. Please review it for errors.” Your `successMessage` and `failMessage` parameters are still used.

Ajax responses keep their shape: `message`, `modelName`, `modelClass`, the entry data under the **Entry Variable Name** key, plus `modelId` on success and `errors` on failure.

New entries get the revision note “Created by the Guest Entries plugin.”

### Database and migrations

The plugin has no tables. Its old settings migrations were removed, and its schema version is still 2.1.0, so nothing runs when you update.

## Removed

- `craft\guestentries\events\SaveEvent` and the `SaveController::EVENT_*` constants. See [Events](#events).
- `craft\guestentries\models\SectionSettings`.
- Craft 4 and 5 support. Use Guest Entries 4.x with those versions.

## Need help?

Open an issue at https://github.com/craftcms/guest-entries/issues.
