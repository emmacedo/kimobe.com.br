<?php

namespace App\Http\Requests;

use App\Services\TenantService;
use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCondominioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza valor monetário antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('valor')) {
            $this->merge(['valor' => Sanitize::moeda($this->input('valor'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return [
            'entidade_externa_id' => [
                'nullable', 'integer',
                Rule::exists('entidades_externas', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'dia_vencimento' => ['nullable', 'integer', 'between:1,31'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'acesso_login' => ['nullable', 'string', 'max:255'],
            'acesso_senha' => ['nullable', 'string', 'max:255'],
            'acesso_descricao' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entidade_externa_id.exists' => 'A entidade externa selecionada é inválida.',
            'dia_vencimento.between' => 'O dia de vencimento deve estar entre 1 e 31.',
            'valor.min' => 'O valor do condomínio deve ser maior ou igual a zero.',
            'acesso_descricao.max' => 'A descrição de acesso não pode ter mais de 5000 caracteres.',
        ];
    }
}
