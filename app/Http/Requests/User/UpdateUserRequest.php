<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['sometimes', 'array', 'min:1'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.open_time' => ['required', 'date_format:H:i'],
            'schedules.*.close_time' => ['required', 'date_format:H:i'],
            'specializations' => ['sometimes', 'array', 'min:1'],
            'specializations.*' => ['required', 'string', 'max:255', 'exists:specializations,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedules.array' => 'O campo de horários deve ser um array.',
            'schedules.min' => 'O campo de horários deve conter ao menos :min item.',
            'schedules.*.day_of_week.between' => 'O dia da semana deve estar entre 0 (Domingo) e 6 (Sábado).',
            'schedules.*.open_time.date_format' => 'O horário de abertura deve estar no formato HH:MM.',
            'schedules.*.close_time.date_format' => 'O horário de fechamento deve estar no formato HH:MM.',
            'specializations.array' => 'O campo de especializações deve ser um array.',
            'specializations.min' => 'O campo de especializações deve conter ao menos :min item.',
            'specializations.*.exists' => 'A especialização informada não existe.',
        ];
    }
}
