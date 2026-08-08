<?php

namespace Database\Seeders;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with development accounts.
     *
     * Everything below is a test fixture (SPEC rule 2) : no data about
     * the real Hori cooperative is ever seeded.
     */
    public function run(): void
    {
        // Le référentiel commun et la base de règles, chargés depuis les
        // fichiers de référence (SPEC section 10).
        $this->call([
            CropSeeder::class,
            VarietySeeder::class,
            RuleSeeder::class,
        ]);

        User::factory()->superAdmin()->create([
            'name' => 'Super administrateur (fixture)',
            'phone' => '00000000',
        ]);

        $cooperative = Cooperative::factory()->create([
            'name' => 'Coopérative de démonstration (fixture)',
            'region' => 'Kara',
            'prefecture' => 'Kozah',
        ]);

        User::factory()->coopAdmin()->for($cooperative)->create([
            'name' => 'Administrateur de coopérative (fixture)',
            'phone' => '00000001',
        ]);

        User::factory()->technician()->for($cooperative)->create([
            'name' => 'Technicien (fixture)',
            'phone' => '00000002',
        ]);

        User::factory()->farmer()->for($cooperative)->create([
            'name' => 'Agriculteur (fixture)',
            'phone' => '00000003',
        ]);
    }
}
