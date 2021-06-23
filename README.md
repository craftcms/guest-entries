# Guest Entries for Craft CMS

This plugin allows you to save guest entries from your site’s front end.

## Requirements

This plugin requires Craft CMS 3.4.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Guest Entries”. Then press **Install** in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/guest-entries

# tell Craft to install the plugin
php craft plugin/install guest-entries
```

## Settings

From the plugin settings page, you can configure which sections should allow guest entry submissions, the default entry authors and statuses, and whether submissions should be validated before being accepted.

## Usage

Your guest entry template can look something like this:

```twig
{% macro errorList(errors) %}
    {% if errors %}
        {{ ul(errors, {class: 'errors'}) }}
    {% endif %}
{% endmacro %}

{% set entry = entry ?? null %}

<form method="post" action="" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('guest-entries/save') }}
    {{ hiddenInput('sectionUid', '3b78a784-8b78-4442-8774-0b33bcb11733') }}
    {{ redirectInput('success') }}

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

    <button type="submit">Publish</button>
</form>
```

You’ll need to adjust the hidden `sectionUid` input to point to the section you’d like to post guest entries to.

If you have a `redirect` input, the user will get redirected to its location upon successfully saving the entry.

If there are validation errors on the entry, the page will be reloaded with an `entry` variable available to it. That `entry` variable will be a `craft\elements\Entry` model representing the submitted entry. You can fetch the posted values from that variable, as well as any validation errors via [`getErrors()`], [`getFirstError()`], or [`getFirstErrors()`]. (The name of this variable is configurable via the “Entry Variable Name” setting.)

[`getErrors()`]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getErrors()-detail
[`getFirstError()`]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getFirstError()-detail
[`getFirstErrors()`]: http://www.yiiframework.com/doc-2.0/yii-base-model.html#getFirstErrors()-detail

### Submitting via Ajax

If you submit your form via Ajax with an `Accept: application/json` header, a JSON response will be returned with the following keys:

- `success` _(boolean)_ – Whether the entry was saved successfully
- `errors` _(object)_ – All of the validation errors indexed by field name (if not saved)
- `id` _(string)_ – the entry’s ID (if saved)
- `title` _(string)_ – the entry’s title (if saved)
- `authorUsername` _(string)_ – the entry’s author’s username (if saved)
- `dateCreated` _(string)_ – the entry’s creation date in ISO 8601 format (if saved)
- `dateUpdated` _(string)_ – the entry’s update date in ISO 8601 format (if saved)
- `postDate` _(string, null)_ – the entry’s post date in ISO 8601 format (if saved and enabled)
- `url` _(string, null)_ – the entry’s public URL (if saved, enabled, and in a section where entries have URLs)

### The `beforeSaveEntry` event

Plugins can be notified right before a guest entry is saved using the `beforeSaveEntry` event. This is also an opportunity to flag the submission as spam, preventing it from getting saved:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(
    SaveController::class,
    SaveController::EVENT_BEFORE_SAVE_ENTRY,
    function(SaveEvent $e) {
        // Grab the entry
        $entry = $e->entry;

        $isSpam = // custom spam detection logic...
        
        if (!$isSpam) {
            $e->isSpam = true;
        }
    }
);
```

### The `afterSaveEntry` event

Plugins can be notified right after a guest entry is saved using the `afterSaveEntry` event:

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
