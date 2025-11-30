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

            if ($data['message']['text'] === '/start') {
                Http::post(
                    "https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage",
                    [
                        'chat_id' => $chatId,
                        'text' => "Olá $nome! Bem-vindo ao nosso serviço de informações de saúde. Você receberá atualizações importantes aqui."
                    ]
                );

                return response()->json(['ok' => true], 200);
            }
            $user = User::where('neighborhood', $data['message']['text'])->exists();
            if($user) {
                $schedules = Schedule::where('user_id', $user->id)->get();
                $horarios = "Aqui estão os horários disponíveis na sua região:\n";
                foreach($schedules as $schedule) {
                    $horarios .= "- Dia da semana: " . $schedule->day_of_week . ", das " . $schedule->open_time . " às " . $schedule->close_time . "\n";
                }
                $specializations = Specialization::whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->get();
                $addresses = $user->neighborhood . ": " . $user->street . ", " . $user->number . "\n";
                $dados_do_sistema = "Especializações disponíveis: " . $specializations->pluck('name')->join(', ') . ".\n" .
                    "Locais de atendimento: " . $addresses .
                    "Horários de atendimento: " . $horarios;
            }
            else {

            }

            $system_prompt = <<<PROMPT
            # IDENTIDADE
            Você é o *Assistente Virtual de Saúde* oficial do sistema "Notificai".
            Sua função é auxiliar cidadãos a encontrar informações sobre unidades de saúde, especialidades médicas e agendamentos.

            # TOM DE VOZ
            - *Empático e Respeitoso:* Lembre-se que o usuário pode estar doente ou preocupado.
            - *Direto e Claro:* Evite termos técnicos médicos complexos. Use linguagem acessível.
            - *Idioma:* Português do Brasil (pt-BR).

            # REGRAS (MUITO IMPORTANTE)
            1. *Veracidade:* Você SÓ pode responder com base nos dados fornecidos na seção "CONTEXTO DE DADOS" abaixo.
            2. *Anti-Alucinação:* Se a informação não estiver nos dados abaixo, responda: "Desculpe, não tenho essa informação no momento. Por favor, entre em contato com a secretaria de saúde."
            3. *Escopo:* Se o usuário perguntar sobre política, futebol, código ou qualquer coisa fora de saúde/agendamento, diga educadamente que só pode ajudar com questões de saúde.
            4. *Não Invente:* NUNCA invente horários, nomes de médicos ou endereços que não estejam listados.
            5. "Se o usuário disser termos populares, associe à especialidade correta. Ex: 'Médico de coração' = Cardiologia; 'Médico de pele' = Dermatologia; 'Médico de criança' = Pediatria."
            6.  Se o usuário perguntar horário, verifique a lista de 'Schedules'.
            Se procurar médico, verifique 'Specializations' e o endereço em 'Locais'.
            3. Se não tiver a informação, diga que não encontrou.

            # FORMATO DE RESPOSTA
            - Se for listar horários ou locais, use tópicos (bullet points) para facilitar a leitura.
            - Não envie textos muito longos (máximo de 3 parágrafos).

            ---
            # CONTEXTO DE DADOS (BASE DE CONHECIMENTO)
            As informações oficiais atualizadas são as seguintes:

            {{DADOS_DO_SISTEMA}}

            ---

            # EXEMPLOS DE INTERAÇÃO

            *Usuário:* "Quero marcar um cardiologista."
            *Você:* "Para cardiologia, você pode procurar a *UBS Centro* (Rua das Flores, 123) ou o *Hospital Geral*. O atendimento é das 08:00 às 17:00."

            *Usuário:* "Quem ganhou o jogo ontem?"
            *Você:* "Sou um assistente focado apenas em saúde e agendamentos. Posso ajudar com algo relacionado a isso?"
                    PROMPT;

            // Recebe os dados brutos do Telegram
            $update = file_get_contents('php://input');
            $data = json_decode($update, true);
            // Verifica se a requisição contém uma mensagem válida
            if (!isset($data['message']['text']) || !isset($data['message']['chat']['id'])) {
                // Retorna OK para o Telegram, mas não faz nada
                http_response_code(200);
                exit;
            }
            $chat_id = $data['message']['chat']['id'];
            $mensagem_usuario = $data['message']['text'];
            // Chama a função que se comunica com a OpenAI
            // $resposta_ia = $this->chamar_openai($mensagem_usuario, $system_prompt);
            Http::post(
                    "https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage",
                    [
                        'chat_id' => $chat_id,
                        // 'text' => $resposta_ia
                        'text' => $dados_do_sistema
                    ]
                );
            // Resposta final ao Telegram (indica que a mensagem foi processada)
            http_response_code(200);
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

    /**
     * Faz a requisição POST para a API de Chat Completion da OpenAI.
     */
    private function chamar_openai($prompt_usuario, $system_prompt) {
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $prompt_usuario]
        ];
        $data = [
            'model' => env('OPENAI_MODEL'),
            'messages' => $messages,
            'temperature' => 0.7,
        ];
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . env('OPENAI_API_KEY')
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if ($http_code !== 200) {
            $error_message = $response_data['error']['message'] ?? "Erro HTTP $http_code. Verifique a chave da OpenAI e os créditos.";
            return "Desculpe, o sistema de Inteligência Artificial está com problemas: $error_message";
        }

        if (isset($response_data['choices'][0]['message']['content'])) {
            return $response_data['choices'][0]['message']['content'];
        }

        return null;
    }
}
