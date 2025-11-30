<?php

namespace Database\Seeders;

use App\Models\Patient;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $dob = fake()->dateTimeBetween('-90 years', '-18 years');
            $diseases = rand(0, 1);

            $data = [
                'name' => fake()->name,
                'email' => fake()->unique()->safeEmail,
                'phone' => preg_replace('/\D/', '', fake()->phoneNumber),
                'zip_code' => preg_replace('/\D/', '', fake()->postcode),
                'state' => 'RS',
                'city' => 'Pelotas',
                'neighborhood' => fake()->randomElement(['Centro', 'Três Vendas', 'Areal', 'Fragata', 'Laranjal']),
                'street' => fake()->streetName,
                'number' => fake()->buildingNumber,
                'complement' => fake()->optional()->secondaryAddress,
                'date_of_birth' => $dob->format('Y-m-d'),
                'age' => Carbon::instance($dob)->age,
            ];

            if ($diseases) {
                $data['diseases'] = json_encode(
                    fake()->randomElements(
                        ['Diabetes', 'Hipertensão', 'Asma', 'Alergia', 'Depressão', 'Artrite'],
                        rand(1, 3)
                    )
                );
            }

            $data['telegram_chat_id'] = '5552563450';

            Patient::create($data);
        }
    }
}
