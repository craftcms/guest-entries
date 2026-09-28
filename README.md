# Guest Entries for Craft CMS

Configure rules that allow anonymous “guest” users to create entries from your site’s front end.

> [!WARNING]
> You are viewing an unreleased version of this plugin, compatible only with Craft 6.x.

## Requirements

_Guest Entries_ requires Craft CMS 6.0.0 or later.
Earlier versions of the plugin support Craft 4.x and 5.x.

## Installation

You can install _Guest Entries_ from the [Plugin Store](https://plugins.craftcms.com/guest-entries) or with Composer.

### From the Plugin Store

Go to the **Plugin Store** in your project’s control panel (in an environment that allows [admin changes](https://craftcms.com/docs/5.x/reference/config/general.html#allowadminchanges)), search for “Guest Entries,” then click **Install**.

### With Composer

Open your terminal and run the following commands:

```bash
cd /path/to/my-project
ddev composer require craftcms/guest-entries -w
ddev artisan craft:plugin:install guest-entries
```

To get the unreleased dev version, replace the package constraint with `craftcms/guest-entries@5.x-dev`.

## Upgrading

The plugin has been completely rewritten for Craft 6.x, but [configuration](#settings) and [usage](#usage) are backward-compatible, when the adapter is installed.
A handful of [template changes](#template-changes) will be required to eject the adapter.
Please [report any issues](https://github.com/craftcms/guest-entries/issues) with the upgrade!

Developers who maintain [advanced submission routes](#advanced-routing) or use [event handlers](#events) to customize the plugin’s behavior should read the corresponding sections.
Concretely, we split the `craft\guestentries\events\SaveEvent` into separate [before](#the-savingguestentry-event) and [after](#the-savedguestentry-event) events, and the `EVENT_AFTER_ERROR` event is replaced by Laravel’s generic error handler.

### Template Changes

In Craft 6.x, the way you access submitted data and errors is slightly different.
When there are issues with submitted data (including validation errors when **Run validation?** is enabled), the client is redirected back to the originating page.

Because the initial submission request (POST) and the re-rendered form request (GET) are separate, the submitted data is preserved by flashing it to the session. 
As a result, you’ll restore that data for the user with the `old()` helper function:

## Settings

From the plugin settings page, you can configure:

- …which sections should allow guest entry submissions;
- …default entry authors and statuses;
- …whether submissions should be validated before being accepted;

Section configuration is stored in project config, and will be applied automatically in other environments.
The plugin does not automatically grant any permissions to unauthenticated users—you must explicitly allow creation of entries in each section.

### Overrides

Settings defined in a `config/craft/guest-entries.php` file override project config.
Craft combines these configuration arrays using a _shallow_ merge, meaning a `sections` key will completely clobber your section-by-section config.

See the bundled `config/guest-entries.php` stub for a list of valid keys.

## Usage

Once you’ve marked at least one section as eligible for creation via the front-end, you’ll need to build a form.

A basic guest entry template will look something like this:

```twig
{# Macro to help output errors: #}
{% macro errorList(field) %}
    {% if errors.has(field) %}
        {{ ul(errors.get(field), { id: "form-errors-#{field}" }) }}
    {% endif %}
{% endmacro %}

<form method="post" action="{{ url('guest-entries/save') }}" accept-charset="UTF-8">
    {# Hidden inputs required for the form to work: #}
    {{ csrfInput() }}

    {# Custom redirect URI: #}
    {{ redirectInput('success') }}
    
    {# Customize success flash message: #}
    {{ successMessageInput('Thanks for filling out the survey!') }}

    {# Section for new entries: #}
    {{ hiddenInput('sectionHandle', 'mySectionHandle') }}

    {# Entry properties and custom fields: #}
    <label for="title">Title</label>
    {{ input('text', 'title', old('title'), { id: 'title' }) }}
    {{ _self.errorList('title') }}

    <label for="body">Body</label>
    {{ tag('textarea', {
        text: old('body'),
        id: 'body',
        name: 'fields[body]',
    }) }}
    {{ _self.errorList('body') }}

    {# ... #}

    <button type="submit">Publish</button>
</form>
```

> [!NOTE]
> The process of submitting data and handling success and error states is outlined in the [forms](https://craftcms.com/docs/5.x/development/forms.html) documentation.

### Supported Params

The following parameters can be sent with a submission:

| Name                                          | Notes                                                                                                                                                                   | Required |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|
| `sectionHandle`, `sectionUid`, or `sectionId` | Determines what section the entry will be created in.                                                                                                                   | ✓        |
| `typeId`                                      | Entry type ID to use. This may affect which custom fields are required. When absent, the first configured type for the specified section is used.                       |          |
| `title`                                       | Optional if the section has automatic title formatting enabled.                                                                                                         | ✓        |
| `slug`                                        | Explicitly sets the new entry’s slug.                                                                                                                                   |          |
| `postDate`                                    | Value should be processable by [`DateTimeHelper::toDateTime()`][api:date-time-helper]                                                                                   |          |
| `expiryDate`                                  | Value should be processable by [`DateTimeHelper::toDateTime()`][api:date-time-helper]                                                                                   |          |
| `parentId`                                    | Nest this entry under another. Invalid for channels and structures with a maximum depth of `1`.                                                                         |          |
| `siteId`                                      | Create the entry in a specific site.                                                                                                                                    |          |
| `enabledForSite`                              | Whether the entry should be enabled in this site. The global `enabled` setting is configurable by administrators, so this alone will not immediately publish something. |          |
| `fields[...]`                                 | Any [custom fields](#sending-custom-fields) you want guests to be able to populate.                                                                                     |          |

[api:date-time-helper]: https://docs.craftcms.com/api/v5/craft-helpers-datetimehelper.html#method-todatetime
[docs:field-types]: https://craftcms.com/docs/5.x/system/fields.html#field-types

### Form Tips

#### Specifying a Section + Entry Type

The plugin determines what section the new entry is created in by looking for a `sectionId`, `sectionUid`, or `sectionHandle` param, _in this order_.
Entry types, on the other hand, can only be defined by a `typeId` param—but because IDs [can be unstable](https://craftcms.com/docs/5.x/system/project-config.html#ids-uuids-and-handles) between environments, you must look them up by a known identifier.

Granted you will already have a section (or at least a section _handle_), the easiest way to do this is via the section object:

```twig
{# Load a section using the Sections facade: #}
{% set targetSection = Sections.getSectionByHandle('tickets') %}

{# Access the supported entry types: #}
{% set entryTypes = targetSection.getEntryTypes() %}

{# Select a single type, identified by its handle: #}
{% set targetEntryType = collect(entryTypes).firstWhere('handle', 'question') %}

{{ hiddenInput('sectionId', targetSection.id) }}
{{ hiddenInput('typeId', targetEntryType.id) }}
```

You can also use the `entryType()` Twig function to directly fetch an entry type by its handle:

```twig
{% set type = entryType('question') %}

{{ hiddenInput('typeId', targetEntryType.id) }}
```

Be aware that the former will honor section-specific handle overrides, while the latter is only aware of the entry types’ original handles.

#### Sending Custom Fields

Custom field data should be nested under the `fields` key, with the field name in `[squareBrackets]`:

```twig
<input
    type="text"
    name="fields[myCustomFieldHandle]"
    value="{{ old('fields.myCustomFieldHandle') }}">
```

If entries in the designated section are enabled by default, validation will occur on _all_ custom fields, meaning those marked as _required_ in the entry type’s field layout must be sent with the submission.
Refer to the [field types][docs:field-types] documentation to learn about the kinds of values that Craft accepts.

> **Warning**  
> Omitting a field from your form does not mean it is safe from tampering! Clever users may be able to modify the request payload and submit additional field data. If this presents a problem for your site, consider using an [event](#events) to clear values or reject submissions.

#### Validation Errors

If there are validation errors on the entry, the page will be reloaded with the populated `\CraftCms\Cms\Entry\Elements\Entry` object available under a variable corresponding to the plugin’s **Entry Variable Name** setting (`entry`, by default). You can access the posted values from that object as though it were a normal entry—or display errors by accessing `errors().get('attrOrFieldName')`.

POSTed fields are also exposed via the `old()` helper:

```twig
{{ input('text', 'fields[body]', old('fields.body'), {}) }}
```

> [!TIP]
> The `entry` variable can be renamed with the **Entry Variable Name** [setting](#settings) in the control panel.
> This might be necessary if you want to use a form on an entry page that already injects a variable of that name.

General messages are flashed to the session.
Place this snippet on the form page, or in a parent layout to display `success` and `error` flashes:

```twig
{% set flashes = session().only(['success', 'error']) %}

{% if flashes is not empty %}
    <ul>
        {% for key, message in flashes %}
            <li class="{{ key }}">{{ message }} (<code>{{ key }}</code>)</li>
        {% endfor %}
    </ul>
{% endif %}
```

#### Redirection

Send a `redirect` param to send the user to a specific location upon successfully saving an entry.
In initial [usage](#usage) example, this is handled by the [`redirectInput('...')` function](https://craftcms.com/docs/5.x/reference/twig/functions.html#redirectinput).
The path is evaluated as an [object template](https://craftcms.com/docs/5.x/system/object-templates.html), and can include properties of the saved entry in `{curlyBraces}`.
See the [viewing entries](#viewing-entries) section for more redirection and routing examples.

### Submitting via Ajax

If you submit your form [via Ajax](https://craftcms.com/docs/5.x/development/forms.html#ajax) with an `Accept: application/json` header, a JSON response will be returned with the following keys:

- `message` _(string)_ — A general description of the request’s status.
- `entry` _(object)_ — A JSON representation of the entry as it was saved, or populated with data that did not validate. ⚠️ This key is determined by the **Entry Variable Name** setting, which is sent under a consistent `modelName` key.
- `modelName` _(string)_ — The location of the returned entry data in the JSON object.
- `modelClass` _(string)_ — The entry’s fully-qualified PHP class name. This should always be `\CraftCms\Cms\Entry\Elements\Entry`.

When there is an issue saving the entry, an additional item is available…

- `errors` _(object)_ — All of the validation errors indexed by field name. Present only when validation errors occur.

…and when an entry is successfully saved, you’ll receive…

- `modelId` _(int)_ — The new entry’s ID.

The JSON response is sent with an appropriate HTTP response code: 200 for success, or 400-level for failure.
A 500-level code indicates there was a lower-level failure (not typically something to do with your request).

### Viewing Entries

Using a [`redirect`](#redirection) param allows you to show a user some or all of the content they just submitted—even if the entry is disabled, by default.

#### Enabled by Default

> [!WARNING]  
> Take great care when displaying untrusted content on your site!
> Without a moderation process in place, immediately publishing content from unauthenticated users can lead to spam, phishing, or other nefarious activity.

Entries in sections with URLs can be viewed immediately, with this `redirect` param:

```twig
{{ redirectInput('{url}') }}
```

If the section does _not_ have URLs, you can use the same strategy that is shown below, for entries that are disabled by default. Be sure and leave out the `.status('disabled')` query constraint!

#### Disabled by Default

In order to display an entry that is _disabled_, you will need to set up a custom [route](https://craftcms.com/docs/5.x/system/routing.html#advanced-routing-with-url-rules):

```php
# routes/web.php
Route::view('/contact/confirmation/{entryUid}', '_submission/confirmation')
    ->whereUuid('entryUid');
```

…and direct users to it by including `{{ redirectInput('contact/confirmation/{uid}') }}` in the entry form.
The template is then responsible for using the provided `entryUid` route parameter to look up the entry:

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

> [!NOTE]
> This query selects only `disabled` entries so that the “preview” is invalidated once the entry goes live.
> This “confirmation” URI does _not_ need to match the actual URI of the entry; in fact, they don’t even need to be in a section that has URLs!
> If adopting this behavior as-is, make sure the **Enable entries by default?** setting is _off_ for any sections that use this confirmation page.

#### Advanced Routing

In Craft 6.x, routes can handle lookups and other controller-like tasks directly in a closure:

```php
Route::get('/contact/confirmation/{entryUid}', function (int $entryUid) {
    $entry = Entry::find()->uid($entryUid)->one();

    if (! $entry) {
        abort(404, t('The submission could not be found!', 'site'));
    }

    return pageTemplate('_submissions/confirmation', ['entry' => $entry]);
})->whereUuid('entryUid');
```

Here, your template (`_submissions/confirmation.twig`) receives a complete entry element (instead of just its UID), as well as any other values you pass to `pageTemplate()`; the template can remain free of queries and error handling, and confidently output entry data.

#### Sensitive Information

Take care when outputting sensitive or personal data in a confirmation.
If some details _must_ be shown to the user, consider redacting them after a certain amount of time:

```twig
{% set elapsed = now.getTimestamp() - preview.dateCreated.getTimestamp() %}
{% set maxAge = 60 * 60 * 24 * 5 %}

Hello, {{ preview.submittedByName }}!

{% if elapsed < maxAge %}
  {# The entry was created recently; display context. #}
{% else %}
  {# The entry is more than five days old; redact sensitive details. #}
{% endif %}
```

## Events

_Guest Entries_ augments the normal [events](https://craftcms.com/docs/6.x/extend/events.html) emitted during the entry lifecycle with a few of its own, allowing developers to customize the submission process.

The following snippets can be added to your app’s `boot()` method, or converted to dedicated [listeners](https://laravel.com/docs/13.x/events#defining-listeners).

### The `SavingGuestEntry` event

Plugins can be notified _before_ a guest entry is saved, by listening to the `\CraftCms\GuestEntries\Events\SavingGuestEntry` event.
This is also an opportunity to flag the submission as spam, and prevent it being saved:

```php
use CraftCms\Cms\Support\Str;
use CraftCms\GuestEntries\Events\SavingGuestEntry;

// ...

Event::listen(function (SavingGuestEntry $event) {
    // Get a reference to the entry object:
    $entry = $event->entry;

    // Perform spam detection logic of your own design:
    if (Str::contains($entry->title, 'synergy', true)) {
        // Set the event property:
        $event->isSpam = true;
    }
});
```

You may also set the `$event->isValid` property to prevent saving the entry for other reasons.

### The `SavedGuestEntry` event

Plugins can be notified _after_ a guest entry is saved, by listening to the `\CraftCms\GuestEntries\Events\SavedGuestEntry` event:

```php
use CraftCms\Cms\Support\Str;
use CraftCms\GuestEntries\Events\SavedGuestEntry;

// ...

Event::listen(function (SavedGuestEntry $event) {
    $entry = $event->entry;

    Log::info(sprintf('Saved a new guest entry: %s', $entry->title));
});
```

### The `SectionResolutionFailed` event

If the plugin can’t determine a section based on the submitted data, it will emit a `CraftCms\GuestEntries\Events\SectionResolutionFailed` event:

```php
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Events\SectionResolutionFailed;

Event::listen(function (SectionResolutionFailed $event) {
    // $event->request is an instance of the FormRequest, including all the submitted fields.

    // Assign the `section` property to establish a “default,” or if you can determine the user’s intent: 
    $event->section = Sections::getSectionByHandle('supportTickets');
});
```

> [!WARNING]
> Assigning a “fallback” section in this way bypasses its **Allow Guest Submissions?** setting!
> Never assign `section` based on input from the user.
