# Guest Entries for Craft CMS

This plugin allows you to save guest entries from the front-end of your website.

## Requirements

This plugin requires Craft CMS 3.1.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Guest Entries”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/guest-entries

# tell Craft to install the plugin
./craft install/plugin guest-entries
```

## Settings

From the plugin settings page, you can configure which sections you want to allow guest entry submissions for, as well as the default entry authors and statuses, and whether submissions should be validated before being accepted.

## Usage

Your guest entry template can look something like this:

```twig
{% macro errorList(errors) %}
    {% if errors %}
        <ul class="errors">
            {% for error in errors %}
                <li>{{ error }}</li>
            {% endfor %}
        </ul>
    {% endif %}
{% endmacro %}

{% from _self import errorList %}

<form method="post" action="" accept-charset="UTF-8">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="guest-entries/save">
    <input type="hidden" name="sectionId" value="3">
    {{ redirectInput('success') }}

    <label for="title">Title</label>
    <input id="title" type="text" name="title"
        {%- if entry is defined %} value="{{ entry.title }}"{% endif -%}>
    
    {% if entry is defined %}
        {{ errorList(entry.getErrors('title')) }}
    {% endif %}

    <label for="body">Body</label>
    <textarea id="body" name="fields[body]">
        {%- if entry is defined %}{{ entry.body }}{% endif -%}
    </textarea>
    
    {% if entry is defined %}
        {{ errorList(entry.getErrors('body')) }}
    {% endif %}

    <input type="submit" value="Publish">
</form>
```

You will need to adjust the hidden `sectionId` input to point to the section you would like to post guest entries to.

If you have a `redirect` hidden input, the user will get redirected to it upon successfully saving the entry.

If there is a validation error on the entry, then the page will be reloaded with an `entry` variable available to it, set to a `craft\elements\Entry` model representing the submitted entry. You can fetch the posted values from that variable, as well as any validation errors via [`getErrors()`], [`getFirstError()`], or [`getFirstErrors()`]. (The name of this variable is configurable via the “Entry Variable Name” setting.)

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

Event::on(SaveController::class, SaveController::EVENT_BEFORE_SAVE_ENTRY, function(SaveEvent $e) {
    // Grab the entry
    $entry = $e->entry;

    $isSpam = // custom spam detection logic...
    
    if (!$isSpam) {
        $e->isSpam = true;
    }
});
```

### The `afterSaveEntry` event

Plugins can be notified right after a guest entry is saved using the `afterSaveEntry` event:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(SaveController::class, SaveController::EVENT_AFTER_SAVE_ENTRY, function(SaveEvent $e) {
    // Grab the entry
    $entry = $e->entry;
    
    // Was it flagged as spam?
    $isSpam = $e->isSpam;
});
```

### The `afterError` event

Plugins can be notified right after a submission is determined to be invalid using the `afterError` event:

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;
use yii\base\Event;

// ...

Event::on(SaveController::class, SaveController::EVENT_AFTER_ERROR, function(SaveEvent $e) {
    // Grab the entry
    $entry = $e->entry;

    // Get any validation errors
    $errors = $entry->getErrors();
});
```
