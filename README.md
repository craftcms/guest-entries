# Guest Entries for Craft CMS

Allow guests to create entries from your site’s front end.

## Requirements

_Guest Entries_ requires Craft CMS 6.0.0-alpha.1 or later, and PHP 8.5 or later.

Using Craft 4 or 5? Stay on the 4.x line of this plugin. Upgrading a Craft 5 project that already has Guest Entries installed? See the [upgrade guide](UPGRADE.md).

## Installation

You can install _Guest Entries_ from the [Plugin Store](https://plugins.craftcms.com/guest-entries) or with Composer.

### From the Plugin Store

Go to the **Plugin Store** in your project’s control panel (in an environment that allows [admin changes](https://craftcms.com/docs/4.x/config/general.html#allowadminchanges)), search for “Guest Entries,” then click **Install**.

### With Composer

Open your terminal and run the following commands:

```bash
# Navigate to your project directory:
cd /path/to/my-project

# Require the plugin package with Composer:
composer require craftcms/guest-entries -w

# Install the plugin with Craft:
php craft plugin/install guest-entries
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
    {{ _self.errorList(errors.get('body')) }}

    {# ... #}

    <button type="submit">Publish</button>
</form>
```

> **Note**  
> The process of submitting data and handling success and error states is outlined in the [controller actions](https://craftcms.com/docs/4.x/dev/controller-actions.html) documentation.

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
`postDate` | Value should be processable by [`DateTimeHelper::toDateTime()`][api:date-time-helper] | 
`expiryDate` | Value should be processable by [`DateTimeHelper::toDateTime()`][api:date-time-helper] | 
`parentId` | Nest this entry under another. Invalid for channels and structures with a maximum depth of `1`. | 
`siteId` | Create the entry in a specific site. | 
`enabledForSite` | Whether the entry should be enabled in this site. The global `enabled` setting is configurable by administrators, so this alone will not immediately publish something. | 
`fields[...]` | Any [custom fields](#sending-custom-fields) you want guests to be able to populate. | 

[api:date-time-helper]: https://docs.craftcms.com/api/v4/craft-helpers-datetimehelper.html#method-todatetime
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
    value="{{ entry ? entry.myCustomFieldHandle }}">
```

If entries in the designated section are enabled by default, validation will occur on _all_ custom fields, meaning those marked as _required_ in the entry type’s field layout must be sent with the submission. Refer to the [field types][docs:field-types] documentation to learn about the kinds of values that Craft accepts.

> **Warning**  
> Omitting a field from your form does not mean it is safe from tampering! Clever users may be able to modify the request payload and submit additional field data. If this presents a problem for your site, consider using an [event](#events) to clear values or reject submissions.

#### Validation Errors

If the submission can’t be saved, the user is sent back to the page they came from. Use Laravel’s session helpers to repopulate the form and show what went wrong:

- `old('title')` and `old('fields.body')` return the submitted values.
- `errors.get('title')` returns the validation errors for an attribute, and `errors.has('title')` tells you whether there are any.
- `session().get('error')` is the flash message (“Couldn’t save entry.”).
- `session().get('entry')` holds the submitted entry’s data, under the name set by the “Entry Variable” [setting](#settings) (`entry` by default). It is an array, not an element, so it is mostly useful for debugging; prefer `old()` and `errors`.

> **Note**  
> The entry variable name can be changed with the `entryVariable` setting. This might be necessary if the page already uses a variable of that name.

#### Redirection

Send a `redirect` param to send the user to a specific location upon successfully saving an entry. In the example above, this is handled via the [`redirectInput('...')` function](https://craftcms.com/docs/4.x/dev/functions.html#redirectinput). The path is evaluated as an [object template](https://craftcms.com/docs/4.x/dev/controller-actions.html#after-a-post-request), and can include properties of the saved entry in `{curlyBraces}`.

### Submitting via Ajax

If you submit your form via Ajax with an `Accept: application/json` header, a JSON response is returned. The HTTP status tells you the outcome: `200` when the entry was saved, `400` when it was not.

Key | Notes
--- | -----
`message` | A success or failure message.
`modelName` | `entry`, or the configured entry variable name when the submission failed.
`modelClass` | The entry class.
`modelId` | The entry’s ID (if saved).
`entry` | All of the entry’s data.
`errors` | Validation errors indexed by attribute name (if the entry was invalid).

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

In order to display an entry that is _disabled_, you will need to set up a custom [route](https://craftcms.com/docs/4.x/routing.html#advanced-routing-with-url-rules)…

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

_Guest Entries_ dispatches a few [Laravel events](https://craftcms.com/docs/6.x/extend/events.html) of its own, allowing developers to customize the submission process.

Register listeners in a service provider’s `boot()` method (for a project, `app/Providers/AppServiceProvider.php`), or in your own plugin’s `boot()` method.

### `SavingGuestEntry`

Dispatched _before_ a guest entry is saved. This is an opportunity to flag the submission as spam, or to reject it:

```php
use craft\guestentries\events\SavingGuestEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

// ...

Event::listen(function(SavingGuestEntry $event): void {
    // Get a reference to the entry object:
    $entry = $event->entry;

    // Perform spam detection logic of your own design:
    if (Str::contains($entry->title, 'synergy', ignoreCase: true)) {
        // Pretend it worked, but discard the entry:
        $event->isSpam = true;
    }

    // Or reject it, so the submission fails:
    // $event->isValid = false;
});
```

### `SavedGuestEntry`

Dispatched _after_ a guest entry is saved, or after a spam submission is discarded:

```php
use craft\guestentries\events\SavedGuestEntry;
use Illuminate\Support\Facades\Event;

// ...

Event::listen(function(SavedGuestEntry $event): void {
    // Grab the entry
    $entry = $event->entry;

    // Was it flagged as spam?
    $isSpam = $event->isSpam;
});
```

### `GuestEntrySaveFailed`

Dispatched right after a submission is determined to be invalid, or rejected by a listener:

```php
use craft\guestentries\events\GuestEntrySaveFailed;
use Illuminate\Support\Facades\Event;

// ...

Event::listen(function(GuestEntrySaveFailed $event): void {
    // Grab the entry
    $entry = $event->entry;

    // Get any validation errors
    $errors = $entry->errors()->getMessages();
});
```
