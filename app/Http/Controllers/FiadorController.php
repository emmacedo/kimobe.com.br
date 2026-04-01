<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fiador;
use App\Services\TenantService;
use App\Support\Sanitize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiadorController extends Controller
{
    /**
     * Regras de validação compartilhadas.
     */
    private function validationRules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:14'],
            'rg' => ['nullable', 'string', 'max:20'],
            'telefone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'profissao' => ['nullable', 'string', 'max:255'],
            'estado_civil' => ['nullable', 'string', 'max:50'],
            'cep' => ['required', 'string', 'max:9'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'nome.required' => 'O nome do fiador é obrigatório.',
            'cpf.required' => 'O CPF do fiador é obrigatório.',
            'telefone.required' => 'O telefone do fiador é obrigatório.',
            'cep.required' => 'O CEP é obrigatório.',
            'logradouro.required' => 'O logradouro é obrigatório.',
            'numero.required' => 'O número é obrigatório.',
            'bairro.required' => 'O bairro é obrigatório.',
            'cidade.required' => 'A cidade é obrigatória.',
            'uf.required' => 'O UF é obrigatório.',
        ];
    }

    /**
     * Cadastra um fiador para o contrato.
     * Máximo de 2 fiadores por contrato.
     */
    public function store(Request $request, Contrato $contrato): JsonResponse
    {
        if ($contrato->tipo_garantia !== 'fiador') {
            return response()->json(['message' => 'Este contrato não utiliza fiador como garantia.'], 422);
        }

        if ($contrato->fiadores()->count() >= 2) {
            return response()->json(['message' => 'Máximo de 2 fiadores por contrato.'], 422);
        }

        // Sanitiza campos com máscara antes de validar
        $request->merge(array_filter([
            'cpf' => $request->cpf ? Sanitize::cpf($request->cpf) : null,
            'telefone' => $request->telefone ? Sanitize::telefone($request->telefone) : null,
            'cep' => $request->cep ? Sanitize::cep($request->cep) : null,
        ], fn ($v) => $v !== null));

        $request->validate($this->validationRules(), $this->validationMessages());

        $tenantId = app(TenantService::class)->getTenantId();

        $fiador = Fiador::create([
            'tenant_id' => $tenantId,
            'contrato_id' => $contrato->id,
            ...$request->only([
                'nome', 'cpf', 'rg', 'telefone', 'email', 'profissao', 'estado_civil',
                'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
            ]),
        ]);

        return response()->json($fiador, 201);
    }

    /**
     * Atualiza os dados de um fiador.
     */
    public function update(Request $request, Contrato $contrato, Fiador $fiador): JsonResponse
    {
        // Sanitiza campos com máscara antes de validar
        $request->merge(array_filter([
            'cpf' => $request->cpf ? Sanitize::cpf($request->cpf) : null,
            'telefone' => $request->telefone ? Sanitize::telefone($request->telefone) : null,
            'cep' => $request->cep ? Sanitize::cep($request->cep) : null,
        ], fn ($v) => $v !== null));

        $request->validate($this->validationRules(), $this->validationMessages());

        $fiador->update($request->only([
            'nome', 'cpf', 'rg', 'telefone', 'email', 'profissao', 'estado_civil',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
        ]));

        return response()->json($fiador);
    }

    /**
     * Remove um fiador do contrato.
     */
    public function destroy(Contrato $contrato, Fiador $fiador): JsonResponse
    {
        $fiador->delete();

        return response()->json(['message' => 'Fiador removido.']);
    }
}
