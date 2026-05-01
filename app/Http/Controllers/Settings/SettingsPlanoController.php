<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FullFlowSubscription;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Kicol\FullFlow\Exceptions\FullFlowException;
use Kicol\FullFlow\Exceptions\SubscriptionAlreadyExistsException;
use Kicol\FullFlow\Facades\FullFlow;
use Kicol\FullFlow\Models\FullFlowPlan;

/**
 * Gerencia visualização e alteração do plano do assinante (Tenant)
 * via catálogo central FullFlow.
 */
class SettingsPlanoController extends Controller
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    /**
     * Página "Meu plano" — plano atual, uso e faturas (cobranças via FullFlow).
     */
    public function index(): Response
    {
        $tenant = $this->tenantService->getTenant();
        $sub = $tenant->currentFullFlowSubscription();
        $planoAtual = $tenant->currentFullFlowPlan();

        $charges = [];
        if ($sub) {
            try {
                $response = FullFlow::listCharges($sub->fullflow_id, 'todas');
                $charges = $response['cobrancas'] ?? [];
            } catch (\Throwable $e) {
                report($e);
                $charges = [];
            }
        }

        $planos = FullFlowPlan::with('modules')->orderBy('sort_order')->get();

        return Inertia::render('settings/plano', [
            'plano_atual' => $planoAtual,
            'subscription' => $sub,
            'cortesia' => $tenant->estaIsento(),
            'imoveis_count' => $tenant->totalImoveis(),
            'faturas' => $charges,
            'planos' => $planos,
        ]);
    }

    /**
     * Contrata o primeiro plano (cria FullFlowSubscription via API FullFlow).
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:fullflow_plans,code'],
            'accept_auto_upgrade' => ['accepted'],
        ], [
            'accept_auto_upgrade.accepted' => 'É necessário aceitar o termo de upgrade automático para contratar o plano.',
        ]);

        $tenant = $this->tenantService->getTenant();
        $plan = FullFlowPlan::where('code', $request->input('plan_code'))->firstOrFail();

        if (FullFlowSubscription::where('tenant_id', $tenant->id)->exists()) {
            return back()->with('error', 'Você já tem uma assinatura ativa.');
        }

        $reference = "kimobe_tenant_{$tenant->id}";
        $documento = preg_replace('/\D/', '', $tenant->documento ?? '');
        $isCnpj = $tenant->tipo_documento === 'cnpj';

        try {
            $result = FullFlow::createSubscription([
                'referencia_externa' => $reference,
                'cliente' => [
                    'tipo' => $isCnpj ? 'pj' : 'pf',
                    'documento' => $documento,
                    'nome' => $isCnpj ? ($tenant->legal_name ?: $tenant->nome) : $tenant->nome,
                    'email' => $tenant->email_contato ?? $tenant->getAdminPrincipal()?->email,
                    'telefone' => $tenant->telefone_comercial ?? $tenant->whatsapp,
                ],
                'assinatura' => [
                    'plan_code' => $plan->code,
                    'dia_vencimento' => 10,
                ],
                'fiscal' => [
                    'emitir_nf' => false,
                    'descricao_servico' => "Assinatura Kimobe — Plano {$plan->name}",
                ],
            ]);
        } catch (SubscriptionAlreadyExistsException) {
            return back()->with('error', 'Já existe assinatura no FullFlow para este tenant.');
        } catch (FullFlowException $e) {
            return back()->with('error', 'Erro ao contratar: '.$e->getMessage());
        }

        FullFlowSubscription::create([
            'tenant_id' => $tenant->id,
            'fullflow_id' => $result['assinatura_id'],
            'reference' => $reference,
            'plan_code' => $plan->code,
            'status' => $result['status'],
            'trial_until' => $result['trial_ate'] ?? null,
            'amount' => $plan->amount,
            'billing_cycle' => $plan->billing_cycle,
        ]);

        $tenant->update(['auto_upgrade_enabled' => true]);

        return redirect()->route('settings.plano')
            ->with('success', "Assinatura contratada! Trial até {$result['trial_ate']}.");
    }

    /**
     * Mudança de plano (upgrade/downgrade) via API FullFlow.
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:fullflow_plans,code'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $this->tenantService->getTenant();
        $sub = $tenant->currentFullFlowSubscription();
        if (! $sub) {
            return back()->with('error', 'Você não tem assinatura para alterar.');
        }

        $newPlan = FullFlowPlan::where('code', $request->input('plan_code'))->firstOrFail();
        if ($sub->plan_code === $newPlan->code) {
            return back()->with('error', 'Esse já é o seu plano atual.');
        }

        $isUpgrade = (float) $newPlan->amount > (float) $sub->amount;

        try {
            $result = $isUpgrade
                ? FullFlow::upgradeSubscription($sub->fullflow_id, $newPlan->code, $request->input('motivo'))
                : FullFlow::downgradeSubscription($sub->fullflow_id, $newPlan->code, $request->input('motivo'));
        } catch (FullFlowException $e) {
            return back()->with('error', 'Não foi possível mudar o plano: '.$e->getMessage());
        }

        $sub->update([
            'plan_code' => $result['plan_code'] ?? ($result['plan_code_atual'] ?? $newPlan->code),
            'amount' => $result['amount'] ?? $newPlan->amount,
            'billing_cycle' => $newPlan->billing_cycle,
            'last_synced_at' => now(),
        ]);

        return redirect()->route('settings.plano')->with('success', "Plano alterado para {$newPlan->name}.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
            'confirmacao' => ['accepted'],
        ]);

        $tenant = $this->tenantService->getTenant();
        $sub = $tenant->currentFullFlowSubscription();
        if (! $sub) {
            return back()->with('error', 'Você não tem assinatura para cancelar.');
        }

        try {
            $result = FullFlow::cancelSubscription($sub->fullflow_id, $request->input('motivo'));
        } catch (FullFlowException $e) {
            return back()->with('error', 'Erro ao cancelar: '.$e->getMessage());
        }

        $sub->update(['status' => $result['status'], 'last_synced_at' => now()]);

        return redirect()->route('settings.plano')->with('success', 'Cancelamento processado.');
    }
}
