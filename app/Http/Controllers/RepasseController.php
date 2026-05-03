<?php

namespace App\Http\Controllers;

use App\Models\Repasse;
use App\Services\NotificacaoAdminService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RepasseController extends Controller
{
    use ScopesPorPapel;

    public function index(Request $request): Response
    {
        $mesAno = $request->input('mes', now()->format('Y-m'));

        $query = Repasse::query()
            ->with([
                'titularidade.vinculo.user',
                'titularidade.dadosBancarios',
                'fatura.contrato.imovel',
                'fatura.contrato',
            ]);

        // Scoping por papel
        $this->scopeRepassesDoUsuario($query);

        // Filtro por período: repasses com data_prevista no mês selecionado
        $partes = explode('-', $mesAno);
        $ano = $partes[0] ?? now()->year;
        $mes = $partes[1] ?? now()->month;
        $query->whereYear('data_prevista', $ano)->whereMonth('data_prevista', $mes);

        if ($busca = $request->input('busca')) {
            $query->where(function ($q) use ($busca) {
                $q->whereHas('titularidade.vinculo.user', fn ($qu) => $qu->where('name', 'like', "%{$busca}%"))
                    ->orWhereHas('fatura.contrato.imovel', fn ($qi) => $qi
                        ->where('logradouro', 'like', "%{$busca}%")
                        ->orWhere('complemento', 'like', "%{$busca}%")
                    );
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $repasses = $query->orderBy('data_prevista', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Resumo (com scoping)
        $pendentes = Repasse::where('status', 'pendente');
        $this->scopeRepassesDoUsuario($pendentes);
        $realizadosMes = Repasse::where('status', 'realizado')
            ->whereYear('data_realizada', $ano)
            ->whereMonth('data_realizada', $mes);
        $this->scopeRepassesDoUsuario($realizadosMes);

        $resumo = [
            'pendentes_count' => (clone $pendentes)->count(),
            'pendentes_valor' => (clone $pendentes)->sum('valor_liquido'),
            'realizados_count' => (clone $realizadosMes)->count(),
            'realizados_valor' => (clone $realizadosMes)->sum('valor_liquido'),
            'total_liquido' => (clone $realizadosMes)->sum('valor_liquido'),
        ];

        return Inertia::render('financeiro/repasses/index', [
            'repasses' => $repasses,
            'resumo' => $resumo,
            'filtros' => [
                'mes' => $mesAno,
                'busca' => $request->input('busca', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function show(Repasse $repasse): Response
    {
        $repasse->load(['titularidade.vinculo']);
        abort_unless($this->podeVerRepasse($repasse), 403);

        $repasse->load([
            'titularidade.vinculo.user',
            'titularidade.dadosBancarios',
            'fatura.contrato.imovel.fotoPrincipal',
            'fatura.contrato',
            'fatura',
            'comprovantes',
        ]);

        return Inertia::render('financeiro/repasses/mostrar', [
            'repasse' => $repasse,
        ]);
    }

    public function confirmar(Request $request, Repasse $repasse): RedirectResponse
    {
        if ($repasse->status !== 'pendente') {
            return back()->withErrors(['repasse' => 'Apenas repasses pendentes podem ser confirmados.']);
        }

        $request->validate([
            'data_realizada' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $repasse->update([
            'status' => 'realizado',
            'data_realizada' => $request->data_realizada,
            'observacoes' => $request->observacoes,
            'realizado_por_user_id' => auth()->id(),
        ]);

        try {
            app(NotificacaoAdminService::class)->notificarRepasseRealizado($repasse);
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar repasse realizado: {$e->getMessage()}");
        }

        return redirect()->route('repasses.show', $repasse)
            ->with('success', "Repasse confirmado — {$repasse->valor_liquido} para o titular.");
    }

    public function confirmarLote(Request $request): JsonResponse
    {
        $request->validate([
            'repasse_ids' => ['required', 'array', 'min:1'],
            'repasse_ids.*' => ['required', 'integer'],
            'data_realizada' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ]);

        $resultado = DB::transaction(function () use ($request) {
            $repasses = Repasse::whereIn('id', $request->repasse_ids)
                ->where('status', 'pendente')
                ->get();

            foreach ($repasses as $repasse) {
                $repasse->update([
                    'status' => 'realizado',
                    'data_realizada' => $request->data_realizada,
                    'observacoes' => $request->observacoes,
                    'realizado_por_user_id' => auth()->id(),
                ]);
                try {
                    app(NotificacaoAdminService::class)->notificarRepasseRealizado($repasse);
                } catch (\Throwable $e) { /* silencioso */
                }
            }

            return [
                'quantidade' => $repasses->count(),
                'valor_total' => $repasses->sum('valor_liquido'),
            ];
        });

        return response()->json($resultado);
    }

    public function cancelar(Repasse $repasse): RedirectResponse
    {
        if ($repasse->status !== 'pendente') {
            return back()->withErrors(['repasse' => 'Apenas repasses pendentes podem ser cancelados.']);
        }

        $repasse->update(['status' => 'cancelado']);

        return redirect()->route('repasses.show', $repasse)
            ->with('success', 'Repasse cancelado.');
    }
}
