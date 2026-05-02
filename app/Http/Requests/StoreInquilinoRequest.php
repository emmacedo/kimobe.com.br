<?php

namespace App\Http\Requests;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquilinoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('documento')) {
            $merge['documento'] = Sanitize::digits((string) $this->documento);
        }

        if ($this->filled('telefone')) {
            $merge['telefone'] = Sanitize::telefone((string) $this->telefone);
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userIdToIgnore = $this->route('inquilino')?->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'tipo_pessoa' => ['required', 'in:pf,pj'],
            'documento' => [
                'nullable',
                'string',
                $this->input('tipo_pessoa') === 'pj'
                    ? 'regex:/^\d{14}$/'
                    : 'regex:/^\d{11}$/',
            ],
            'telefone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userIdToIgnore),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'tipo_pessoa.required' => 'Selecione o tipo de pessoa (PF ou PJ).',
            'tipo_pessoa.in' => 'Tipo de pessoa inválido.',
            'documento.regex' => $this->input('tipo_pessoa') === 'pj'
                ? 'O CNPJ deve ter 14 dígitos.'
                : 'O CPF deve ter 11 dígitos.',
            'telefone.regex' => 'O telefone deve ter 10 ou 11 dígitos.',
            'email.email' => 'Informe um email válido.',
            'email.unique' => 'Este email já está cadastrado por outro usuário.',
        ];
    }
}
