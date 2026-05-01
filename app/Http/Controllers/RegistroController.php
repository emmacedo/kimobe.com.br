<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistroService;
use App\Support\Sanitize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Kicol\FullFlow\Models\FullFlowPlan;

class RegistroController extends Controller
{
    public function __construct(
        protected RegistroService $registroService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $planos = FullFlowPlan::with('modules')
            ->orderBy('sort_order')
            ->orderBy('amount')
            ->get();

        return Inertia::render('public/registro', [
            'planos' => $planos,
            'plano_selecionado' => $request->input('plano'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Sanitiza campos com máscara antes de validar
        $request->merge(array_filter([
            'telefone' => $request->telefone ? Sanitize::telefone($request->telefone) : null,
            'cpf' => $request->cpf ? Sanitize::cpf($request->cpf) : null,
            'cnpj' => $request->cnpj ? Sanitize::cnpj($request->cnpj) : null,
        ], fn ($v) => $v !== null));

        $request->validate([
            'plan_code' => ['required', 'string', 'exists:fullflow_plans,code'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cpf' => ['required', 'string', 'max:14'],
            'senha' => ['required', 'string', 'min:8', 'confirmed'],
            'tipo_tenant' => ['required', 'in:imobiliaria,proprietario_direto'],
            'nome_tenant' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'required_if:tipo_tenant,imobiliaria', 'string', 'max:18'],
            'legal_name' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->tipo_tenant === 'imobiliaria')],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'termos' => ['required', 'accepted'],
            'accept_auto_upgrade' => ['accepted'],
        ], [
            'email.unique' => 'Este email já está cadastrado.',
            'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'senha.confirmed' => 'A confirmação de senha não confere.',
            'nome.required' => 'Informe seu nome completo.',
            'cpf.required' => 'Informe seu CPF.',
            'nome_tenant.required' => 'Informe o nome da empresa.',
            'cnpj.required_if' => 'Informe o CNPJ da imobiliária.',
            'legal_name.required' => 'Informe a razão social.',
            'termos.accepted' => 'Você precisa aceitar os termos de uso.',
            'accept_auto_upgrade.accepted' => 'É necessário aceitar o termo de upgrade automático para contratar o plano.',
        ]);

        // Verificar unicidade do documento do tenant
        if ($request->tipo_tenant === 'imobiliaria') {
            $docExists = Tenant::withoutGlobalScopes()->where('documento', $request->cnpj)->exists();
            if ($docExists) {
                return back()->withErrors(['cnpj' => 'Este CNPJ já está cadastrado.']);
            }
        } else {
            $docExists = Tenant::withoutGlobalScopes()->where('documento', $request->cpf)->exists();
            if ($docExists) {
                return back()->withErrors(['cpf' => 'Este CPF já está cadastrado como assinante.']);
            }
        }

        $this->registroService->registrar($request->all());

        return redirect()->route('dashboard')
            ->with('success', 'Bem-vindo ao Kimobe! Sua conta foi criada com sucesso.');
    }

    public function verificarEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $disponivel = ! User::where('email', $request->email)->exists();

        return response()->json(['disponivel' => $disponivel]);
    }
}
