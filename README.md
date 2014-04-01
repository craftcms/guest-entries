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

    <form method="post" action="" accept-charset="UTF-8">
        <input type="hidden" name="action" value="guestEntries/saveEntry">
        <input type="hidden" name="redirect" value="success">
        <input type="hidden" name="sectionId" value="3">

        <label for="title">Title</label>
        <input id="title" type="text" name="title">

        <label for="body">Body</label>
        <textarea id="body" name="fields[body]"></textarea>

        <input type="submit" value="Publish">
    </form>


You will need to adjust the hidden “sectionId” input to point to the section you would like to post guest entries to.

If you have a “redirect” hidden input, the user will get redirected to it upon successfully saving the entry.

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

## Changelog

### 1.0

* Initial release
