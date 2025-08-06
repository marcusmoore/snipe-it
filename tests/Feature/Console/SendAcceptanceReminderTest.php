<?php

use App\Mail\UnacceptedAssetReminderMail;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('acceptance reminder command', function () {
    Mail::fake();
    $userA = User::factory()->create(['email' => 'userA@test.com']);
    $userB = User::factory()->create(['email' => 'userB@test.com']);

    CheckoutAcceptance::factory()->pending()->count(2)->create([
        'assigned_to_id' => $userA->id,
    ]);
    CheckoutAcceptance::factory()->pending()->create([
        'assigned_to_id' => $userB->id,
    ]);

    $this->artisan('snipeit:acceptance-reminder')->assertExitCode(0);

    Mail::assertSent(UnacceptedAssetReminderMail::class, function ($mail) {
        return $mail->hasTo('userA@test.com');
    });

    Mail::assertSent(UnacceptedAssetReminderMail::class, function ($mail) {
        return $mail->hasTo('userB@test.com');
    });

    Mail::assertSent(UnacceptedAssetReminderMail::class,2);
});

test('acceptance reminder command handles user without email', function () {
    Mail::fake();
    $userA = User::factory()->create(['email' => '']);

    CheckoutAcceptance::factory()->pending()->create([
        'assigned_to_id' => $userA->id,
    ]);
    $headers = ['ID', 'Name'];
    $rows = [
        [$userA->id, $userA->present()->fullName()],
    ];
    $this->artisan('snipeit:acceptance-reminder')
        ->expectsOutput("The following users do not have an email address:")
        ->expectsTable($headers, $rows)
        ->assertExitCode(0);

    Mail::assertNotSent(UnacceptedAssetReminderMail::class);
});
