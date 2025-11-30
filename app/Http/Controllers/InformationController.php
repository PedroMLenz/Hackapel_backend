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
    public function __construct(Private Information $information){}

    public function indexByUser($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado.'], 404);
        }
        $informations = $this->information->where('user_id', $user->id)->get();
        if ($informations->isEmpty()) {
            return response()->json(['message' => 'Nenhuma informação encontrada para este usuário.'], 200);
        }

        return response()->json($informations, 200);
    }

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
                if (isset($filters['age_range'])) {
                    $ageGroup = $filters['age_range'];
                    if (is_string($ageGroup) && preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $ageGroup, $matches)) {
                        $start = (int) $matches[1];
                        $end = (int) $matches[2];
                        if ($start > $end) {
                            [$start, $end] = [$end, $start];
                        }
                        $patients->whereBetween('age', [$start, $end]);
                    } else {
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
                            default:
                                // unknown format — no age filter applied
                                break;
                        }
                    }
                }

                $patients = $patients->get();
            }

            $information = Information::create([
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'user_id' => $user->id,
                'filters' => is_array($filters) ? implode(',', $filters) : $filters,
                'patients_sended_count' => $patients->count(),
            ]);

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

    public function handleTelegramWebhook(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                Log::error('Webhook recebeu um payload inválido.');
                return response()->json(['ok' => true], 200);
            }
            Log::info('Webhook JSON:', $data);
            if (!isset($data['message'])) {
                Log::error('Sem campo message');
                return response()->json(['ok' => true], 200);
            }
            $faker = \Faker\Factory::create('pt_BR');
            $chatId = $data['message']['chat']['id'];
            $nome   = $data['message']['from']['first_name'];
            Log::info("Mensagem recebida de $chatId ($nome)", $data['message']);
            $patient = Patient::where('telegram_chat_id', $chatId)->first();
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
                Log::info('Criando paciente...', $novoPaciente);
                Patient::create($novoPaciente);
            }

            Http::post(
                "https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => "Olá $nome, cadastro feito!"
                ]
            );

            return response()->json(['ok' => true], 200);

        } catch (\Throwable $e) {

            Log::error('ERRO NO WEBHOOK:', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json(['error' => 'erro interno'], 500);
        }
    }
}
