<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use League\Csv\Reader;
use PHPUnit\Framework\Assert;

beforeEach(function () {
    TestResponse::macro(
        'assertSeeTextInStreamedResponse',
        function (string $needle) {
            Assert::assertTrue(
                collect(Reader::createFromString($this->streamedContent())->getRecords())
                    ->pluck(0)
                    ->contains($needle)
            );

            return $this;
        }
    );

    TestResponse::macro(
        'assertDontSeeTextInStreamedResponse',
        function (string $needle) {
            Assert::assertFalse(
                collect(Reader::createFromString($this->streamedContent())->getRecords())
                    ->pluck(0)
                    ->contains($needle)
            );

            return $this;
        }
    );
});

test('permission required to view unaccepted asset report', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports/unaccepted_assets'))
        ->assertForbidden();
});

test('user can list unaccepted assets', function () {
    $this->actingAs(User::factory()->superuser()->create())
        ->get(route('reports/unaccepted_assets'))
        ->assertOk();
});
