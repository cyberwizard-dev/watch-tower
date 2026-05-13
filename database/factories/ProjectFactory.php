<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);
        $rawSecret = 'sk_test_'.Str::random(40);

        return [
            'organization_id' => Organization::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'api_key' => 'pk_test_'.Str::random(40),
            'api_secret_hash' => Hash::make($rawSecret),
            'sampling_rate' => 1.0,
            'retention_days' => 30,
        ];
    }

    /**
     * Attach a known plain-text API key for testing.
     */
    public function withApiKey(string $key): static
    {
        return $this->state(fn () => ['api_key' => $key]);
    }
}
