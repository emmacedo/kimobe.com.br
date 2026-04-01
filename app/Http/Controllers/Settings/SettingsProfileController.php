<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use App\Support\Sanitize;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gerencia as configurações de perfil do usuário e dados da empresa (tenant).
 */
class SettingsProfileController extends Controller
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    /**
     * Página "Meu perfil" — dados pessoais do usuário.
     */
    public function perfil(Request $request): Response
    {
        return Inertia::render('settings/perfil', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Atualiza dados pessoais do usuário (nome, email, telefone).
     */
    public function atualizarPerfil(Request $request): RedirectResponse
    {
        // Sanitiza telefone com máscara
        if ($request->telefone) {
            $request->merge(['telefone' => Sanitize::telefone($request->telefone)]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O email é obrigatório.',
            'email.unique' => 'Este email já está cadastrado.',
        ]);

        $user = $request->user();
        $user->fill($request->only(['name', 'email', 'telefone']));

        // Se o email mudou, limpa a verificação
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Perfil atualizado com sucesso.');
    }

    /**
     * Página "Minha empresa" — dados do tenant (apenas admin).
     */
    public function empresa(): Response
    {
        $tenant = $this->tenantService->getTenant();

        return Inertia::render('settings/empresa', [
            'tenant' => [
                'nome' => $tenant->nome,
                'tipo' => $tenant->tipo,
                'documento' => $tenant->documento,
                'cep' => $tenant->cep,
                'logradouro' => $tenant->logradouro,
                'numero' => $tenant->numero,
                'complemento' => $tenant->complemento,
                'bairro' => $tenant->bairro,
                'cidade' => $tenant->cidade,
                'uf' => $tenant->uf,
                'email_contato' => $tenant->email_contato,
                'telefone_comercial' => $tenant->telefone_comercial,
                'whatsapp' => $tenant->whatsapp,
                'site' => $tenant->site,
            ],
        ]);
    }

    /**
     * Atualiza dados básicos da empresa.
     */
    public function atualizarEmpresa(Request $request): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ], [
            'nome.required' => 'O nome da empresa é obrigatório.',
        ]);

        $tenant = $this->tenantService->getTenant();
        $tenant->update($request->only(['nome']));

        return back()->with('success', 'Dados da empresa atualizados.');
    }

    /**
     * Atualiza endereço da empresa.
     */
    public function atualizarEnderecoEmpresa(Request $request): RedirectResponse
    {
        if ($request->cep) {
            $request->merge(['cep' => Sanitize::cep($request->cep)]);
        }

        $request->validate([
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
        ]);

        $tenant = $this->tenantService->getTenant();
        $tenant->update($request->only(['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf']));

        return back()->with('success', 'Endereço da empresa atualizado.');
    }

    /**
     * Atualiza contato da empresa.
     */
    public function atualizarContatoEmpresa(Request $request): RedirectResponse
    {
        // Sanitiza telefones com máscara
        if ($request->telefone_comercial) {
            $request->merge(['telefone_comercial' => Sanitize::telefone($request->telefone_comercial)]);
        }
        if ($request->whatsapp) {
            $request->merge(['whatsapp' => Sanitize::telefone($request->whatsapp)]);
        }

        $request->validate([
            'email_contato' => ['nullable', 'email', 'max:255'],
            'telefone_comercial' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'site' => ['nullable', 'url', 'max:255'],
        ]);

        $tenant = $this->tenantService->getTenant();
        $tenant->update($request->only(['email_contato', 'telefone_comercial', 'whatsapp', 'site']));

        return back()->with('success', 'Contato da empresa atualizado.');
    }
}
