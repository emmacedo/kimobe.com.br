<?php

namespace App\Http\Requests;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemCobrancaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('valor_unitario') && is_string($this->valor_unitario)) {
            $this->merge(['valor_unitario' => Sanitize::moeda($this->input('valor_unitario'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:255'],
            'pagante' => ['required', Rule::in(['inquilino', 'proprietario', 'administradora'])],
            'recebedor' => ['required', Rule::in(['inquilino', 'proprietario', 'administradora'])],
            'entidade_externa_id' => ['nullable', 'integer', 'exists:entidades_externas,id'],
            'tipo' => ['required', Rule::in(['recorrente', 'parcelado', 'avulso'])],
            'periodicidade' => [
                'nullable',
                Rule::in(['mensal', 'bimestral', 'trimestral', 'semestral', 'anual']),
                Rule::requiredIf(fn () => $this->input('tipo') === 'recorrente'),
            ],
            'num_parcelas_total' => [
                'nullable',
                'integer',
                'min:1',
                'max:360',
                Rule::requiredIf(fn () => $this->input('tipo') === 'parcelado'),
            ],
            'valor_unitario' => ['required', 'numeric'],
            'dia_vencimento' => ['nullable', 'integer', 'between:1,28'],
            'mes_referencia' => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
            'visivel_inquilino' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descricao.required' => 'Descrição é obrigatória.',
            'pagante.required' => 'Selecione quem paga.',
            'recebedor.required' => 'Selecione quem recebe.',
            'tipo.required' => 'Selecione o tipo do item.',
            'periodicidade.required' => 'Itens recorrentes exigem periodicidade.',
            'num_parcelas_total.required' => 'Itens parcelados exigem número de parcelas.',
            'mes_referencia.regex' => 'Mês de referência deve ser MM/YYYY (ex: 07/2026).',
            'dia_vencimento.integer' => 'Dia de vencimento deve ser um número.',
            'dia_vencimento.between' => 'Dia de vencimento deve estar entre 1 e 28.',
            'entidade_externa_id.exists' => 'Entidade externa selecionada é inválida.',
        ];
    }
}
