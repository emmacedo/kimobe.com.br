<?php

namespace App\Http\Requests;

use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemCobrancaRequest extends FormRequest
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
            'escopo' => ['required', Rule::in(['somente', 'futuras', 'todas'])],
            'descricao' => ['sometimes', 'string', 'max:255'],
            'pagante' => ['sometimes', Rule::in(['inquilino', 'proprietario', 'administradora'])],
            'recebedor' => ['sometimes', Rule::in(['inquilino', 'proprietario', 'administradora'])],
            'entidade_externa_id' => ['nullable', 'integer', 'exists:entidades_externas,id'],
            'valor_unitario' => ['sometimes', 'numeric'],
            'visivel_inquilino' => ['sometimes', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'escopo.required' => 'Defina o escopo: somente, futuras ou todas.',
            'escopo.in' => 'Escopo inválido. Use somente, futuras ou todas.',
            'entidade_externa_id.exists' => 'Entidade externa selecionada é inválida.',
        ];
    }

    /**
     * Retorna apenas os atributos atualizáveis (sem o campo `escopo`).
     *
     * @return array<string, mixed>
     */
    public function attributesParaAtualizar(): array
    {
        return collect($this->validated())
            ->except(['escopo'])
            ->all();
    }
}
