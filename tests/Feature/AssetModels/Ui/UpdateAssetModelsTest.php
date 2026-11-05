<?php

namespace Tests\Feature\AssetModels\Ui;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Tests\TestCase;

class UpdateAssetModelsTest extends TestCase
{
    public function test_permission_required_to_update_asset_model()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('models.update', ['model' => AssetModel::factory()->create()]), [
                'name' => 'Changed Name',
                'category_id' => Category::factory()->create()->id,
            ])
            ->assertForbidden();
    }

    public function test_page_renders()
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('models.edit', AssetModel::factory()->create()))
            ->assertOk();
    }

    public function test_user_can_edit_asset_models()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create(['name' => 'Test Model', 'category_id' => $category->id]);
        $this->assertTrue(AssetModel::where('name', 'Test Model')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $model]), [
                'name' => 'Test Model Edited',
                'category_id' => $model->category_id,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('models.index'));

        $this->followRedirects($response)->assertSee('Success');
        $this->assertTrue(AssetModel::where('name', 'Test Model Edited')->exists());

    }

    public function test_user_cannot_change_asset_model_category_type()
    {
        $category = Category::factory()->forAssets()->create();
        $model = AssetModel::factory()->create(['name' => 'Test Model', 'category_id' => $category->id]);
        $this->assertTrue(AssetModel::where('name', 'Test Model')->exists());

        $response = $this->actingAs(User::factory()->superuser()->create())
            ->from(route('models.edit', $model))
            ->put(route('models.update', $model), [
                'name' => 'Test Model Edited',
                'category_id' => Category::factory()->forAccessories()->create()->id,
            ])
            ->assertSessionHasErrors(['category_type'])
            ->assertInvalid(['category_type'])
            ->assertStatus(302)
            ->assertRedirect(route('models.edit', $model));

        $this->followRedirects($response)->assertSee(trans('general.error'));
        $this->assertFalse(AssetModel::where('name', 'Test Model Edited')->exists());

    }

    public function test_default_values_remain_unchanged_after_validation_error_occurs()
    {
        $this->markIncompleteIfMySQL('Custom Field Tests do not work in MySQL');

        $assetModel = AssetModel::factory()->create();
        $customFieldset = CustomFieldset::factory()->create();
        [$customFieldOne, $customFieldTwo] = CustomField::factory()->count(2)->create();

        $customFieldset->fields()->attach($customFieldOne, ['order' => 1, 'required' => false]);
        $customFieldset->fields()->attach($customFieldTwo, ['order' => 2, 'required' => false]);

        $assetModel->fieldset()->associate($customFieldset);

        $assetModel->defaultValues()->attach($customFieldOne, ['default_value' => 'first default value']);
        $assetModel->defaultValues()->attach($customFieldTwo, ['default_value' => 'second default value']);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $assetModel]), [
                // should trigger validation error without name, etc, and NOT remove or change default values
                'add_default_values' => '1',
                'fieldset_id' => $customFieldset->id,
                'default_values' => [
                    $customFieldOne->id => 'first changed value',
                    $customFieldTwo->id => 'second changed value',
                ],
            ]);

        $potentiallyChangedDefaultValues = $assetModel->defaultValues->pluck('pivot.default_value');
        $this->assertCount(2, $potentiallyChangedDefaultValues);
        $this->assertContains('first default value', $potentiallyChangedDefaultValues);
        $this->assertContains('second default value', $potentiallyChangedDefaultValues);
    }

    public function test_default_values_can_be_updated()
    {
        $this->markIncompleteIfMySQL('Custom Field Tests do not work in MySQL');

        $assetModel = AssetModel::factory()->create();
        $customFieldset = CustomFieldset::factory()->create();
        [$customFieldOne, $customFieldTwo] = CustomField::factory()->count(2)->create();

        $customFieldset->fields()->attach($customFieldOne, ['order' => 1, 'required' => false]);
        $customFieldset->fields()->attach($customFieldTwo, ['order' => 2, 'required' => false]);

        $assetModel->fieldset()->associate($customFieldset);

        $assetModel->defaultValues()->attach($customFieldOne, ['default_value' => 'first default value']);
        $assetModel->defaultValues()->attach($customFieldTwo, ['default_value' => 'second default value']);

        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', ['model' => $assetModel]), [
                // should trigger validation error without name, etc, and NOT remove or change default values
                'name' => 'Test Model Edited',
                'category_id' => $assetModel->category_id,
                'add_default_values' => '1',
                'fieldset_id' => $customFieldset->id,
                'default_values' => [
                    $customFieldOne->id => 'first changed value',
                    $customFieldTwo->id => 'second changed value',
                ],
            ]);

        $potentiallyChangedDefaultValues = $assetModel->defaultValues->pluck('pivot.default_value');
        $this->assertCount(2, $potentiallyChangedDefaultValues);
        $this->assertContains('first changed value', $potentiallyChangedDefaultValues);
        $this->assertContains('second changed value', $potentiallyChangedDefaultValues);
    }

    public function test_changing_eol_months_recalculates_calculated_assets_with_a_purchase_date()
    {
        $this->skipUnlessMySQL('The EOL months cascade uses MySQL DATE_ADD.');

        $model = $this->modelWithEolMonths(36);
        $asset = $this->assetWithEol($model, '2021-01-15', '2024-01-15', false);

        $this->updateModelEolMonths($model, 24);

        $asset->refresh();
        $this->assertSame('2023-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    public function test_changing_eol_months_leaves_explicit_assets_and_assets_without_purchase_date_alone()
    {
        $this->skipUnlessMySQL('The EOL months cascade uses MySQL DATE_ADD.');

        $model = $this->modelWithEolMonths(36);
        $explicit = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);
        $noPurchaseDate = $this->assetWithEol($model, null, null, false);

        $this->updateModelEolMonths($model, 24);

        $explicit->refresh();
        $this->assertSame('2028-11-30', $explicit->asset_eol_date);
        $this->assertTrue($explicit->eol_explicit);

        $noPurchaseDate->refresh();
        $this->assertNull($noPurchaseDate->asset_eol_date);
        $this->assertFalse($noPurchaseDate->eol_explicit);
    }

    public function test_setting_eol_months_to_zero_clears_calculated_eol_dates_and_keeps_explicit_ones()
    {
        $model = $this->modelWithEolMonths(36);
        $calculated = $this->assetWithEol($model, '2021-01-15', '2024-01-15', false);
        $explicit = $this->assetWithEol($model, '2021-01-15', '2028-11-30', true);

        $this->updateModelEolMonths($model, 0);

        $calculated->refresh();
        $this->assertNull($calculated->asset_eol_date);
        $this->assertFalse($calculated->eol_explicit);

        $explicit->refresh();
        $this->assertSame('2028-11-30', $explicit->asset_eol_date);
        $this->assertTrue($explicit->eol_explicit);
    }

    public function test_saving_model_without_changing_eol_months_leaves_assets_alone()
    {
        $model = $this->modelWithEolMonths(36);
        $asset = $this->assetWithEol($model, '2021-01-15', '2030-01-15', false);

        $this->updateModelEolMonths($model, 36);

        $asset->refresh();
        $this->assertSame('2030-01-15', $asset->asset_eol_date);
        $this->assertFalse($asset->eol_explicit);
    }

    private function modelWithEolMonths(int $months): AssetModel
    {
        return AssetModel::factory()->create([
            'category_id' => Category::factory()->forAssets(),
            'eol' => $months,
        ]);
    }

    private function assetWithEol(AssetModel $model, ?string $purchaseDate, ?string $eolDate, bool $explicit): Asset
    {
        $asset = Asset::factory()->create(['model_id' => $model->id]);

        $asset->forceFill([
            'purchase_date' => $purchaseDate,
            'asset_eol_date' => $eolDate,
            'eol_explicit' => $explicit,
        ])->saveQuietly();

        return $asset;
    }

    private function updateModelEolMonths(AssetModel $model, int $months): void
    {
        $this->actingAs(User::factory()->superuser()->create())
            ->put(route('models.update', $model), [
                'name' => $model->name,
                'category_id' => $model->category_id,
                'eol' => $months,
            ])
            ->assertRedirect(route('models.index'))
            ->assertSessionHasNoErrors();
    }
}
