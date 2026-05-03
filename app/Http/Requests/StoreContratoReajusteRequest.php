<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContratoReajusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valor_novo' => ['required', 'numeric', 'gt:0'],
            'data_aplicacao' => ['required', 'date'],
            'indice_usado' => ['required', 'in:igpm,ipca,fixo,manual'],
            'origem' => ['required', 'in:reajuste_anual,aditivo,renegociacao,correcao'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'valor_novo' => 'novo valor do aluguel',
            'data_aplicacao' => 'data de aplicação',
            'indice_usado' => 'índice utilizado',
            'origem' => 'origem do reajuste',
            'observacao' => 'observação',
        ];
    }
}
