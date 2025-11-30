<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\info;

class AuthController extends Controller
{
    public function __construct(private User $user)
    {
    }

    public function login(LoginRequest $request)
    {
        try {
            $user = $this->user->where('email', $request->email)->first();
            if (! $user) {
                return response()->json(['error' => 'Não há um usuário cadastrado com o e-mail informado'], 404);
            }
            if (!Auth::attempt($request->validated())) {
                return response()->json([
                    'message' => 'Credenciais inválidas'
                ], 401);
            }
            // Gera token Sanctum
            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'message' => 'Login realizado com sucesso',
                'token' => $token,
                'user' => $user,
            ]);
        } catch (\Exception $exception) {
            info('Exception in login method auth controller: ' . $exception);

            return response()->json(['error' => 'Ocorreu um erro inesperado. Tente novamente ou contato a equipe de desenvolvimento!'], 500);
        }
    }
}
