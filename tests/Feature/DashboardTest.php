<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithSettings;

    public function testUsersWithoutAdminAccessAreRedirected()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('view-assets'));
    }

    public function testUserCountForDashboard()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Company A has 2 users
        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $companyA->users()->saveMany(User::factory()->count(1)->make());

        // Company B has 3 users
        $adminUser = $companyB->users()->save(User::factory()->admin()->make());
        $companyB->users()->saveMany(User::factory()->count(2)->make());

        $this->settings->disableMultipleFullCompanySupport();

        $this->actingAs($superUser)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('counts', fn($counts) => $counts['user'] === 5);

        $this->actingAs($adminUser)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('counts', fn($counts) => $counts['user'] === 5);

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAs($superUser)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('counts', fn($counts) => $counts['user'] === 5);

        $this->actingAs($adminUser)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('counts', fn($counts) => $counts['user'] === 3);
    }
}
