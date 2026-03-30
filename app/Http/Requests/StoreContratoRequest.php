<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContratoRequest extends FormRequest
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
        return [
            // Imóvel e inquilino
            'imovel_id' => ['required', 'integer'],
            'inquilino_vinculo_id' => ['required', 'integer'],

            // Valores e vigência
            'valor_aluguel' => ['required', 'numeric', 'min:0.01'],
            'dia_vencimento' => ['required', 'integer', 'between:1,28'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'indice_reajuste' => ['required', 'in:igpm,ipca,fixo'],
            'mes_reajuste' => ['required', 'integer', 'between:1,12'],

            // Modelo de repasse
            'modelo_repasse' => ['required', 'in:por_recebimento,garantido'],
            'taxa_administracao_pct' => ['required', 'numeric', 'between:0,100'],
            'taxa_seguro_inadimplencia_pct' => ['nullable', 'numeric', 'between:0,100'],

            // Multas e juros
            'multa_atraso_pct' => ['required', 'numeric', 'between:0,100'],
            'juros_atraso_pct_dia' => ['required', 'numeric', 'between:0,10'],
            'dias_carencia' => ['required', 'integer', 'min:0'],
            'multa_rescisoria_pct' => ['nullable', 'numeric', 'between:0,100'],
            'desconto_pontualidade_pct' => ['nullable', 'numeric', 'between:0,100'],

            // Garantia
            'tipo_garantia' => ['required', 'in:caucao,fiador,seguro_fianca,titulo_capitalizacao,sem_garantia'],

            // Campos condicionais de garantia
            'garantia_valor' => ['nullable', 'numeric', 'min:0'],
            'garantia_seguradora' => ['nullable', 'string', 'max:255'],
            'garantia_numero_apolice' => ['nullable', 'string', 'max:100'],
            'garantia_numero_titulo' => ['nullable', 'string', 'max:100'],
            'garantia_data_inicio' => ['nullable', 'date'],
            'garantia_data_fim' => ['nullable', 'date'],

            // Observações
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imovel_id.required' => 'Selecione o imóvel.',
            'inquilino_vinculo_id.required' => 'Selecione o inquilino.',
            'valor_aluguel.required' => 'O valor do aluguel é obrigatório.',
            'valor_aluguel.min' => 'O valor do aluguel deve ser maior que zero.',
            'dia_vencimento.required' => 'Selecione o dia de vencimento.',
            'dia_vencimento.between' => 'O dia de vencimento deve ser entre 1 e 28.',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_fim.required' => 'A data de término é obrigatória.',
            'data_fim.after' => 'A data de término deve ser posterior à data de início.',
            'modelo_repasse.required' => 'Selecione o modelo de repasse.',
            'taxa_administracao_pct.required' => 'A taxa de administração é obrigatória.',
            'indice_reajuste.required' => 'Selecione o índice de reajuste.',
            'mes_reajuste.required' => 'Selecione o mês de reajuste.',
            'multa_atraso_pct.required' => 'A multa por atraso é obrigatória.',
            'juros_atraso_pct_dia.required' => 'Os juros por dia são obrigatórios.',
            'dias_carencia.required' => 'Os dias de carência são obrigatórios.',
            'tipo_garantia.required' => 'Selecione o tipo de garantia.',
        ];
    }
}
