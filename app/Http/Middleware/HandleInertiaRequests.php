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
            'app_url' => rtrim(config('app.url'), '/'),
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
            'manage_faturas' => $isAdmin,
            'manage_repasses' => $isAdmin,
            'upload_comprovantes' => $isAdmin || $isInquilino,
            'view_repasses' => $isAdmin || $isProprietario,
            'manage_dados_bancarios' => $isAdmin || $isProprietario,
        ];
    }

    /**
     * Alerta de status da assinatura para banner in-app.
     *
     * Substitui o legado de FaturaKimobe pelo status da FullFlowSubscription:
     *   - trial expirando        → nivel 1 (info)
     *   - past_due (boletos)     → nivel 2 (atenção)
     *   - suspensa               → nivel 3 (urgência, prestes a bloquear)
     *   - cancelamento_agendado  → nivel 1 (info)
     */
    private function getAlertaFatura(Request $request): ?array
    {
        $tenantService = app(TenantService::class);
        $tenant = $tenantService->getTenant();

        if (! $tenant || $tenant->is_exempt_from_subscription) {
            return null;
        }

        $sub = $tenant->currentFullFlowSubscription();
        if (! $sub) {
            return null;
        }

        return match ($sub->status) {
            'past_due' => [
                'nivel' => 2,
                'mensagem' => 'Sua assinatura está com pagamento em atraso. Regularize para manter o acesso.',
            ],
            'suspensa' => [
                'nivel' => 3,
                'mensagem' => 'ATENÇÃO: Sua assinatura está suspensa. Regularize a cobrança para reativar o acesso.',
            ],
            'cancelamento_agendado' => [
                'nivel' => 1,
                'mensagem' => 'Cancelamento agendado'.($sub->current_period_end ? ' — acesso disponível até '.$sub->current_period_end->format('d/m/Y') : '').'.',
            ],
            default => null,
        };
    }
}
