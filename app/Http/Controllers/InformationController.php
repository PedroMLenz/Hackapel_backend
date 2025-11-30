<?php

namespace App\Http\Controllers;

use App\Http\Requests\Information\StoreInformationRequest;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Information;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        try {
            $data = $request->all();
            if (isset($data['message'])) {
                $chatId = $data['message']['chat']['id'];
                $nome   = $data['message']['from']['first_name'];
                Patient::updateOrCreate(
                    ['chat_id' => $chatId]
                );
                Http::post("https://api.telegram.org/bot".env('TELEGRAM_TOKEN')."/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "Olá $nome, cadastro feito!",
                ]);
            }
        } catch (\Exception $exception) {
            info('Exception in webhook method information controller: ' . $exception);

            return response()->json(['error' => 'Ocorreu um erro inesperado. Tente novamente ou contato a equipe de desenvolvimento!'], 500);
        }
    }
}
