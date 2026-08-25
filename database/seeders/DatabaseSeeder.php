<?php

namespace Database\Seeders;

use App\Models\Exploitation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with development accounts.
     *
     * Everything below is a test fixture (SPEC rule 2) : no data about
     * the real Hori exploitation is ever seeded.
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

        $exploitation = Exploitation::factory()->create([
            'name' => 'Exploitation de démonstration (fixture)',
            'region' => 'Kara',
            'prefecture' => 'Kozah',
        ]);

        User::factory()->exploitationAdmin()->for($exploitation)->create([
            'name' => 'Administrateur d\'exploitation (fixture)',
            'phone' => '00000001',
        ]);

        User::factory()->technician()->for($exploitation)->create([
            'name' => 'Technicien (fixture)',
            'phone' => '00000002',
        ]);

        User::factory()->farmer()->for($exploitation)->create([
            'name' => 'Agriculteur (fixture)',
            'phone' => '00000003',
        ]);
    }
}
