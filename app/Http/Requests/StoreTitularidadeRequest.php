<?php

namespace App\Http\Requests;

use App\Models\DadosBancarios;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Services\TenantService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTitularidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return [
            'vinculo_id' => [
                'required', 'integer',
                Rule::exists('vinculos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('papel', 'proprietario')
                    ->where('status', 'ativo'),
            ],
            'tipo_titular' => ['required', 'in:pessoa_fisica,empresa,inventario'],
            'papel' => ['required', 'in:responsavel,observador'],
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'dados_bancarios_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Validações cruzadas: unicidade do vínculo, soma de percentuais e
     * pertinência da conta bancária ao vínculo.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $imovelId = $this->route('imovel')?->id;
            if (! $imovelId) {
                return;
            }

            $vinculoId = $this->input('vinculo_id');
            $percentual = (float) $this->input('percentual', 0);
            $dadosBancariosId = $this->input('dados_bancarios_id');

            // Unicidade: este vínculo já não pode ser titular ATIVO deste imóvel.
            $jaExiste = Titularidade::withoutGlobalScopes([TenantScope::class])
                ->where('imovel_id', $imovelId)
                ->where('vinculo_id', $vinculoId)
                ->exists();

            if ($jaExiste) {
                $v->errors()->add('vinculo_id', 'Este proprietário já é titular deste imóvel.');

                return;
            }

            // Soma de percentuais não pode ultrapassar 100% (margem para float).
            $somaAtual = (float) Titularidade::withoutGlobalScopes([TenantScope::class])
                ->where('imovel_id', $imovelId)
                ->sum('percentual');

            if (($somaAtual + $percentual) > 100.005) {
                $v->errors()->add('percentual', 'A soma dos percentuais ultrapassaria 100%. Disponível: '.number_format(100 - $somaAtual, 2).'%.');
            }

            // Conta bancária deve pertencer ao vínculo.
            if ($dadosBancariosId) {
                $valida = DadosBancarios::withoutGlobalScopes([TenantScope::class])
                    ->where('id', $dadosBancariosId)
                    ->where('vinculo_id', $vinculoId)
                    ->exists();

                if (! $valida) {
                    $v->errors()->add('dados_bancarios_id', 'Conta bancária inválida para este proprietário.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vinculo_id.required' => 'Selecione o proprietário.',
            'vinculo_id.exists' => 'Proprietário não encontrado.',
            'tipo_titular.required' => 'Selecione o tipo de titular.',
            'papel.required' => 'Selecione o papel do titular.',
            'percentual.required' => 'Informe o percentual de propriedade.',
            'percentual.min' => 'O percentual deve ser maior que zero.',
            'percentual.max' => 'O percentual não pode ultrapassar 100%.',
        ];
    }
}
