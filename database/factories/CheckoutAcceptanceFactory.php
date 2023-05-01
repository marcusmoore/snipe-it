<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckoutAcceptanceFactory extends Factory
{
    protected $model = CheckoutAcceptance::class;

    public function definition(): array
    {
        return [
            'checkoutable_id' => Asset::factory(),
            'checkoutable_type' => Asset::class,
            'assigned_to_id' => User::factory(),
        ];
    }
}
