<?php

namespace Tests\Feature\Notifications;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use App\Notifications\CheckoutAssetNotification;
use Notification;
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
        $this->markTestIncomplete();

        // Given default EULA is set
        $this->settings->set(['default_eula_text' => 'Default EULA text']);

        // And an asset's category has its own EULA set and doesn't use the default
        $asset = Asset::factory()->create();
        $asset->model->category->update([
            'eula_text' => 'Custom EULA text',
            'use_default_eula' => false,
        ]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->for($asset, 'checkoutable')->create();

        $notification = new CheckoutAssetNotification(
            $asset,
            $checkoutAcceptance->assignedTo,
            User::factory()->superuser()->create(),
            $checkoutAcceptance,
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

        Notification::fake();

        // Given default EULA is set
        $this->settings->set(['default_eula_text' => 'Default EULA text']);

        // And an asset's category uses the default EULA
        $asset = Asset::factory()->create();
        $asset->model->category->update([
            'eula_text' => 'Custom EULA text',
            'use_default_eula' => true,
        ]);

        $checkoutAcceptance = CheckoutAcceptance::factory()->for($asset, 'checkoutable')->create();

        $notification = new CheckoutAssetNotification(
            $asset,
            $checkoutAcceptance->assignedTo,
            User::factory()->superuser()->create(),
            $checkoutAcceptance,
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
