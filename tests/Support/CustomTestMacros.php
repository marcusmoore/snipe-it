<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use ReflectionFunction;
use RuntimeException;

trait CustomTestMacros
{
    protected function registerCustomMacros()
    {
        $guardAgainstNullProperty = function (Model $model, string $property) {
            if (is_null($model->{$property})) {
                throw new RuntimeException(
                    "The property ({$property}) either does not exist or is null on the model which isn't helpful for comparison."
                );
            }
        };

        TestResponse::macro(
            'assertResponseContainsInRows',
            function (Model $model, string $property = 'name', string $message = null) use ($guardAgainstNullProperty) {
                $guardAgainstNullProperty($model, $property);

                Assert::assertTrue(
                    collect($this['rows'])->pluck($property)->contains(e($model->{$property})),
                    $message ?? "Response did not contain the expected value: {$model->{$property}}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertResponseDoesNotContainInRows',
            function (Model $model, string $property = 'name', string $message = null) use ($guardAgainstNullProperty) {
                $guardAgainstNullProperty($model, $property);

                Assert::assertFalse(
                    collect($this['rows'])->pluck($property)->contains(e($model->{$property})),
                    $message ?? "Response contained unexpected value: {$model->{$property}}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertResponseContainsInResults',
            function (Model $model, string $property = 'id') use ($guardAgainstNullProperty) {
                $guardAgainstNullProperty($model, $property);

                Assert::assertTrue(
                    collect($this->json('results'))->pluck('id')->contains(e($model->{$property})),
                    "Response did not contain the expected value: {$model->{$property}}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertResponseDoesNotContainInResults',
            function (Model $model, string $property = 'id') use ($guardAgainstNullProperty) {
                $guardAgainstNullProperty($model, $property);

                Assert::assertFalse(
                    collect($this->json('results'))->pluck('id')->contains(e($model->{$property})),
                    "Response contained unexpected value: {$model->{$property}}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertStatusMessageIs',
            function (string $message) {
                Assert::assertEquals(
                    $message,
                    $this['status'],
                    "Response status message was not {$message}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'assertMessagesAre',
            function (string $message) {
                Assert::assertEquals(
                    $message,
                    $this['messages'],
                    "Response messages was not {$message}"
                );

                return $this;
            }
        );

        TestResponse::macro(
            'checkAssertionsFromProvider',
            function (Closure|array $data) {
                // ✅ $data() will be an array. need to check to make sure 'assertions' is key

                // ✅ $data will be a closure with class: "Tests\Support\Provider"

                // ✅ $data()['assertions'] will be a closure with class: "Tests\Feature\ScopingForAssetIndexTest"
                //   use: {
                //     $assetWithNoCompany: App\Models\Asset {#3755 …}
                //     $assetForCompanyA: App\Models\Asset {#3759 …}
                //     $assetForCompanyB: App\Models\Asset {#2702 …}
                //   }

                if (is_array($data)) {
                    if (!array_key_exists('assertions', $data)) {
                        throw new RuntimeException("The key 'assertions' is missing from the array.");
                    }

                    $data = $data['assertions'];
                }

                if ((new ReflectionFunction($data))->getClosureScopeClass()?->getName() === Provider::class) {
                    $data = $data()['assertions'];
                }

                $data->bindTo($this)();

                return $this;
            }
        );

        TestResponse::macro(
            'provider',
            function ($key) {
                return resolve('bad_idea')[$key];
            }
        );
    }
}
