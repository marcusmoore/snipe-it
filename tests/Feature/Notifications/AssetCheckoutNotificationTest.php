<?php

namespace Tests\Feature\Notifications;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use App\Notifications\CheckoutAssetNotification;
use Tests\Support\InteractsWithSettings;
use Tests\TestCase;

class AssetCheckoutNotificationTest extends TestCase
{
    use InteractsWithSettings;

    public function scenarios(): array
    {
        return [
            '"Send email to user on checkout" is enabled',
            '"Send email to user on checkout" is disabled but category requires acceptance',
            '"Send email to user on checkout" is disabled and category does not require acceptance',
        ];
    }

    public function testAssetDeliveryConfirmationIncludesCategoryEULA()
    {
        // Given default EULA is set
        $this->settings->set(['default_eula_text' => 'Default EULA text']);

        // And an asset's category has its own EULA set and doesn't use the default
        $asset = Asset::factory()->create();
        $asset->model->category->update([
            'eula_text' => 'Custom EULA text',
            'use_default_eula' => false,
        ]);

        $notification = new CheckoutAssetNotification(
            $asset,
            User::factory()->create(),
            User::factory()->superuser()->create(),
            null,
            ''
        );

        // The checkout email should include the category's EULA and not the default
        $this->assertStringContainsString(
            'Custom EULA text',
            $notification->toMail()->render(),
            'Category EULA text is not present in email'
        );
    }

    public function testAssetDeliveryConfirmationUsesDefaultEULAWhenEnabled()
    {
        $this->markTestIncomplete();

        // Given default EULA is set
        $this->settings->set(['default_eula_text' => 'Default EULA text']);

        // And an asset's category uses the default EULA
        $asset = Asset::factory()->create();
        $asset->model->category->update([
            'eula_text' => 'Custom EULA text',
            'use_default_eula' => true,
        ]);

        $notification = new CheckoutAssetNotification(
            $asset,
            User::factory()->create(),
            User::factory()->superuser()->create(),
            null,
            ''
        );

        // The checkout email should include the default EULA
        $this->assertStringContainsString(
            'Default EULA text',
            $notification->toMail()->render(),
            'Default EULA text is not present in email'
        );
    }
}
