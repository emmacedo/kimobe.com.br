<?php

namespace App\Http\Requests;

class UpdateContratoRequest extends StoreContratoRequest
{
    /**
     * Na edição, imóvel e inquilino são imutáveis.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Remove validação de imóvel e inquilino (campos disabled na edição)
        unset($rules['imovel_id'], $rules['inquilino_vinculo_id']);

        return $rules;
    }
}
