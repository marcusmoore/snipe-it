<?php

namespace Tests\Feature\Api\Users;

use App\Models\Asset;
use App\Models\LicenseSeat;
use App\Models\User;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use InteractsWithSettings;

    public function testCanFilterByAssetCount()
    {
        $admin = User::factory()->superuser()->create();

        $userWithNoAssets = User::factory()->create();

        $userWithOneAsset = User::factory()->afterCreating(function ($user) use ($admin) {
            Asset::factory()->create()->checkOut($user, $admin, now());
        })->create();

        $userWithTwoAssets = User::factory()->afterCreating(function ($user) use ($admin) {
            Asset::factory()->count(2)->create()->each(function ($asset) use ($user, $admin) {
                return $asset->checkOut($user, $admin, now());
            });
        })->create();

        $this->actingAsForApi($admin);

        $response = $this->getJson(route('api.users.index', ['assets_count' => 0]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithNoAssets->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithOneAsset->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithTwoAssets->email));

        $response = $this->getJson(route('api.users.index', ['assets_count' => 1]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithNoAssets->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithOneAsset->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithTwoAssets->email));

        $response = $this->getJson(route('api.users.index', ['assets_count' => 2]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithNoAssets->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithOneAsset->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithTwoAssets->email));
    }

    public function testCanFilterByLicenseCount()
    {
        $admin = User::factory()->superuser()->create();

        $userWithNoLicenses = User::factory()->create();
        $userWithOneLicense = User::factory()
            ->afterCreating(fn($user) => LicenseSeat::factory()->for($user)->create())
            ->create();
        $userWithTwoLicenses = User::factory()
            ->afterCreating(fn($user) => LicenseSeat::factory()->count(2)->for($user)->create())
            ->create();

        $this->actingAsForApi($admin);

        $response = $this->getJson(route('api.users.index', ['licenses_count' => 0]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithNoLicenses->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithOneLicense->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithTwoLicenses->email));

        $response = $this->getJson(route('api.users.index', ['licenses_count' => 1]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithNoLicenses->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithOneLicense->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithTwoLicenses->email));

        $response = $this->getJson(route('api.users.index', ['licenses_count' => 2]));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithNoLicenses->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->doesntContain($userWithOneLicense->email));
        $this->assertTrue(collect($response->json('rows'))->pluck('email')->contains($userWithTwoLicenses->email));
    }
}
