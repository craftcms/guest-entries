# Guest Entries for Craft CMS

Allow guests to create entries from your site’s front end.

## Requirements

_Guest Entries_ 5.x requires Craft CMS 6.0.0+ and PHP 8.5+. See [UPGRADE.md](UPGRADE.md) if you are upgrading from an earlier version.

## Installation

You can install _Guest Entries_ from the [Plugin Store](https://plugins.craftcms.com/guest-entries) or with Composer.

### From the Plugin Store

Go to the **Plugin Store** in your project’s control panel (in an environment that allows admin changes), search for “Guest Entries,” then click **Install**.

### With Composer

Open your terminal and run the following commands:

```bash
# Navigate to your project directory:
cd /path/to/my-project

# Require the plugin package with Composer:
composer require craftcms/guest-entries -w

# Install the plugin with Craft:
php artisan craft:plugin:install guest-entries
```

## Settings

From the plugin settings page, you can configure…
- …which sections should allow guest entry submissions;
- …the default entry authors and statuses;
- …and whether submissions should be validated before being accepted.

## Usage

A basic guest entry template should look something like this:

```twig
{# Macro to help output errors: #}
{% macro errorList(errors) %}
    {% if errors %}
        {{ ul(errors, { class: 'errors' }) }}
    {% endif %}
{% endmacro %}

<form method="post" action="" accept-charset="UTF-8">
    {# Hidden inputs required for the form to work: #}
    {{ csrfInput() }}
    {{ actionInput('guest-entries/save') }}

    {# Custom redirect URI: #}
    {{ redirectInput('success') }}

    {# Section for new entries: #}
    {{ hiddenInput('sectionHandle', 'mySectionHandle') }}

    {# Entry properties and custom fields: #}
    <label for="title">Title</label>
    {{ input('text', 'title', old('title'), { id: 'title' }) }}
    {{ _self.errorList(errors.get('title')) }}

    <label for="body">Body</label>
    {{ tag('textarea', {
        text: old('fields.body'),
        id: 'body',
        name: 'fields[body]',
    }) }}
    {{ _self.errorList(errors.get('fields.body')) }}

    {# ... #}

    <button type="submit">Publish</button>
</form>
```

> **Note**  
> A failed submission redirects back to the page with the posted data and validation errors flashed to the session. Use `old()`, `errors` and `session().get('error')` in your template, as shown above. A successful submission redirects to the `redirect` param, and the page can read `session().get('success')`.

### Supported Params

The following parameters can be sent with a submission:

Name | Notes | Required
---- | ----- | --------
`sectionHandle` | Determines what section the entry will be created in. | ✓
`sectionUid` | Can be sent in lieu of `sectionHandle`. | 
`sectionId` | Can be sent in lieu of `sectionHandle`. | 
`typeId` | Entry type ID to use. This may affect which custom fields are required. When absent, the first configured type for the specified section is used. | 
`title` | Optional if the section has automatic title formatting enabled. | ✓
`slug` | Explicitly sets the new entry’s slug. | 
`postDate` | Value should be processable by `CraftCms\Cms\Support\DateTimeHelper::toDateTime()` | 
`expiryDate` | Value should be processable by `CraftCms\Cms\Support\DateTimeHelper::toDateTime()` | 
`parentId` | Nest this entry under another. Invalid for channels and structures with a maximum depth of `1`. | 
`siteId` | Create the entry in a specific site. | 
`enabledForSite` | Whether the entry should be enabled in this site. The global `enabled` setting is configurable by administrators, so this alone will not immediately publish something. | 
`fields[...]` | Any [custom fields](#sending-custom-fields) you want guests to be able to populate. | 

[docs:field-types]: https://craftcms.com/docs/4.x/fields.html#field-types

### Form Tips

#### Specifying a Section + Entry Type

The plugin determines what section the new entry is created in by looking for a `sectionHandle`, `sectionUid`, or `sectionId` param, _in this order_. Entry types, on the other hand, can only be defined by a `typeId` param—but because IDs [can be unstable](https://craftcms.com/docs/4.x/project-config.html#ids-uuids-and-handles) between environments, you must look them up by a known identifier.

Granted you will already have a section (or at least a section _handle_), the easiest way to do this is via the section model:

```twig
{% set targetSection = Sections.getSectionByHandle('resources') %}
{% set entryTypes = targetSection.getEntryTypes() %}

{# Select a single type, identified by its handle: #}
{% set targetEntryType = collect(entryTypes).firstWhere('handle', 'document') %}

{{ hiddenInput('sectionId', targetSection.id) }}
{{ hiddenInput('typeId', targetEntryType.id) }}
```

#### Sending Custom Fields

Custom field data should be nested under the `fields` key, with the field name in `[squareBrackets]`:

```twig
<input
    type="text"
    name="fields[myCustomFieldHandle]"
    value="{{ old('fields.myCustomFieldHandle') }}">
```

If entries in the designated section are enabled by default, validation will occur on _all_ custom fields, meaning those marked as _required_ in the entry type’s field layout must be sent with the submission. Refer to the [field types][docs:field-types] documentation to learn about the kinds of values that Craft accepts.

> **Warning**  
> Omitting a field from your form does not mean it is safe from tampering! Clever users may be able to modify the request payload and submit additional field data. If this presents a problem for your site, consider using an [event](#events) to clear values or reject submissions.

#### Validation Errors

If there are validation errors on the entry, the user is redirected back to the page and the submitted data is flashed to the session. In your template:

- `old('title')` and `old('fields.body')` return the submitted values, to repopulate the form;
- `errors.get('title')`, `errors.has('title')` and `errors.all()` return the validation errors, keyed by attribute ;
- `session().get('error')` is the error message.

The `entry` variable from earlier versions is no longer available. The “Entry Variable Name” [setting](#settings) now only names the key that holds the entry's data in an Ajax error response.

#### Redirection

Send a `redirect` param to send the user to a specific location upon successfully saving an entry. In the example above, this is handled via the `redirectInput('...')` function. The path is evaluated as an object template, and can include properties of the saved entry in `{curlyBraces}` (for example `{id}`, `{uid}` or `{url}`).

### Submitting via Ajax

If you submit your form via Ajax with an `Accept: application/json` header, a JSON response will be returned. A successful submission has a `200` status, and a failed one has a `400` status.

Key | Notes
--- | -----
`message` | “Entry saved.” or “Couldn’t save entry.”
`modelName` | The key holding the entry data: `entry` on success, or the “Entry Variable Name” setting on failure.
`modelClass` | The entry’s class name.
`<modelName>` | The entry’s data, as an object.
`modelId` | The entry’s ID (success only).
`errors` | All of the validation errors, indexed by attribute name (failure only).

### Viewing Entries

Using a [`redirect`](#redirection) param allows you to show a user some or all of the content they just submitted—even if the entry is disabled, by default.

> **Warning**  
> Take great care when displaying untrusted content on your site, especially when subverting moderation processes!

#### Enabled by Default

Entries in sections with URLs can be viewed immediately, with this `redirect` param:

```twig
{{ redirectInput('{url}') }}
```

#### Disabled by Default

In order to display an entry that is _disabled_, you will need to set up a custom route in your project’s `config/craft/routes.php`…

```php
<?php

return [
    // This route uses the special `{uid}` token, which will
    // match any UUIDv4 generated by Craft:
    'submissions/confirmation/<entryUid:{uid}>' => ['template' => '_submissions/confirmation'],
];
```

…and direct users to it by including `{{ redirectInput('submissions/confirmation/{uid}') }}` in the entry form. Your template (`_submissions/confirmation.twig`) will be responsible for looking up the disabled entry and displaying it, based on the `entryUid` route token that Craft makes available:

```twig
{% set preview = craft.entries()
    .status('disabled')
    .section('documents')
    .uid(entryUid)
    .one() %}

{# Bail if it doesn’t exist: #}
{% if not preview %}
    {% exit 404 %}
{% endif %}

{# Supposing the user’s name was recorded in the `title` field: #}
<h1>Thanks, {{ preview.title }}!</h1>

<p>Your submission has been recorded, and is awaiting moderation.</p>
```

This query selects only `disabled` entries so that the “preview” is invalidated once the entry goes live. This “confirmation” URI does _not_ need to match the actual URI of the entry.

## Events

_Guest Entries_ dispatches a few Laravel events of its own, allowing developers to customize the submission process. Register listeners in your plugin’s `boot()` method, a service provider (such as `App\Providers\AppServiceProvider`), or a plugin’s `$events` property. Listeners receive the event only: there is no `sender`.

### `SavingGuestEntry`

Dispatched _before_ a guest entry is saved. This is an opportunity to flag the submission as spam (it is discarded, but the submitter sees a success response), or to reject it by setting `isValid` to `false`:

```php
use CraftCms\GuestEntries\Events\SavingGuestEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

Event::listen(function (SavingGuestEntry $event): void {
    // Perform spam detection logic of your own design:
    if (Str::contains($event->entry->title, 'synergy', ignoreCase: true)) {
        $event->isSpam = true;
    }
});
```

### `SavedGuestEntry`

Dispatched _after_ a guest entry is saved. It is also dispatched for submissions flagged as spam, in which case `isSpam` is `true`:

```php
use CraftCms\GuestEntries\Events\SavedGuestEntry;
use Illuminate\Support\Facades\Event;

Event::listen(function (SavedGuestEntry $event): void {
    $entry = $event->entry;
    $isSpam = $event->isSpam;
});
```

### `GuestEntrySaveFailed`

Dispatched right after a submission is rejected by a listener or fails to save:

```php
use CraftCms\GuestEntries\Events\GuestEntrySaveFailed;
use Illuminate\Support\Facades\Event;

Event::listen(function (GuestEntrySaveFailed $event): void {
    $entry = $event->entry;
    $errors = $entry->errors()->getMessages();
});
```
