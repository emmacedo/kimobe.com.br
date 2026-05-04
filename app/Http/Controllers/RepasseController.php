<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Fatura;
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

    /**
     * Listagem por contrato ativo do mês: para cada contrato, exibe a soma
     * dos repasses persistidos (1 linha por contrato, agregando titularidades)
     * ou um preview calculado a partir das titularidades do imóvel.
     */
    public function index(Request $request): Response
    {
        $mes = $request->input('mes', now()->format('Y-m'));
        $referencia = Fatura::mesParaReferencia($mes);
        $busca = trim((string) $request->input('busca', ''));

        $contratosQuery = Contrato::query()->ativo()->with([
            'imovel.titularidades.vinculo.user',
            'faturas' => fn ($q) => $q->where('referencia', $referencia)
                ->with(['repasses.titularidade.vinculo.user']),
        ]);
        $this->scopeContratosDoUsuario($contratosQuery);

        if ($busca !== '') {
            $contratosQuery->where(function ($q) use ($busca) {
                $q->whereHas('imovel', fn ($qi) => $qi
                    ->where('logradouro', 'like', "%{$busca}%")
                    ->orWhere('complemento', 'like', "%{$busca}%")
                )->orWhereHas('imovel.titularidades.vinculo.user', fn ($qu) => $qu
                    ->where('name', 'like', "%{$busca}%")
                );
            });
        }

        $contratos = $contratosQuery->orderBy('id')->get();
        $linhas = $contratos->map(fn (Contrato $c) => $this->montarLinhaRepasse($c, $mes));

        return Inertia::render('financeiro/repasses/index', [
            'linhas' => $linhas->values(),
            'filtros' => [
                'mes' => $mes,
                'busca' => $busca,
            ],
        ]);
    }

    /**
     * Monta a linha agregada do contrato no mês — soma repasses persistidos
     * (1 por titularidade) ou calcula preview se não há fatura.
     */
    private function montarLinhaRepasse(Contrato $contrato, string $mes): array
    {
        $titular = $contrato->getTitularResponsavel();

        $base = [
            'contrato_id' => $contrato->id,
            'imovel' => $contrato->getEnderecoCurto(),
            'titular' => $titular?->vinculo?->user?->name ?? '—',
            'mes_referencia' => $mes,
        ];

        $imovel = $contrato->imovel;

        $fatura = $contrato->faturas->first();
        $repasses = $fatura?->repasses ?? collect();

        if ($repasses->isNotEmpty()) {
            // Status agregado: se todos cancelados → cancelado; se algum pendente → pendente; senão realizado.
            $status = $repasses->contains('status', 'pendente')
                ? 'pendente'
                : ($repasses->every(fn ($r) => $r->status === 'cancelado') ? 'cancelado' : 'realizado');

            return $base + [
                'fatura_id' => $fatura->id,
                'valor_liquido' => (float) $repasses->sum('valor_liquido'),
                'status' => $status,
                'data_prevista' => $repasses->first()?->data_prevista?->toDateString(),
                'is_preview' => false,
                'qtd_titularidades' => $repasses->count(),
            ];
        }

        $valorPreview = $contrato->calcularRepasseLiquidoTotal();

        return $base + [
            'fatura_id' => null,
            'valor_liquido' => $valorPreview,
            'status' => 'preview',
            'data_prevista' => null,
            'is_preview' => true,
            'qtd_titularidades' => $imovel?->titularidades->count() ?? 0,
        ];
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
