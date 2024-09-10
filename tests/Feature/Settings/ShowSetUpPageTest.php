<?php

use PHPUnit\Framework\Attributes\DataProvider;
use App\Http\Controllers\SettingsController;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

function getSetUpPageResponse() : TestResponse
{
    if ($this->preventStrayRequest) {
        Http::fake([URL::to('.env') => Http::response(null, 404)]);
    }

    return $this->get('/setup');
}

test('view', function () {
    getSetUpPageResponse()->assertOk()->assertViewIs('setup.index');
});

test('will show error message when database connection cannot be established', function () {
    Event::listen(function (QueryExecuted $query) {
        if ($query->sql === 'select 2 + 2') {
            throw new PDOException("SQLSTATE[HY000] [1045] Access denied for user ''@'localhost' (using password: NO)");
        }
    });

    getSetUpPageResponse()->assertOk();

    assertSeeDatabaseConnectionErrorMessage();
});

function assertSeeDatabaseConnectionErrorMessage(bool $shouldSee = true) : void
{
    $errorMessage = "D'oh! Looks like we can't connect to your database. Please update your database settings in your  <code>.env</code> file.";
    $successMessage = sprintf('Great work! Connected to <code>%s</code>', DB::connection()->getDatabaseName());

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage, false)->assertDontSee($successMessage, false);
        return;
    }

    self::$latestResponse->assertSee($successMessage, false)->assertDontSee($errorMessage, false);
}

test('will not show error message when database is connected', function () {
    getSetUpPageResponse()->assertOk();

    assertSeeDatabaseConnectionErrorMessage(false);
});

test('will show error message when debug mode is enabled and app environment is set to production', function () {
    config(['app.debug' => true]);

    $this->app->bind('env', fn () => 'production');

    getSetUpPageResponse()->assertOk();

    assertSeeDebugModeMisconfigurationErrorMessage();
});

function assertSeeDebugModeMisconfigurationErrorMessage(bool $shouldSee = true) : void
{
    $errorMessage = 'Yikes! You should turn off debug mode unless you encounter any issues. Please update your <code>APP_DEBUG</code> settings in your  <code>.env</code> file';
    $successMessage = "Awesomesauce. Debug is either turned off, or you're running this in a non-production environment. (Don't forget to turn it off when you're ready to go live.)";

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage, false)->assertDontSee($successMessage, false);
        return;
    }

    self::$latestResponse->assertSee($successMessage, false)->assertDontSee($errorMessage, false);
}

test('will not show error when debug mode is enabled and app environment is set to local', function () {
    config(['app.debug' => true]);

    $this->app->bind('env', fn () => 'local');

    getSetUpPageResponse()->assertOk();

    assertSeeDebugModeMisconfigurationErrorMessage(false);
});

test('will not show error when debug mode is disabled and app environment is set to production', function () {
    config(['app.debug' => false]);

    $this->app->bind('env', fn () => 'production');

    getSetUpPageResponse()->assertOk();

    assertSeeDebugModeMisconfigurationErrorMessage(false);
});

test('will show error when environment is local', function () {
    $this->app->bind('env', fn () => 'local');

    getSetUpPageResponse()->assertOk();

    assertSeeEnvironmentMisconfigurationErrorMessage();
});

function assertSeeEnvironmentMisconfigurationErrorMessage(bool $shouldSee = true) : void
{
    $errorMessage = 'Your app is set <code>local</code> instead of <code>production</code> mode.';
    $successMessage = 'Your app is set to production mode. Rock on!';

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage, false)->assertDontSee($successMessage, false);

        return;
    }

    self::$latestResponse->assertSee($successMessage, false)->assertDontSee($errorMessage, false);
}

test('will not show error when environment is production', function () {
    $this->app->bind('env', fn () => 'production');

    getSetUpPageResponse()->assertOk();

    assertSeeEnvironmentMisconfigurationErrorMessage(false);
});

test('will check dot env file visibility', function () {
    getSetUpPageResponse()->assertOk();

    Http::assertSent(function (Request $request) {
        expect($request->method())->toEqual('GET');
        expect($request->url())->toEqual(URL::to('.env'));
        return true;
    });
});

test('will show error when dot env file is accessible via http', function (int $statusCode) {
    $this->preventStrayRequest = false;

    Http::fake([URL::to('.env') => Http::response(null, $statusCode)]);

    getSetUpPageResponse()->assertOk();

    Http::assertSent(function (Request $request, Response $response) use ($statusCode) {
        expect($response->status())->toEqual($statusCode);
        return true;
    });

    assertSeeDotEnvFileExposedErrorMessage();
})->with('willShowErrorWhenDotEnvFileIsAccessibleViaHttpData');

dataset('willShowErrorWhenDotEnvFileIsAccessibleViaHttpData', function () {
    return collect([200, 202, 204, 206])
        ->mapWithKeys(fn (int $code) => ["StatusCode: {$code}" => [$code]])
        ->all();
});

function assertSeeDotEnvFileExposedErrorMessage(bool $shouldSee = true) : void
{
    $errorMessage = "We cannot determine if your config file is exposed to the outside world, so you will have to manually verify this. You don't ever want anyone able to see that file. Ever. Ever ever. An exposed <code>.env</code> file can disclose sensitive data about your system and database.";
    $successMessage = "Sweet. It doesn't look like your <code>.env</code> file is exposed to the outside world. (You should double check this in a browser though. You don't ever want anyone able to see that file. Ever. Ever ever.) <a href=\"../../.env\">Click here to check now</a> (This should return a file not found or forbidden error.)";

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage, false)->assertDontSee($successMessage, false);

        return;
    }

    self::$latestResponse->assertSee($successMessage, false)->assertDontSee($errorMessage, false);
}

test('will not show error when dot env file is not accessible via http', function () {
    getSetUpPageResponse()->assertOk();

    assertSeeDotEnvFileExposedErrorMessage(false);
});

test('will show error when dot env file visibility check request fails', function () {
    $this->preventStrayRequest = false;

    Http::fake([URL::to('.env') => fn () => throw new ConnectionException('Some curl error message.')]);

    Log::setEventDispatcher(Event::fake());

    getSetUpPageResponse()->assertOk();

    assertSeeDotEnvFileExposedErrorMessage();

    Event::assertDispatched(function (MessageLogged $event) {
        expect($event->level)->toEqual('debug');
        expect($event->message)->toEqual('Some curl error message.');

        return true;
    });
});

test('will show error message when app url is not same with page url', function () {
    config(['app.url' => 'http://www.github.com']);

    getSetUpPageResponse()->assertOk();

    assertSeeAppUrlMisconfigurationErrorMessage();
});

function assertSeeAppUrlMisconfigurationErrorMessage(bool $shouldSee = true) : void
{
    $url = URL::to('setup');

    $errorMessage = "Uh oh! Snipe-IT thinks your URL is http://www.github.com/setup, but your real URL is {$url}";
    $successMessage = 'That URL looks right! Good job!';

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage)->assertDontSee($successMessage);
        return;
    }

    self::$latestResponse->assertSee($successMessage)->assertDontSee($errorMessage);
}

test('will not show error message when app url is same with page url', function () {
    getSetUpPageResponse()->assertOk();

    assertSeeAppUrlMisconfigurationErrorMessage(false);
});

test('when app url contains trailing slash', function () {
    config(['app.url' => 'http://www.github.com/']);

    getSetUpPageResponse()->assertOk();

    assertSeeAppUrlMisconfigurationErrorMessage();
});

test('will see directory permission error when storage path is not writable', function () {
    File::shouldReceive('isWritable')->andReturn(false);

    getSetUpPageResponse()->assertOk();

    assertSeeDirectoryPermissionError();
});

function assertSeeDirectoryPermissionError(bool $shouldSee = true) : void
{
    $storagePath = storage_path();

    $errorMessage = "Uh-oh. Your <code>{$storagePath}</code> directory (or sub-directories within) are not writable by the web-server. Those directories need to be writable by the web server in order for the app to work.";
    $successMessage = 'Yippee! Your app storage directory seems writable';

    if ($shouldSee) {
        self::$latestResponse->assertSee($errorMessage, false)->assertDontSee($successMessage, false);
        return;
    }

    self::$latestResponse->assertSee($successMessage, false)->assertDontSee($errorMessage,false);
}

test('will not see directory permission error when storage path is writable', function () {
    File::shouldReceive('isWritable')->andReturn(true);

    getSetUpPageResponse()->assertOk();

    assertSeeDirectoryPermissionError(false);
});

test('invalid t l s certs ok when checking for env file', function () {
    //set the weird bad SSL cert place - https://self-signed.badssl.com
    $this->markTestIncomplete("Not yet sure how to write this test, it requires messing with .env ...");
    expect((new SettingsController())->dotEnvFileIsExposed())->toBeTrue();
});
