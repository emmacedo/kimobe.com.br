<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenantData = $this->getTenantData($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'current_tenant' => $tenantData['tenant'],
            'current_papeis' => $tenantData['papeis'],
            'has_multiple_tenants' => $tenantData['has_multiple'],
            'can' => $this->getPermissions($tenantData['papeis']),
            'alerta_fatura' => fn () => $this->getAlertaFatura($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Retorna os dados do tenant ativo para compartilhar com o frontend.
     *
     * @return array{tenant: array|null, papeis: array, has_multiple: bool}
     */
    private function getTenantData(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return ['tenant' => null, 'papeis' => [], 'has_multiple' => false];
        }

        $tenantService = app(TenantService::class);
        $tenant = $tenantService->getTenant();

        if (! $tenant) {
            return ['tenant' => null, 'papeis' => [], 'has_multiple' => false];
        }

        // Papéis do usuário no tenant ativo
        $papeis = $tenantService->getUserPapeis($user, $tenant);

        // Verifica se o usuário tem acesso a mais de um tenant
        $tenantCount = $user->vinculos()
            ->where('status', 'ativo')
            ->distinct('tenant_id')
            ->count('tenant_id');

        return [
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'tipo' => $tenant->tipo,
            ],
            'papeis' => $papeis,
            'has_multiple' => $tenantCount > 1,
        ];
    }

    /**
     * Calcula permissões booleanas baseadas nos papéis do usuário.
     *
     * @return array<string, bool>
     */
    private function getPermissions(array $papeis): array
    {
        $isAdmin = in_array('admin', $papeis);
        $isProprietario = in_array('proprietario', $papeis);
        $isInquilino = in_array('inquilino', $papeis);

        return [
            'manage_imoveis' => $isAdmin,
            'manage_contratos' => $isAdmin,
            'manage_cobrancas' => $isAdmin,
            'manage_repasses' => $isAdmin,
            'upload_comprovantes' => $isAdmin || $isInquilino,
            'view_repasses' => $isAdmin || $isProprietario,
            'manage_dados_bancarios' => $isAdmin || $isProprietario,
        ];
    }

    /**
     * Retorna alerta de fatura para banner in-app.
     */
    private function getAlertaFatura(Request $request): ?array
    {
        $tenantService = app(TenantService::class);
        $tenant = $tenantService->getTenant();

        if (! $tenant || $tenant->cortesia) {
            return null;
        }

        $config = \App\Models\ConfiguracaoCobrancaKimobe::first();
        if (! $config) {
            return null;
        }

        // Buscar fatura atrasada primeiro
        $faturaAtrasada = $tenant->faturasKimobe()
            ->where('status', 'atrasado')
            ->orderBy('data_vencimento')
            ->first();

        if ($faturaAtrasada) {
            $diasAtraso = (int) now()->diffInDays($faturaAtrasada->data_vencimento);
            $diasParaBloqueio = max(0, $config->dias_graca_apos_vencimento - $diasAtraso);
            $nivel = $diasParaBloqueio <= 2 ? 3 : 2;

            return [
                'nivel' => $nivel,
                'referencia' => $faturaAtrasada->referencia,
                'valor' => (float) $faturaAtrasada->valor,
                'dias_atraso' => $diasAtraso,
                'dias_para_bloqueio' => $diasParaBloqueio,
                'mensagem' => $nivel === 3
                    ? "ATENÇÃO: Sua fatura está vencida há {$diasAtraso} dias. O acesso será bloqueado em {$diasParaBloqueio} dia(s)."
                    : "Sua fatura de {$faturaAtrasada->referencia} está vencida há {$diasAtraso} dias. Regularize para evitar o bloqueio.",
            ];
        }

        // Fatura pendente próxima do vencimento
        $faturaPendente = $tenant->faturasKimobe()
            ->where('status', 'pendente')
            ->where('data_vencimento', '<=', now()->addDays($config->dias_aviso_antes_vencimento))
            ->orderBy('data_vencimento')
            ->first();

        if ($faturaPendente) {
            $diasRestantes = (int) now()->diffInDays($faturaPendente->data_vencimento, false);

            return [
                'nivel' => 1,
                'referencia' => $faturaPendente->referencia,
                'valor' => (float) $faturaPendente->valor,
                'dias_atraso' => 0,
                'dias_para_bloqueio' => 0,
                'mensagem' => "Sua fatura de {$faturaPendente->referencia} no valor de R$ " . number_format($faturaPendente->valor, 2, ',', '.') . " vence em {$diasRestantes} dia(s).",
            ];
        }

        return null;
    }
}
