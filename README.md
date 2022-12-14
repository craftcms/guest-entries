# Guest Entries for Craft CMS

Allow guests to create entries from your site’s front end.

## Requirements

_Guest Entries_ requires Craft CMS 4.0 or later.

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

{# Default value for the `entry` variable: #}
{% set entry = entry ?? null %}

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
    {{ input('text', 'title', entry ? entry.title, { id: 'title' }) }}
    {{ entry ? _self.errorList(entry.getErrors('title')) }}

    <label for="body">Body</label>
    {{ tag('textarea', {
        text: entry ? entry.body,
        id: 'body',
        name: 'fields[body]',
    }) }}
    {{ entry ? _self.errorList(entry.getErrors('body')) }}

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
{% set targetSection = craft.app.sections.getSectionByHandle('resources') %}
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

If there are validation errors on the entry, the page will be reloaded with the populated [`craft\elements\Entry`][api:entry] object available under an `entry` variable. You can access the posted values from that object as though it were a normal entry—or display errors with [`getErrors()`][yii:base-model-getErrors], [`getFirstError()`][yii:base-model-getFirstError], or [`getFirstErrors()`][yii:base-model-getFirstErrors].

> **Note**  
> The `entry` variable can be renamed with the “Entry Variable Name” [setting](#settings) in the control panel. This might be necessary if you want to use a form on an entry page that already injects a variable of that name.

[api:entry]: https://docs.craftcms.com/api/v4/craft-elements-entry.html
[yii:base-model-getErrors]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getErrors()-detail
[yii:base-model-getFirstError]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getFirstError()-detail
[yii:base-model-getFirstErrors]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getFirstErrors()-detail

#### Redirection

Send a `redirect` param to send the user to a specific location upon successfully saving an entry. In the example above, this is handled via the [`redirectInput('...')` function](https://craftcms.com/docs/4.x/dev/functions.html#redirectinput). The path is evaluated as an [object template](https://craftcms.com/docs/4.x/dev/controller-actions.html#after-a-post-request), and can include properties of the saved entry in `{curlyBraces}`.

### Submitting via Ajax

If you submit your form [via Ajax](https://craftcms.com/docs/4.x/dev/controller-actions.html#ajax) with an `Accept: application/json` header, a JSON response will be returned with the following keys:

- `success` _(boolean)_ – Whether the entry was saved successfully
- `errors` _(object)_ – All of the validation errors indexed by field name (if not saved)
- `id` _(string)_ – the entry’s ID (if saved)
- `title` _(string)_ – the entry’s title (if saved)
- `authorUsername` _(string)_ – the entry’s author’s username (if saved)
- `dateCreated` _(string)_ – the entry’s creation date in ISO 8601 format (if saved)
- `dateUpdated` _(string)_ – the entry’s update date in ISO 8601 format (if saved)
- `postDate` _(string, null)_ – the entry’s post date in ISO 8601 format (if saved and enabled)
- `url` _(string, null)_ – the entry’s public URL (if saved, enabled, and in a section that has URLs)

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

_Guest Entries_ augments the normal [events](https://craftcms.com/docs/4.x/extend/events.html) emitted during the entry lifecycle with a few of its own, allowing developers to customize the submission process.

The following snippets should be added to your plugin or module’s `init()` method, per the official [event usage instructions](https://craftcms.com/knowledge-base/custom-module-events).

### The `beforeSaveEntry` event

Plugins can be notified _before_ a guest entry is saved, using the `beforeSaveEntry` event. This is also an opportunity to flag the submission as spam, and prevent it being saved:

```php
use craft\helpers\StringHelper;
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(
    SaveController::class,
    SaveController::EVENT_BEFORE_SAVE_ENTRY,
    function(SaveEvent $e) {
        // Get a reference to the entry object:
        $entry = $e->entry;

        // Perform spam detection logic of your own design:
        if (StringHelper::contains($entry->title, 'synergy', false)) {
            // Set the event property:
            $e->isSpam = true;
        }
    }
);
```

### The `afterSaveEntry` event

Plugins can be notified _after_ a guest entry is saved, using the `afterSaveEntry` event:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(
    SaveController::class,
    SaveController::EVENT_AFTER_SAVE_ENTRY,
    function(SaveEvent $e) {
        // Grab the entry
        $entry = $e->entry;

        // Was it flagged as spam?
        $isSpam = $e->isSpam;
    }
);
```

### The `afterError` event

Plugins can be notified right after a submission is determined to be invalid using the `afterError` event:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(
    SaveController::class,
    SaveController::EVENT_AFTER_ERROR,
    function(SaveEvent $e) {
        // Grab the entry
        $entry = $e->entry;

        // Get any validation errors
        $errors = $entry->getErrors();
    }
);
```
