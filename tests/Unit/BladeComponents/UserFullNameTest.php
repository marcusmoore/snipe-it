<?php

use App\Models\User;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Assert;

test('component', function (User $actor, User|null $user, Closure $assertions) {
    $this->actingAs($actor);

    $renderedTemplateString = View::make('blade.full-user-name', ['user' => $user])->render();

    $assertions($renderedTemplateString);
})->with([
    'Renders link to user if they exist and the authenticated user can view them' => [
        function () {
            return [
                'actor' => User::factory()->viewUsers()->create(),
                'user' => User::factory()->create(['first_name' => 'Jim', 'last_name' => 'Bagg']),
                'assertions' => function ($rendered) {
                    Assert::assertStringContainsString('<a ', $rendered);
                    Assert::assertStringContainsString('Jim Bagg', $rendered);
                },
            ];
        }
    ],

    'Renders struck-through link to user if they are deleted and the authenticated user can view them' => [
        function () {
            return [
                'actor' => User::factory()->viewUsers()->create(),
                'user' => User::factory()->deleted()->create(['first_name' => 'Jim', 'last_name' => 'Bagg']),
                'assertions' => function ($rendered) {
                    Assert::assertStringContainsString('<s><a ', $rendered);
                    Assert::assertStringContainsString('Jim Bagg', $rendered);
                },
            ];
        }
    ],

    'Renders name without link if the authenticated user cannot view them' => [
        function () {
            return [
                'actor' => User::factory()->create(),
                'user' => User::factory()->create(['first_name' => 'Jim', 'last_name' => 'Bagg']),
                'assertions' => function ($rendered) {
                    Assert::assertStringContainsString('<span>Jim Bagg', $rendered);
                    Assert::assertStringNotContainsString('<a ', $rendered);
                },
            ];
        }
    ],

    'Renders struck-through name without link if the user is deleted and the authenticated user cannot view them' => [
        function () {
            return [
                'actor' => User::factory()->create(),
                'user' => User::factory()->deleted()->create(['first_name' => 'Jim', 'last_name' => 'Bagg']),
                'assertions' => function ($rendered) {
                    Assert::assertStringContainsString('<s><span>Jim Bagg', $rendered);
                },
            ];
        }
    ],

    'Renders nothing if the provided user is null' => [
        function () {
            return [
                'actor' => User::factory()->create(),
                'user' => null,
                'assertions' => function ($rendered) {
                    Assert::assertEmpty($rendered);
                },
            ];
        }
    ],
]);
