<?php

namespace App\Http\Requests;

class UpdateContratoRequest extends StoreContratoRequest
{
    /**
     * Na edição, imóvel e inquilinos são imutáveis (gerenciados via sub-controller
     * ContratoInquilinoController). O withValidator do parent também não roda
     * porque os campos foram removidos.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Remove validação de imóvel (campo disabled na edição) e de inquilinos
        // (gerenciados via /contratos/{contrato}/inquilinos pelo controller separado).
        unset(
            $rules['imovel_id'],
            $rules['inquilinos'],
            $rules['inquilinos.*.vinculo_id'],
            $rules['inquilinos.*.principal'],
        );

        return $rules;
    }

    /**
     * Sobrescreve para NÃO rodar as validações cruzadas do StoreContratoRequest
     * (imóvel ativo, principal único, etc.) que não fazem sentido na edição.
     */
    public function withValidator($validator): void
    {
        // No-op: validações cruzadas só rodam no store.
    }
}
