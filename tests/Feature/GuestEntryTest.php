<?php

declare(strict_types=1);

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Entry\Models\EntryType;
use CraftCms\Cms\Site\Models\Site;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\GuestEntries\Events\GuestEntrySaved;
use CraftCms\GuestEntries\Events\GuestEntrySaveFailed;
use CraftCms\GuestEntries\Events\GuestEntrySaving;
use CraftCms\GuestEntries\Events\SectionResolutionFailed;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->section = guestSection();
    $this->submission = [
        'sectionHandle' => 'submissions',
        'title' => 'Hello from a guest',
        'fields' => ['body' => 'Some content'],
    ];
});

it('saves a guest entry with the section’s settings', function (string $url) {
    postJson($url, $this->submission)->assertOk()->assertJsonPath('entry.title', 'Hello from a guest');

    $entry = Entry::find()->section('submissions')->status(null)->one();

    expect($entry->getType()->handle)->toBe('submission')
        ->and($entry->enabled)->toBeTrue()
        ->and($entry->getFieldValue('body'))->toBe('Some content')
        ->and(collect($entry->getAuthors())->pluck('admin')->all())->toBe([true]);
})->with([
    'Craft 5 action URL' => '/actions/guest-entries/save',
    'endpoint setting' => '/guest-entries/save',
]);

it('saves disabled entries unless the section enables them by default', function () {
    allowGuests($this->section, ['enabledByDefault' => false]);

    postJson('/actions/guest-entries/save', $this->submission)->assertOk();

    expect(Entry::find()->section('submissions')->status(null)->one()->enabled)->toBeFalse();
});

it('uses the requested entry type', function (string $param) {
    $question = collect($this->section->getEntryTypes())->firstWhere('handle', 'question');
    $value = $param === 'type' ? $question->handle : $question->id;

    postJson('/actions/guest-entries/save', [...$this->submission, $param => $value])->assertOk();

    expect(Entry::find()->section('submissions')->one()->getType()->handle)->toBe('question');
})->with(['type', 'typeId']);

it('rejects entry types that aren’t in the section', function (string $param, string $attribute) {
    $other = EntryType::factory()->create(['handle' => 'other']);

    postJson('/actions/guest-entries/save', [...$this->submission, $param => $other->$attribute])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($param);

    expect(Entry::find()->status(null)->count())->toBe(0);
})->with([
    'type' => ['type', 'handle'],
    'typeId' => ['typeId', 'id'],
]);

it('only accepts sections that allow guest submissions', function (array $section) {
    guestSection('closed', ['allowGuestSubmissions' => false]);

    postJson('/actions/guest-entries/save', [...$this->submission, ...$section])->assertForbidden();

    expect(Entry::find()->status(null)->count())->toBe(0);
})->with([
    'disallowed section' => [['sectionHandle' => 'closed']],
    'unknown section' => [['sectionHandle' => 'nope']],
]);

it('ignores other section identifiers once one is sent', function () {
    $closed = guestSection('closed', ['allowGuestSubmissions' => false]);

    postJson('/actions/guest-entries/save', [...$this->submission, 'sectionId' => $closed->id])->assertForbidden();

    expect(Entry::find()->status(null)->count())->toBe(0);
});

it('creates the entry in the requested site', function () {
    $other = Site::factory()->create(['handle' => 'other', 'primary' => false]);
    guestSection('everywhere', [], Site::query()->where('primary', true)->first(), $other);

    postJson('/actions/guest-entries/save', [...$this->submission, 'sectionHandle' => 'everywhere', 'siteId' => $other->id])->assertOk();
    expect(Entry::find()->section('everywhere')->siteId($other->id)->count())->toBe(1);

    // The `submissions` section isn’t enabled for the other site:
    postJson('/actions/guest-entries/save', [...$this->submission, 'siteId' => $other->id])->assertBadRequest();
});

it('ignores a blank section ID when another identifier is sent', function () {
    postJson('/actions/guest-entries/save', [...$this->submission, 'sectionId' => ''])->assertOk();

    expect(Entry::find()->section('submissions')->status(null)->count())->toBe(1);
});

it('uses the current site when the site ID is blank', function () {
    postJson('/actions/guest-entries/save', [...$this->submission, 'siteId' => ''])->assertOk();

    $primary = Site::query()->where('primary', true)->first();
    expect(Entry::find()->section('submissions')->siteId($primary->id)->status(null)->count())->toBe(1);
});

it('dispatches events around a successful save', function () {
    $dispatched = [];
    Event::listen(GuestEntrySaving::class, function (GuestEntrySaving $event) use (&$dispatched) {
        $dispatched[] = [$event::class, $event->entry->id];
    });
    Event::listen(GuestEntrySaved::class, function (GuestEntrySaved $event) use (&$dispatched) {
        $dispatched[] = [$event::class, $event->entry->id];
    });

    postJson('/actions/guest-entries/save', $this->submission)->assertOk();

    $id = Entry::find()->section('submissions')->one()->id;
    expect($dispatched)->toBe([[GuestEntrySaving::class, null], [GuestEntrySaved::class, $id]]);
});

it('pretends spam was saved', function () {
    Event::listen(fn (GuestEntrySaving $event) => $event->isSpam = true);
    Event::fake([GuestEntrySaved::class, GuestEntrySaveFailed::class]);

    postJson('/actions/guest-entries/save', $this->submission)
        ->assertOk()
        ->assertJsonPath('modelId', null);

    expect(Entry::find()->status(null)->count())->toBe(0);
    Event::assertNotDispatched(GuestEntrySaved::class);
    Event::assertNotDispatched(GuestEntrySaveFailed::class);
});

it('reports rejected and invalid submissions', function (Closure $setUp, array $submission, array $errors) {
    $setUp();
    Event::fake([GuestEntrySaved::class, GuestEntrySaveFailed::class]);

    $response = postJson('/actions/guest-entries/save', [...$this->submission, ...$submission])->assertBadRequest();

    expect(array_keys($response->json('errors') ?? []))->toBe($errors)
        ->and(Entry::find()->status(null)->count())->toBe(0);
    Event::assertDispatchedTimes(GuestEntrySaveFailed::class, 1);
    Event::assertNotDispatched(GuestEntrySaved::class);
})->with([
    'rejected by a listener' => [fn () => Event::listen(fn (GuestEntrySaving $event) => $event->isValid = false), [], []],
    'failed validation' => [fn () => null, ['fields' => ['body' => '']], ['body']],
]);

it('lets listeners choose a section when none matches', function () {
    guestSection('closed', ['allowGuestSubmissions' => false]);
    Event::listen(function (SectionResolutionFailed $event) {
        if ($event->request->input('sectionHandle') === 'fallback') {
            $event->section = Sections::getSectionByHandle('closed');
        }
    });

    postJson('/actions/guest-entries/save', [...$this->submission, 'sectionHandle' => 'fallback'])->assertOk();

    expect(Entry::find()->section('closed')->status(null)->count())->toBe(1);
});

it('redirects back with the submitted values and errors', function () {
    post('/actions/guest-entries/save', [...$this->submission, 'fields' => ['body' => '']], ['Referer' => 'https://localhost/form'])
        ->assertRedirect('https://localhost/form')
        ->assertSessionHasErrors('body')
        ->assertSessionHasInput('title', 'Hello from a guest')
        ->assertSessionHas('entry.title', 'Hello from a guest');
});
