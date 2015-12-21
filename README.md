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

If there is a validation error on the entry, then the page will be releaded with a `entry` variable available to it, set to an [EntryModel](http://buildwithcraft.com/docs/templating/entrymodel) describing the submitted entry. You can fetch the posted values from that variable, as well as any validation errors via [`entry.getError()`](http://www.yiiframework.com/doc/api/1.1/CModel#getError-detail), [`getErrors()`](http://www.yiiframework.com/doc/api/1.1/CModel#getErrors-detail), or [`getAllErrors()`](http://buildwithcraft.com/classreference/models/BaseModel#getAllErrors-detail). (The name of this variable is configurable via the `entryVariable` config setting.)

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


## Configuration

Guest Entries has the following config settings:

- `entryVariable` - The name of the variable that submitted entries should be assigned to when the template is reloaded in the event of a validation error. Default is `'entry'`.

To override Guest Entries’ config settings, create a new `guestentries.php` file in your craft/config/ folder, which returns an array of your custom config values.

```php
<?php

return array(
    'entryVariable' => 'guestEntry',
);
```


## Changelog

### 1.4

- Updated to take advantage of new Craft 2.5 plugin features.

### 1.3.1

- Fixed a bug where the “Validate Entry” setting Lightswitch would reset to `on` position after being set to `off`.

### 1.3

- Added the `entryVariable` config setting.

### 1.2.2

- Fixed a bug where validation would fail when saving guest entries for sections/entry types with dynamic titles.

### 1.2.1

- Added the ability to explicitly set whether validation is required on a per-section basis.

### 1.2

- Added support for the Client user when running Craft Client.

### 1.1

- Added GuestEntriesService.php to raise an ‘onBeforeSave’ event before saving a new guest entry.

### 1.0

* Initial release
