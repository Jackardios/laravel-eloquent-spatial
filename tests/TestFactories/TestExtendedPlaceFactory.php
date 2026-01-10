<?php

namespace Jackardios\EloquentSpatial\Tests\TestFactories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;

/**
 * @extends Factory<TestExtendedPlace>
 */
class TestExtendedPlaceFactory extends Factory
{
    protected $model = TestExtendedPlace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->streetName,
            'address' => $this->faker->address,
        ];
    }
}
