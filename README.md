# Guest Entries plugin for Craft

This plugin allows you to save guest entries from the front-end of your website.

## Requirements

This plugin requires Craft CMS 3.0.0-beta.20 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require craftcms/guest-entries

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Guest Entries.

## Settings

From the plugin settings page, you can configure which sections you want to allow guest entry submission to along with who the default author will be.

Every user you see in the default author list has “createEntry” permissions for the section.

## Usage

Your guest entry template can look something like this:

```jinja
<form method="post" action="" accept-charset="UTF-8">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="guest-entries/save ">
    <input type="hidden" name="redirect" value="success">
    <input type="hidden" name="sectionId" value="3">

    <label for="title">Title</label>
    <input id="title" type="text" name="title">

    <label for="body">Body</label>
    <textarea id="body" name="fields[body]"></textarea>

    <input type="submit" value="Publish">
</form>
```

You will need to adjust the hidden “sectionId” input to point to the section you would like to post guest entries to.

If you have a “redirect” hidden input, the user will get redirected to it upon successfully saving the entry.

If there is a validation error on the entry, then the page will be reloaded with an `entry` variable available to it, set to an [EntryModel](http://craftcms.com/docs/templating/entrymodel) describing the submitted entry. You can fetch the posted values from that variable, as well as any validation errors via [`entry.getError()`](http://www.yiiframework.com/doc/api/1.1/CModel#getError-detail), [`getErrors()`](http://www.yiiframework.com/doc/api/1.1/CModel#getErrors-detail), or [`getAllErrors()`](http://buildwithcraft.com/classreference/models/BaseModel#getAllErrors-detail). (The name of this variable is configurable via the `entryVariable` config setting.)

### Submitting via Ajax
Submitting a `guest-entries/save` form action via ajax responds with an object with the following keys:

- `success` (boolean) - true
- `id` (string) - id of the entry saved
- `title` (string) - title of the entry saved
- `cpEditUrl` (string) - returned if the request came from the control panel
- `authorUsername` (string) - author username of the entry saved
- `dateCreated` (string) - ISO 8601 standard date and time format of the date the entry was created
- `dateUpdated` (string) - ISO 8601 standard date and time format of the date the entry was updated
- `postDate` (string) - if the entry is disabled by default, this will be null
- `url` (string) - live URL of the entry saved if it has a URL

### The `beforeSaveEntry` event

Other plugins can be notified right before a guest entry is saved with the Guest Entries plugin,
and they are even given a chance to prevent the entry from saving at all.

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SendEvent;
use yii\base\Event;

// ...

Event::on(SaveController::class, SaveController::EVENT_BEFORE_SAVE_ENTRY, function(SendEvent $e) {
    // Do we want to pretend like this worked?
    $e->fakeIt = true;

    // Do we want to stop the process?
    $e->isValid = true;

    // Grab the Guest Entry
    $entry = $e->entry;
});
```

### The `afterSaveEntry` event

Other plugins can be notified right after a guest entry is saved with the Guest Entries plugin, and
they can see if it was a faked save or not.

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SendEvent;
use yii\base\Event;

// ...

Event::on(SaveController::class, SaveController::EVENT_AFTER_SAVE_ENTRY, function(SendEvent $e) {
    // Was this entry faked?
    $faked = $e->faked;

    // Grab the Guest Entry
    $entry = $e->entry;
});
```

### The `onError` event

Plugins can also listen for an  `onError` event that gets fired when a guest entry cannot be saved.

It has an `entry` parameter where you can access any validation errors for the Guest Entry.

```php
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SendEvent;
use yii\base\Event;

// ...

Event::on(SaveController::class, SaveController::EVENT_ON_ERROR, function(SendEvent $e) {
    // Grab the Guest Entry
    $entry = $e->entry;

    // Get any validation errors
    $errors = $entry->getErrors();
});
```

## Configuration

Guest Entries has the following config settings:

- `entryVariable` - The name of the variable that submitted entries should be assigned to when the template is reloaded in the event of a validation error. Default is `'entry'`.

To override Guest Entries’ config settings, create a new file in your `craft/config` folder called `guest-entries.php`, at `craft/config/guest-entries.php`.  That file should returns an array of your custom config values.

```php
<?php

return [
    'entryVariable' => 'guestEntry',
];
```
