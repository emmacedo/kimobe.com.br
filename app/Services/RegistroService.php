<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Kicol\FullFlow\Models\FullFlowPlan;

class RegistroService
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    /**
     * Cria user + tenant + vínculos em uma transação.
     * Autentica e seleciona o tenant automaticamente.
     */
    public function registrar(array $dados): array
    {
        return DB::transaction(function () use ($dados) {
            // 1. Criar user
            $user = User::create([
                'name' => $dados['nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'] ?? null,
                'cpf' => $dados['cpf'] ?? null,
                'password' => Hash::make($dados['senha']),
            ]);

            // 2. Criar tenant
            $documento = $dados['tipo_tenant'] === 'imobiliaria'
                ? $dados['cnpj']
                : $dados['cpf'];

            $tipoDocumento = ($dados['tipo_tenant'] === 'imobiliaria' || ! empty($dados['cnpj'])) ? 'cnpj' : 'cpf';

            $tenant = Tenant::create([
                'nome' => $dados['nome_tenant'],
                'legal_name' => $dados['legal_name'] ?? null,
                'tipo' => $dados['tipo_tenant'],
                'tipo_documento' => $tipoDocumento,
                'documento' => $documento,
                'state_registration' => $dados['state_registration'] ?? null,
                'municipal_registration' => $dados['municipal_registration'] ?? null,
                'status' => 'ativo',
                'auto_upgrade_enabled' => true,
            ]);

            // 3. Criar vínculo admin
            Vinculo::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'papel' => 'admin',
                'status' => 'ativo',
            ]);

            // Se proprietário direto, criar também vínculo proprietario
            if ($dados['tipo_tenant'] === 'proprietario_direto') {
                Vinculo::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'papel' => 'proprietario',
                    'status' => 'ativo',
                ]);
            }

            // 4. Login automático
            Auth::login($user);

            // 5. Setar tenant na sessão
            $this->tenantService->setTenant($tenant);

            // 6. Enviar emails de notificação
            $emailService = app(EmailNotificationService::class);
            $planoCode = $dados['plan_code'] ?? null;
            $planoNome = $planoCode
                ? (FullFlowPlan::where('code', $planoCode)->value('name') ?? $planoCode)
                : 'A definir';

            // Boas-vindas ao novo assinante
            $emailService->enviar('kimobe.boas_vindas', $user->email, $user->name, [
                'nome' => $user->name, 'email' => $user->email,
                'nome_empresa' => $tenant->nome, 'plano_nome' => $planoNome,
                'link_sistema' => url('/dashboard'),
            ], $tenant->id);

            // Notificar super admin
            $adminEmail = AdminUser::first();
            if ($adminEmail) {
                $emailService->enviar('kimobe.novo_cadastro_admin', $adminEmail->email, $adminEmail->nome, [
                    'nome' => $user->name, 'email' => $user->email,
                    'nome_empresa' => $tenant->nome, 'tipo_tenant' => $tenant->tipo,
                    'plano_nome' => $planoNome, 'documento' => $tenant->documento,
                ]);
            }

            return ['user' => $user, 'tenant' => $tenant];
        });
    }
}
