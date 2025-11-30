<?php

namespace App\Http\Requests\Information;

use Illuminate\Foundation\Http\FormRequest;

class StoreInformationRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'neighborhood' => 'sometimes|string|exists:filters,value',
            'disease' => 'sometimes|string|exists:filters,value',
            'age_group' => 'sometimes|string|exists:filters,value',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'title.string' => 'O título deve ser uma string.',
            'title.max' => 'O título não pode exceder 255 caracteres.',
            'content.required' => 'O conteúdo é obrigatório.',
            'content.string' => 'O conteúdo deve ser uma string.',
            'neighborhood.string' => 'O bairro deve ser uma string.',
            'neighborhood.exists' => 'O bairro selecionado é inválido.',
            'disease.string' => 'A doença deve ser uma string.',
            'disease.exists' => 'A doença selecionada é inválida.',
            'age_group.string' => 'O grupo etário deve ser uma string.',
            'age_group.exists' => 'O grupo etário selecionado é inválido.',
        ];
    }
}
