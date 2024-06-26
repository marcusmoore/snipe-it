<?php

namespace Tests;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Spatie\Once\Cache;
use Tests\Support\AssertsAgainstSlackNotifications;
use Tests\Support\CanSkipTests;
use Tests\Support\CustomAssertions;
use Tests\Support\CustomTestMacros;
use Tests\Support\InteractsWithAuthentication;
use Tests\Support\InitializesSettings;

abstract class TestCase extends BaseTestCase
{
    use AssertsAgainstSlackNotifications;
    use CanSkipTests;
    use CreatesApplication;
    use CustomAssertions;
    use CustomTestMacros;
    use InteractsWithAuthentication;
    use InitializesSettings;
    use LazilyRefreshDatabase;

    private array $globallyDisabledMiddleware = [
        SecurityHeaders::class,
    ];

    protected function setUp(): void
    {
        $this->guardAgainstMissingEnv();

        parent::setUp();

        $this->registerCustomMacros();

        $this->registerFlushingMemoizedVariables();

        $this->withoutMiddleware($this->globallyDisabledMiddleware);

        $this->initializeSettings();
    }

    private function guardAgainstMissingEnv(): void
    {
        if (!file_exists(realpath(__DIR__ . '/../') . '/.env.testing')) {
            throw new RuntimeException(
                '.env.testing file does not exist. Aborting to avoid wiping your local database.'
            );
        }
    }

    private function registerFlushingMemoizedVariables(): void
    {
        $this->beforeApplicationDestroyed(fn() => Cache::getInstance()->flush());
    }
}
