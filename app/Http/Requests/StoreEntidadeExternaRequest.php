<?php

namespace App\Http\Requests;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntidadeExternaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza campos com máscara antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('cpf_cnpj')) {
            $merge['cpf_cnpj'] = Sanitize::digits((string) $this->cpf_cnpj);
        }

        if ($this->filled('telefone')) {
            $merge['telefone'] = Sanitize::telefone((string) $this->telefone);
        }

        if ($this->filled('cep')) {
            $merge['cep'] = Sanitize::cep((string) $this->cep);
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
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => [
                'required',
                Rule::in([
                    'administradora_condominio',
                    'sindico',
                    'prefeitura',
                    'seguradora',
                    'prestador_servico',
                    'empresa',
                    'pessoa_fisica',
                    'outro',
                ]),
            ],
            'cpf_cnpj' => ['nullable', 'string', 'regex:/^\d{11}$|^\d{14}$/'],
            'telefone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'contato_interno_nome' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'tipo.required' => 'O campo tipo é obrigatório.',
            'tipo.in' => 'O tipo selecionado é inválido.',
            'cpf_cnpj.regex' => 'O CPF deve ter 11 dígitos ou o CNPJ deve ter 14 dígitos.',
            'telefone.regex' => 'O telefone deve ter 10 ou 11 dígitos.',
            'email.email' => 'Informe um email válido.',
            'cep.regex' => 'O CEP deve ter 8 dígitos.',
            'uf.size' => 'O campo UF deve ter 2 caracteres.',
            'observacoes.max' => 'As observações não podem ter mais de 5000 caracteres.',
        ];
    }
}
