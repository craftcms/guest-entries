# Guest Entries plugin for Craft

This plugin allows you to save guest entries from the front-end of your website.

## Requirements

This plugin requires Craft 2.0+.

## Installation

To install Guest Entries, follow these steps:

1.  Upload the guestentries/ folder to your craft/plugins/ folder.
2.  Go to Settings > Plugins from your Craft control panel and enable the Guest Entries plugin.
3.  Click on “Guest Entries” to go to the plugin’s settings page, and configure the plugin how you’d like.

## Settings

From the plugin settings page, you can configure which sections you want to allow guest entry submission to along with who the default author will be.

Every user you see in the default author list has “createEntry” permissions for the section.

## Usage

Your guest entry template can look something like this:

```jinja
<form method="post" action="" accept-charset="UTF-8">
    {{ getCsrfInput() }}
    <input type="hidden" name="action" value="guestEntries/saveEntry">
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

If there is a validation error on the entry’s content, then the page will be reloaded with an `entry` variable available to it, set to an [EntryModel](http://buildwithcraft.com/docs/templating/entrymodel) describing the submitted entry. You can fetch the posted values from that variable, as well as any validation errors via [`entry.getError()`](http://www.yiiframework.com/doc/api/1.1/CModel#getError-detail), [`getErrors()`](http://www.yiiframework.com/doc/api/1.1/CModel#getErrors-detail), or [`getAllErrors()`](http://buildwithcraft.com/classreference/models/BaseModel#getAllErrors-detail). (The name of this variable is configurable via the `entryVariable` config setting.)

### Submitting via Ajax
Submitting a `guestEntries/saveEntry` form action via ajax responds with an object with the following keys:

- `success` (boolean) - true
- `id` (string) - id of the entry saved
- `title` (string) - title of the entry saved
- `cpEditUrl` (string) - returned if the request came from the control panel
- `authorUsername` (string) - author username of the entry saved
- `dateCreated` (string) - ISO 8601 standard date and time format of the date the entry was created
- `dateUpdated` (string) - ISO 8601 standard date and time format of the date the entry was updated
- `postDate` (string) - if the entry is disabled by default, this will be null
- `url` (string) - live URL of the entry saved if it has a URL

### The `guestEntries.beforeSave` event

Other plugins can be notified right before a guest entry is saved with the Guest Entries plugin,
and they are even given a chance to prevent the entry from saving at all.

```php
class SomePlugin extends BasePlugin
{
    // ...

    public function init()
    {
        craft()->on('guestEntries.beforeSave', function(GuestEntriesEvent $event) {
            $entryModel = $event->params['entry'];

            // ...

            if ($isVulgar)
            {
                // Setting $isValid to false will cause a validation error
                // and prevent the entry from being saved.

                $entryModel->addError('title', 'Do you kiss your mother with those lips?');
                $event->isValid = false;
            }

            if ($isSpam)
            {
                // Setting $fakeIt to true will make things look as if the entry was saved,
                // but really it wasn't

                $event->fakeIt = true;
            }
        });
    }
}
```

### `guestEntries.success` and `guestEntries.error` events

Plugins can also listen to `success` and `error` events that get fired when a guest entry successfully gets saved or not.

Each of them has an `entry` parameter where you can access the `EntryModel` of the guest entry.

Additionally, `success` has a `faked` parameter so you can tell whether the success was a real one or a faked one.

## Configuration

Guest Entries has the following config settings:

- `entryVariable` - The name of the variable that submitted entries should be assigned to when the template is reloaded in the event of a validation error. Default is `'entry'`.

To override Guest Entries’ config settings, create a new file in your `craft/config` folder called `guestentries.php`, at `craft/config/guestentries.php`.  That file should returns an array of your custom config values.

```php
<?php

return array(
    'entryVariable' => 'guestEntry',
);
```
