<?php

use App\Livewire\CategoryEditForm;
use Livewire\Livewire;

test('the component can render', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => true,
        'useDefaultEula' => true,
    ])->assertStatus(200);
});

test('send email checkbox is checked on load when send email is existing setting', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => true,
        'eulaText' => '',
        'useDefaultEula' => false,
    ])->assertSet('sendCheckInEmail', true);
});

test('send email checkbox is checked on load when category eula set', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'eulaText' => 'Some Content',
        'useDefaultEula' => false,
    ])->assertSet('sendCheckInEmail', true);
});

test('send email checkbox is checked on load when using default eula', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'eulaText' => '',
        'useDefaultEula' => true,
    ])->assertSet('sendCheckInEmail', true);
});

test('send email check box is unchecked on load when send email is false no category eula set and not using default eula', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'eulaText' => '',
        'useDefaultEula' => false,
    ])->assertSet('sendCheckInEmail', false);
});

test('send email checkbox is checked when category eula entered', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'useDefaultEula' => false,
    ])->assertSet('sendCheckInEmail', false)
        ->set('eulaText', 'Some Content')
        ->assertSet('sendCheckInEmail', true);
});

test('send email checkbox checked and disabled and eula text disabled when use default eula selected', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'useDefaultEula' => false,
    ])->assertSet('sendCheckInEmail', false)
        ->set('useDefaultEula', true)
        ->assertSet('sendCheckInEmail', true)
        ->assertSet('eulaTextDisabled', true)
        ->assertSet('sendCheckInEmailDisabled', true);
});

test('send email checkbox enabled and set to original value when no category eula and not using global eula', function () {
    Livewire::test(CategoryEditForm::class, [
        'eulaText' => 'Some Content',
        'sendCheckInEmail' => false,
        'useDefaultEula' => true,
    ])
        ->set('useDefaultEula', false)
        ->set('eulaText', '')
        ->assertSet('sendCheckInEmail', false)
        ->assertSet('sendCheckInEmailDisabled', false);

    Livewire::test(CategoryEditForm::class, [
        'eulaText' => 'Some Content',
        'sendCheckInEmail' => true,
        'useDefaultEula' => true,
    ])
        ->set('useDefaultEula', false)
        ->set('eulaText', '')
        ->assertSet('sendCheckInEmail', true)
        ->assertSet('sendCheckInEmailDisabled', false);
});

test('eula field enabled on load when not using default eula', function () {
    Livewire::test(CategoryEditForm::class, [
        'sendCheckInEmail' => false,
        'eulaText' => '',
        'useDefaultEula' => false,
    ])->assertSet('eulaTextDisabled', false);
});
