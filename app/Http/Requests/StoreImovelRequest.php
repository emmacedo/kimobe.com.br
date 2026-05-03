<?php

namespace App\Http\Requests;

use App\Models\DadosBancarios;
use App\Services\TenantService;
use App\Support\Sanitize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        if (is_array($this->input('condominio'))) {
            $condominio = $this->input('condominio');
            if (isset($condominio['valor']) && $condominio['valor'] !== '' && $condominio['valor'] !== null) {
                $condominio['valor'] = Sanitize::moeda($condominio['valor']);
                $this->merge(['condominio' => $condominio]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return [
            'cep' => ['required', 'string', 'max:9'], // Aceita com ou sem máscara (prepareForValidation sanitiza)
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'inscricao_iptu' => ['nullable', 'string', 'max:50'],
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

            // Condomínio (opcional — se presente, todos os campos são opcionais individualmente)
            'condominio' => ['nullable', 'array'],
            'condominio.entidade_externa_id' => [
                'nullable', 'integer',
                Rule::exists('entidades_externas', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'condominio.dia_vencimento' => ['nullable', 'integer', 'between:1,31'],
            'condominio.valor' => ['nullable', 'numeric', 'min:0'],
            'condominio.acesso_login' => ['nullable', 'string', 'max:255'],
            'condominio.acesso_senha' => ['nullable', 'string', 'max:255'],
            'condominio.acesso_descricao' => ['nullable', 'string', 'max:5000'],

            // Titulares (opcional — pode ser cadastrado depois na edição)
            'titulares' => ['nullable', 'array'],
            'titulares.*.vinculo_id' => [
                'required', 'integer',
                Rule::exists('vinculos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('papel', 'proprietario')
                    ->where('status', 'ativo'),
            ],
            'titulares.*.tipo_titular' => ['required', 'in:pessoa_fisica,empresa,inventario'],
            'titulares.*.papel' => ['required', 'in:responsavel,observador'],
            'titulares.*.percentual' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'titulares.*.dados_bancarios_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Validações cruzadas dos titulares: exatamente 1 responsável, soma ≤ 100, vinculo_id único.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $titulares = $this->input('titulares', []);
            if (! is_array($titulares) || count($titulares) === 0) {
                return;
            }

            $responsaveis = collect($titulares)->where('papel', 'responsavel')->count();
            if ($responsaveis !== 1) {
                $v->errors()->add('titulares', 'Deve haver exatamente 1 titular marcado como responsável.');
            }

            $soma = collect($titulares)->sum(fn ($t) => (float) ($t['percentual'] ?? 0));
            if ($soma > 100.005) {
                $v->errors()->add('titulares', 'A soma dos percentuais dos titulares ultrapassa 100%.');
            }

            $vinculoIds = collect($titulares)->pluck('vinculo_id');
            if ($vinculoIds->count() !== $vinculoIds->unique()->count()) {
                $v->errors()->add('titulares', 'Há proprietários duplicados na lista.');
            }

            // Valida que cada dados_bancarios_id pertence ao vinculo_id correspondente.
            foreach ($titulares as $idx => $t) {
                $dbId = $t['dados_bancarios_id'] ?? null;
                $vincId = $t['vinculo_id'] ?? null;
                if ($dbId === null || $vincId === null) {
                    continue;
                }

                $valida = DadosBancarios::withoutGlobalScopes()
                    ->where('id', $dbId)
                    ->where('vinculo_id', $vincId)
                    ->exists();

                if (! $valida) {
                    $v->errors()->add("titulares.{$idx}.dados_bancarios_id", 'Conta bancária inválida para este proprietário.');
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
            'cep.required' => 'O campo CEP é obrigatório.',
            'logradouro.required' => 'O campo logradouro é obrigatório.',
            'numero.required' => 'O campo número é obrigatório.',
            'bairro.required' => 'O campo bairro é obrigatório.',
            'cidade.required' => 'O campo cidade é obrigatório.',
            'uf.required' => 'O campo UF é obrigatório.',
            'uf.size' => 'O campo UF deve ter 2 caracteres.',
            'inscricao_iptu.max' => 'A inscrição do IPTU não pode ter mais de 50 caracteres.',
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
            'condominio.entidade_externa_id.exists' => 'A entidade externa selecionada é inválida.',
            'condominio.dia_vencimento.between' => 'O dia de vencimento do condomínio deve estar entre 1 e 31.',
            'condominio.valor.min' => 'O valor do condomínio deve ser maior ou igual a zero.',
            'condominio.acesso_descricao.max' => 'A descrição de acesso não pode ter mais de 5000 caracteres.',
        ];
    }
}
