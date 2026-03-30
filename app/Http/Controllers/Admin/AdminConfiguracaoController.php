<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoCobrancaKimobe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfiguracaoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/configuracoes/index', [
            'config' => ConfiguracaoCobrancaKimobe::getConfig(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'dias_aviso_antes_vencimento' => ['required', 'integer', 'min:0', 'max:30'],
            'aviso_no_dia_vencimento' => ['required', 'boolean'],
            'dias_graca_apos_vencimento' => ['required', 'integer', 'min:0', 'max:60'],
            'dias_aviso_bloqueio' => ['required', 'integer', 'min:0'],
            'aviso_ao_bloquear' => ['required', 'boolean'],
            'dia_vencimento_fatura' => ['required', 'integer', 'between:1,28'],
        ]);

        // Validar que dias_aviso_bloqueio < dias_graca
        if ($request->dias_aviso_bloqueio >= $request->dias_graca_apos_vencimento) {
            return back()->withErrors(['dias_aviso_bloqueio' => 'Deve ser menor que os dias de graça.']);
        }

        ConfiguracaoCobrancaKimobe::getConfig()->update($request->only([
            'dias_aviso_antes_vencimento', 'aviso_no_dia_vencimento',
            'dias_graca_apos_vencimento', 'dias_aviso_bloqueio',
            'aviso_ao_bloquear', 'dia_vencimento_fatura',
        ]));

        return redirect()->route('admin.configuracoes.index')->with('success', 'Configurações atualizadas.');
    }
}
