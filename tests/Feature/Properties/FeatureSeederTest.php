<?php

namespace Tests\Feature\Properties;

use App\Models\Feature;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_seeder_is_idempotent_and_convergent(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->assertSame(16, Feature::count());

        Feature::where('slug', 'alberca')->update(['name' => 'Nombre obsoleto']);
        $this->seed(FeatureSeeder::class);

        $this->assertSame(16, Feature::count());
        $this->assertDatabaseHas('features', ['slug' => 'alberca', 'name' => 'Alberca']);
        $this->assertDatabaseHas('features', ['slug' => 'seguridad-247']);
    }
}
