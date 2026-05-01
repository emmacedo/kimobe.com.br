<?php

namespace App\Http\Requests;

use App\Models\DadosBancarios;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTitularidadeRequest extends FormRequest
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
        // Update não permite trocar o vinculo_id (proprietário) — apenas tipo, papel, percentual, conta.
        return [
            'tipo_titular' => ['required', 'in:pessoa_fisica,empresa,inventario'],
            'papel' => ['required', 'in:responsavel,observador'],
            'percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'dados_bancarios_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $imovel = $this->route('imovel');
            $titularidade = $this->route('titularidade');
            if (! $imovel || ! $titularidade) {
                return;
            }

            $percentual = (float) $this->input('percentual', 0);
            $dadosBancariosId = $this->input('dados_bancarios_id');

            // Soma — exclui o percentual atual desta titularidade.
            $somaOutros = (float) Titularidade::withoutGlobalScopes([TenantScope::class])
                ->where('imovel_id', $imovel->id)
                ->where('id', '!=', $titularidade->id)
                ->sum('percentual');

            if (($somaOutros + $percentual) > 100.005) {
                $v->errors()->add('percentual', 'A soma dos percentuais ultrapassaria 100%. Disponível: '.number_format(100 - $somaOutros, 2).'%.');
            }

            if ($dadosBancariosId) {
                $valida = DadosBancarios::withoutGlobalScopes([TenantScope::class])
                    ->where('id', $dadosBancariosId)
                    ->where('vinculo_id', $titularidade->vinculo_id)
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
            'tipo_titular.required' => 'Selecione o tipo de titular.',
            'papel.required' => 'Selecione o papel do titular.',
            'percentual.required' => 'Informe o percentual de propriedade.',
            'percentual.min' => 'O percentual deve ser maior que zero.',
            'percentual.max' => 'O percentual não pode ultrapassar 100%.',
        ];
    }
}
