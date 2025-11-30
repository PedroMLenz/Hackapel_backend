<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'UBS Teste',
            'email' => 'ubs@example.com',
            'password' => bcrypt('password'),
            'zipcode' => '96081700',
            'neighborhood' => 'Areal',
            'street' => 'Rua Dr. Barcelos',
            'number' => 600,
        ]);

        $this->call(PatientSeeder::class);
        $this->call(FiltersSeeder::class);
    }
}
