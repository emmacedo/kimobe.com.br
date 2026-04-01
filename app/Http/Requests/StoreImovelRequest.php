<?php

namespace App\Http\Requests;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class StoreImovelRequest extends FormRequest
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
        if ($this->has('cep') && $this->cep) {
            $this->merge(['cep' => Sanitize::cep($this->cep)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cep' => ['required', 'string', 'max:9'], // Aceita com ou sem máscara (prepareForValidation sanitiza)
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'tipo' => ['required', 'in:apartamento,casa,sala,loja,galpao'],
            'status' => ['nullable', 'in:disponivel,alugado,manutencao,inativo'],
            'quartos' => ['nullable', 'integer', 'min:0'],
            'suites' => ['nullable', 'integer', 'min:0'],
            'banheiros' => ['nullable', 'integer', 'min:0'],
            'vagas_garagem' => ['nullable', 'integer', 'min:0'],
            'andar' => ['nullable', 'integer', 'min:0'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'valor_aluguel_sugerido' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cep.required' => 'O campo CEP é obrigatório.',
            'logradouro.required' => 'O campo logradouro é obrigatório.',
            'numero.required' => 'O campo número é obrigatório.',
            'bairro.required' => 'O campo bairro é obrigatório.',
            'cidade.required' => 'O campo cidade é obrigatório.',
            'uf.required' => 'O campo UF é obrigatório.',
            'uf.size' => 'O campo UF deve ter 2 caracteres.',
            'tipo.required' => 'Selecione o tipo do imóvel.',
            'tipo.in' => 'O tipo selecionado é inválido.',
            'status.in' => 'O status selecionado é inválido.',
            'quartos.min' => 'O número de quartos deve ser maior ou igual a zero.',
            'suites.min' => 'O número de suítes deve ser maior ou igual a zero.',
            'banheiros.min' => 'O número de banheiros deve ser maior ou igual a zero.',
            'vagas_garagem.min' => 'O número de vagas deve ser maior ou igual a zero.',
            'andar.min' => 'O andar deve ser maior ou igual a zero.',
            'area_m2.min' => 'A área deve ser maior ou igual a zero.',
            'valor_aluguel_sugerido.min' => 'O valor de aluguel deve ser maior ou igual a zero.',
            'observacoes.max' => 'As observações não podem ter mais de 5000 caracteres.',
        ];
    }
}
