<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanoController extends Controller
{
    public function index(): Response
    {
        $planos = Plano::ordenado()
            ->withCount('tenants')
            ->get();

        return Inertia::render('admin/planos/index', [
            'planos' => $planos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:100', 'unique:planos,nome'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'limite_imoveis' => ['required', 'integer', 'min:0'],
            'valor_mensal' => ['required', 'numeric', 'min:0.01'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);

        Plano::create($request->only(['nome', 'descricao', 'limite_imoveis', 'valor_mensal', 'ordem']));

        return redirect()->route('admin.planos.index')->with('success', 'Plano criado com sucesso.');
    }

    public function update(Request $request, Plano $plano): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:100', 'unique:planos,nome,' . $plano->id],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'limite_imoveis' => ['required', 'integer', 'min:0'],
            'valor_mensal' => ['required', 'numeric', 'min:0.01'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);

        $plano->update($request->only(['nome', 'descricao', 'limite_imoveis', 'valor_mensal', 'ordem']));

        return redirect()->route('admin.planos.index')->with('success', 'Plano atualizado.');
    }

    public function toggleStatus(Plano $plano): RedirectResponse
    {
        $plano->update(['status' => $plano->status === 'ativo' ? 'inativo' : 'ativo']);
        $msg = $plano->status === 'ativo' ? 'Plano reativado.' : 'Plano desativado.';

        return redirect()->route('admin.planos.index')->with('success', $msg);
    }

    public function destroy(Plano $plano): RedirectResponse
    {
        if ($plano->tenants()->count() > 0) {
            return back()->withErrors(['plano' => 'Não é possível excluir um plano com assinantes vinculados.']);
        }

        $plano->delete();

        return redirect()->route('admin.planos.index')->with('success', 'Plano excluído.');
    }
}
