<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialties = [
            // Atenção Básica
            ['name' => 'Clínica Geral'],
            ['name' => 'Medicina de Família e Comunidade'],
            ['name' => 'Pediatria'],
            ['name' => 'Ginecologia e Obstetrícia'],

            // Especialidades Médicas
            ['name' => 'Cardiologia'],
            ['name' => 'Ortopedia e Traumatologia'],
            ['name' => 'Dermatologia'],
            ['name' => 'Oftalmologia'],
            ['name' => 'Psiquiatria'],
            ['name' => 'Neurologia'],
            ['name' => 'Endocrinologia'],
            ['name' => 'Gastroenterologia'],
            ['name' => 'Urologia'],

            ['name' => 'Otorrinolaringologia'],
            ['name' => 'Pneumologia'],
            ['name' => 'Reumatologia'],
            ['name' => 'Oncologia Clínica'],
            ['name' => 'Geriatria'],
            ['name' => 'Infectologia'],
            ['name' => 'Nefrologia'],
            ['name' => 'Hematologia'],
            ['name' => 'Mastologia'],
            ['name' => 'Angiologia e Cirurgia Vascular'],
            ['name' => 'Neurocirurgia'],
            ['name' => 'Cirurgia Geral'],
            ['name' => 'Coloproctologia'],

            // Multidisciplinar
            ['name' => 'Odontologia'],
            ['name' => 'Psicologia'],
            ['name' => 'Fisioterapia'],
            ['name' => 'Nutrição'],
            ['name' => 'Fonoaudiologia'],
            ['name' => 'Enfermagem'],
            ['name' => 'Terapia Ocupacional'],
            ['name' => 'Serviço Social'],
        ];
        foreach ($specialties as $spec) {
            Specialization::firstOrCreate($spec);
        }
    }
}
