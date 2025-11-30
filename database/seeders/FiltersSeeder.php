<?php

namespace Database\Seeders;

use App\Models\Filters;
use App\Models\Patient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class FiltersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = Patient::all();
        $bairrosMap = [];
        $doencasMap = [];
        $ageRangesMap = [];

        foreach ($patients as $patient) {
            // Bairro (agora usa o campo 'neighborhood' do model)
            $bairro = $patient->neighborhood ?? null;
            if ($bairro) {
                $bairrosMap[trim($bairro)] = true;
            }

            // Doenças (campo 'diseases' — pode ser array, JSON ou string separada por vírgula)
            $rawDiseases = $patient->diseases ?? null;
            if ($rawDiseases) {
                if (is_array($rawDiseases)) {
                    $diseasesList = $rawDiseases;
                } elseif (is_string($rawDiseases)) {
                    $decoded = json_decode($rawDiseases, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $diseasesList = $decoded;
                    } else {
                        $diseasesList = array_map('trim', explode(',', $rawDiseases));
                    }
                } else {
                    $diseasesList = [(string)$rawDiseases];
                }

                foreach ($diseasesList as $d) {
                    if ($d !== null && $d !== '') {
                        $doencasMap[trim($d)] = true;
                    }
                }
            }

            // Idade (usa 'age' ou calcula a partir de 'date_of_birth')
            $age = null;
            if (isset($patient->age) && $patient->age !== null) {
                $age = (int) $patient->age;
            } else {
                $dob = $patient->date_of_birth ?? null;
                if ($dob) {
                    try {
                        $age = Carbon::parse($dob)->age;
                    } catch (\Exception $e) {
                        $age = null;
                    }
                }
            }

            if ($age !== null && $age >= 0) {
                $start = intval(floor($age / 10) * 10);
                $range = $start >= 100 ? '100+' : sprintf('%d-%d', $start, $start + 9);
                $ageRangesMap[$range] = true;
            }
        }

        // Extrai chaves, ordena para consistência
        $bairros = array_values(array_keys($bairrosMap));
        sort($bairros, SORT_NATURAL | SORT_FLAG_CASE);

        $doencas = array_values(array_keys($doencasMap));
        sort($doencas, SORT_NATURAL | SORT_FLAG_CASE);

        $ageRanges = array_values(array_keys($ageRangesMap));
        sort($ageRanges, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($bairros as $bairro) {
            $filters = [
                'type' => 'neighborhood',
                'value' => $bairro,
            ];
            Filters::create($filters);
        }
        foreach ($doencas as $doenca) {
            $filters = [
                'type' => 'disease',
                'value' => $doenca,
            ];
            Filters::create($filters);
        }
        foreach ($ageRanges as $ageRange) {
            $filters = [
                'type' => 'age_range',
                'value' => $ageRange,
            ];
            Filters::create($filters);
        }
    }
}
