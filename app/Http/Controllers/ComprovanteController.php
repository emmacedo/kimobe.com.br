<?php

namespace App\Http\Controllers;

use App\Models\Comprovante;
use App\Models\Fatura;
use App\Models\ItemCobranca;
use App\Models\Repasse;
use App\Services\NotificacaoAdminService;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller único e polimórfico para gerenciar comprovantes anexados a
 * faturas, repasses ou itens de cobrança (Seção 2.11 do escopo).
 */
class ComprovanteController extends Controller
{
    use ScopesPorPapel;

    /**
     * Upload de comprovante para uma Fatura.
     */
    public function storeForFatura(Request $request, Fatura $fatura): JsonResponse
    {
        // Inquilino só pode enviar comprovante para fatura dele com status pendente/atrasado.
        if ($this->isInquilino() && ! $this->isAdmin()) {
            abort_unless($this->podeVerFatura($fatura), 403);
            abort_unless(in_array($fatura->status, ['pendente', 'atrasado']), 422,
                'Só é possível enviar comprovante para faturas pendentes ou atrasadas.');
        }

        $comprovante = $this->salvar($request, $fatura, papel: $this->papelAtual(), prefixo: 'faturas');

        // Notifica admin se foi inquilino que enviou.
        if ($this->isInquilino() && ! $this->isAdmin()) {
            try {
                app(NotificacaoAdminService::class)->notificarComprovanteEnviado($fatura, auth()->user()->name);
            } catch (\Throwable $e) { /* silencioso */
            }
        }

        return response()->json($comprovante, 201);
    }

    /**
     * Upload de comprovante para um Repasse (admin transferindo ao proprietário).
     */
    public function storeForRepasse(Request $request, Repasse $repasse): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $comprovante = $this->salvar($request, $repasse, papel: 'admin', prefixo: 'repasses');

        return response()->json($comprovante, 201);
    }

    /**
     * Upload de comprovante para um ItemCobranca (ex: admin pagando síndico
     * em itens com `recebedor=administradora` intermediada).
     */
    public function storeForItemCobranca(Request $request, ItemCobranca $itemCobranca): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $comprovante = $this->salvar($request, $itemCobranca, papel: 'admin', prefixo: 'itens');

        return response()->json($comprovante, 201);
    }

    /**
     * Atualiza observações ou tipo de um comprovante.
     */
    public function update(Request $request, Comprovante $comprovante): JsonResponse
    {
        $this->autorizarEdicao($comprovante);

        $request->validate([
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'tipo' => ['nullable', 'in:pagamento_pix,pagamento_boleto,transferencia,recibo,nota_fiscal,outro'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'data_referencia' => ['nullable', 'date'],
        ]);

        $comprovante->update($request->only(['observacoes', 'tipo', 'valor', 'data_referencia']));

        return response()->json($comprovante);
    }

    /**
     * Remove um comprovante (e o arquivo do storage).
     */
    public function destroy(Comprovante $comprovante): JsonResponse
    {
        $this->autorizarEdicao($comprovante);

        if ($comprovante->arquivo) {
            Storage::disk('public')->delete($comprovante->arquivo);
        }
        $comprovante->delete();

        return response()->json(['message' => 'Comprovante removido.']);
    }

    // =========================================================================
    // Internos
    // =========================================================================

    private function salvar(Request $request, Model $owner, string $papel, string $prefixo): Comprovante
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'tipo' => ['nullable', 'in:pagamento_pix,pagamento_boleto,transferencia,recibo,nota_fiscal,outro'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'data_referencia' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'arquivo.max' => 'O arquivo não pode ter mais de 10MB.',
            'arquivo.mimes' => 'Formato aceito: PDF, JPG, PNG ou WebP.',
        ]);

        $arquivo = $request->file('arquivo');
        $nomeUnico = Str::uuid().'.'.$arquivo->getClientOriginalExtension();
        $caminho = $arquivo->storeAs("comprovantes/{$prefixo}/{$owner->id}", $nomeUnico, 'public');

        return Comprovante::create([
            'tenant_id' => app(TenantService::class)->getTenantId(),
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'tipo' => $request->input('tipo', 'outro'),
            'arquivo' => $caminho,
            'nome_original' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'tamanho_bytes' => $arquivo->getSize(),
            'valor' => $request->input('valor'),
            'data_referencia' => $request->input('data_referencia'),
            'observacoes' => $request->input('observacoes'),
            'enviado_por_user_id' => auth()->id(),
            'enviado_por_papel' => $papel,
        ]);
    }

    /**
     * Autoriza edição/remoção:
     * - admin pode tudo;
     * - quem enviou pode remover/editar o próprio.
     */
    private function autorizarEdicao(Comprovante $comprovante): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if ($comprovante->enviado_por_user_id === auth()->id()) {
            return;
        }

        abort(403, 'Você só pode alterar comprovantes que você enviou.');
    }

    private function papelAtual(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }
        if ($this->isProprietario()) {
            return 'proprietario';
        }

        return 'inquilino';
    }
}
