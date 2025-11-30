<?php

namespace App\Http\Controllers;

use App\Http\Requests\Information\StoreInformationRequest;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Information;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Log;

class InformationController extends Controller
{
    public function store($id, StoreInformationRequest $request)
    {
        try {
            $user = User::findOrFail($id);
        $filters = [];
        if ($request->has('neighborhood')) {
            $filters['neighborhood'] = $request->input('neighborhood');
        }
        if ($request->has('disease')) {
            $filters['disease'] = $request->input('disease');
        }
        if ($request->has('age_group')) {
            $filters['age_group'] = $request->input('age_group');
        }
        if (empty($filters)) {
            $filters = 'all';
        }
        $information = Information::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'user_id' => $user->id,
            'filters' => is_array($filters) ? implode(',', $filters) : $filters,
        ]);

        if($filters == 'all') {
            $patients = Patient::all();
        } else {
            $patients = Patient::query();

            if (isset($filters['neighborhood'])) {
                $patients->where('neighborhood', $filters['neighborhood']);
            }
            if (isset($filters['disease'])) {
                $patients->whereJsonContains('diseases', $filters['disease']);
            }
            if (isset($filters['age_group'])) {
                $ageGroup = $filters['age_group'];
                switch ($ageGroup) {
                    case 'child':
                        $patients->where('age', '<=', 12);
                        break;
                    case 'teen':
                        $patients->whereBetween('age', [13, 19]);
                        break;
                    case 'adult':
                        $patients->whereBetween('age', [20, 59]);
                        break;
                    case 'senior':
                        $patients->where('age', '>=', 60);
                        break;
                }
            }

            $patients = $patients->get();
        }

        foreach ($patients as $patient) {
            dispatch(new SendTelegramMessageJob(
                $patient->telegram_chat_id,
                $information->content
            ));
        }

        return response()->json($information, 201);
        } catch (\Exception $exception) {
            info('Exception in store method information controller: ' . $exception);

            return response()->json(['error' => 'Ocorreu um erro inesperado. Tente novamente ou contato a equipe de desenvolvimento!'], 500);
        }
    }

    public function webhook(Request $request)
    {
        Log::info('WEBHOOK RECEBIDO:', $request->all());

        try {

            $faker = \Faker\Factory::create('pt_BR');

            $data = $request->all();
            Log::info('Dados recebidos:', $data);

            if (!isset($data['message'])) {
                Log::error('Webhook sem message');
                return response()->json(['ok' => true], 200);
            }

            $chatId = $data['message']['chat']['id'];
            $nome   = $data['message']['from']['first_name'];

            Log::info("Chat ID recebido: $chatId");

            $patient = Patient::where('telegram_chat_id', $chatId)->first();
            Log::info('Paciente encontrado?', ['patient' => $patient]);

            if (!$patient) {

                $dob = $faker->dateTimeBetween('-90 years', '-18 years');
                $diseases = rand(0, 1);

                $novoPaciente = [
                    'name' => $nome,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => preg_replace('/\D/', '', $faker->phoneNumber),
                    'zip_code' => preg_replace('/\D/', '', $faker->postcode),
                    'state' => 'RS',
                    'city' => 'Pelotas',
                    'neighborhood' => $faker->randomElement(['Centro', 'Três Vendas', 'Areal', 'Fragata', 'Laranjal']),
                    'street' => $faker->streetName,
                    'number' => $faker->buildingNumber,
                    'complement' => $faker->optional()->secondaryAddress,
                    'date_of_birth' => $dob->format('Y-m-d'),
                    'age' => Carbon::instance($dob)->age,
                    'telegram_chat_id' => $chatId,
                ];

                if ($diseases) {
                    $novoPaciente['diseases'] = json_encode(
                        $faker->randomElements(
                            ['Diabetes', 'Hipertensão', 'Asma', 'Alergia', 'Depressão', 'Artrite'],
                            rand(1, 3)
                        )
                    );
                }

                Log::info('Criando novo paciente:', $novoPaciente);
                Patient::create($novoPaciente);
            }

            // Enviar resposta ao Telegram
            $resposta = Http::post(
                "https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => "Olá $nome, cadastro feito!"
                ]
            );

            Log::info('Resposta do Telegram:', $resposta->json());

            return response()->json(['ok' => true], 200);

        } catch (\Throwable $exception) {

            Log::error('ERRO NO WEBHOOK:', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Erro interno no webhook!'], 500);
        }
    }

}
