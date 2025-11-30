<?php

namespace App\Http\Controllers;

use App\Http\Requests\Information\StoreInformationRequest;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Information;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\Specialization;
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
            if ($request->has('age_range')) {
                $filters['age_range'] = $request->input('age_range');
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
            // $data = json_decode($request->getContent(), true);
            // if (!$data) {
            //     Log::error('Webhook recebeu um payload inválido.');
            //     return response()->json(['ok' => true], 200);
            // }
            // Log::info('Webhook JSON:', $data);
            // if (!isset($data['message'])) {
            //     Log::error('Sem campo message');
            //     return response()->json(['ok' => true], 200);
            // }
            // $faker = \Faker\Factory::create('pt_BR');
            // $chatId = $data['message']['chat']['id'];
            // $nome   = $data['message']['from']['first_name'];
            // Log::info("Mensagem recebida de $chatId ($nome)", $data['message']);
            // $patient = Patient::where('telegram_chat_id', $chatId)->first();
            // if (!$patient) {
            //     $dob = $faker->dateTimeBetween('-90 years', '-18 years');
            //     $diseases = rand(0, 1);
            //     $novoPaciente = [
            //         'name' => $nome,
            //         'email' => $faker->unique()->safeEmail,
            //         'phone' => preg_replace('/\D/', '', $faker->phoneNumber),
            //         'zip_code' => preg_replace('/\D/', '', $faker->postcode),
            //         'state' => 'RS',
            //         'city' => 'Pelotas',
            //         'neighborhood' => $faker->randomElement(['Centro', 'Três Vendas', 'Areal', 'Fragata', 'Laranjal']),
            //         'street' => $faker->streetName,
            //         'number' => $faker->buildingNumber,
            //         'complement' => $faker->optional()->secondaryAddress,
            //         'date_of_birth' => $dob->format('Y-m-d'),
            //         'age' => Carbon::instance($dob)->age,
            //         'telegram_chat_id' => $chatId,
            //     ];
            //     if ($diseases) {
            //         $novoPaciente['diseases'] = json_encode(
            //             $faker->randomElements(
            //                 ['Diabetes', 'Hipertensão', 'Asma', 'Alergia', 'Depressão', 'Artrite'],
            //                 rand(1, 3)
            //             )
            //         );
            //     }
            //     Log::info('Criando paciente...', $novoPaciente);
            //     Patient::create($novoPaciente);
            // }

            // if ($data['message']['text'] === '/start') {
            //     Http::post(
            //         "https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage",
            //         [
            //             'chat_id' => $chatId,
            //             'text' => "Olá $nome! Bem-vindo ao nosso serviço de informações de saúde. Você receberá atualizações importantes aqui."
            //         ]
            //     );

            //     return response()->json(['ok' => true], 200);
            // }

             // 1. CAPTURA E VALIDAÇÃO DOS DADOS
            $data = $request->all(); // O Laravel já faz o json_decode automaticamente

            if (!isset($data['message']['text']) || !isset($data['message']['chat']['id'])) {
                return response()->json(['ok' => true]);
            }

            $chatId = $data['message']['chat']['id'];
            $userMessage = $data['message']['text'];
            $firstName = $data['message']['from']['first_name'] ?? 'Cidadão';

            // 2. COMANDOS BÁSICOS (/start)
            if ($userMessage === '/start') {
                $this->sendMessage($chatId, "Olá $firstName! Bem-vindo ao Notificai. Qual é o seu Bairro ou Cidade para eu localizar a unidade mais próxima?");
                return response()->json(['ok' => true]);
            }


            // 3. IDENTIFICAR O PACIENTE E O BAIRRO
            // Tenta achar o paciente. Se não achar, cria (mantendo sua lógica de Faker para teste)
            $patient = Patient::where('telegram_chat_id', $chatId)->first();

            $faker = \Faker\Factory::create('pt_BR');

            if (!$patient) {
                $dob = $faker->dateTimeBetween('-90 years', '-18 years');
                $diseases = rand(0, 1);
                $novoPaciente = [
                    'name' => $userMessage,
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
            }

            // 4. LÓGICA INTELIGENTE: BUSCAR DADOS DA REGIÃO
            // A IA precisa saber o contexto da região do usuário

            // Tenta achar uma UBS no mesmo bairro do paciente
            $ubsProxima = User::where('neighborhood', $patient->neighborhood)->first();

            // Se não achar no bairro, pega qualquer uma da cidade ou a primeira do banco
            if (!$ubsProxima) {
                $ubsProxima = User::first();
            }

            // Busca especialidades e horários dessa UBS específica
            $specialties = Specialization::all()->pluck('name')->join(', ');

            $schedulesInfo = "Horários padrão: 08:00 às 17:00";
            if ($ubsProxima) {
                $schedules = Schedule::where('user_id', $ubsProxima->id)->take(5)->get();
                if ($schedules->isNotEmpty()) {
                    $schedulesInfo = $schedules->map(fn($s) => "{$s->day_of_week}: {$s->open_time} às {$s->close_time}")->join("\n");
                }
            }

            // 5. MONTAR O CONTEXTO PARA A IA
            $dadosDoSistema = <<<DATA
            [DADOS DO PACIENTE]
            Nome: {$patient->name}
            Bairro de Residência: {$patient->neighborhood}

            [UNIDADE DE SAÚDE DE REFERÊNCIA]
            Nome: {$ubsProxima->name}
            Endereço: {$ubsProxima->street}, {$ubsProxima->number} - {$ubsProxima->neighborhood}

            [ESPECIALIDADES GERAIS DA REDE]
            {$specialties}

            [HORÁRIOS DA UNIDADE]
            {$schedulesInfo}
            DATA;

            // 6. MONTAR O PROMPT DO SISTEMA
            $systemPrompt = <<<PROMPT
            # IDENTIDADE
            Você é o Assistente Virtual do sistema "Notificai".

            # CONTEXTO
            {$dadosDoSistema}

            # REGRAS
            1. O usuário mora no bairro {$patient->neighborhood}. Indique a unidade "{$ubsProxima->name}" como a mais próxima.
            2. Se o usuário perguntar o endereço, forneça o endereço da unidade listada acima.
            3. Responda de forma curta e acolhedora.
            PROMPT;

            // 7. CHAMAR A OPENAI (Usando Laravel Http, bem mais limpo)
            $respostaIA = $this->callOpenAI($userMessage, $systemPrompt);

            // 8. ENVIAR RESPOSTA
            $this->sendMessage($chatId, $respostaIA);

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            Log::error('ERRO WEBHOOK:', ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json(['error' => 'Internal Error'], 500);
        }
    }

    /**
     * Envia mensagem para o Telegram
     */
    private function sendMessage($chatId, $text)
    {
        Http::post("https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    /**
     * Chama a OpenAI
     */
    private function callOpenAI($userMessage, $systemPrompt)
    {
        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4.1'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage]
                    ],
                    'temperature' => 0.5,
                ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'];
            }

            Log::error('OpenAI Error', $response->json());
            return "Desculpe, estou com dificuldade de acessar o sistema agora.";
        } catch (\Exception $e) {
            return "Erro de conexão com a inteligência.";
        }
    }
}
