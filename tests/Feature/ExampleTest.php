<?php

use App\Models\User;

it('can login', function () {
    User::factory()->create(['username' => 'marcus', 'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi']);

    visit('/login')->assertSee('Please Login')
        ->type('username', 'marcus')
        ->type('password', 'password')
        ->click('Login')
        ->assertSee('You have successfully logged in');
});
