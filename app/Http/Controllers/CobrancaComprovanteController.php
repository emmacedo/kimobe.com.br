<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\CobrancaComprovante;
use App\Services\TenantService;
use App\Traits\ScopesPorPapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CobrancaComprovanteController extends Controller
{
    use ScopesPorPapel;

    public function store(Request $request, Cobranca $cobranca): JsonResponse
    {
        // Inquilino: só pode enviar se a cobrança é dele e está pendente/atrasada
        if ($this->isInquilino() && ! $this->isAdmin()) {
            $cobranca->load(['contrato.inquilino']);
            abort_unless($this->podeVerCobranca($cobranca), 403);
            abort_unless(in_array($cobranca->status, ['pendente', 'atrasado']), 422, 'Só é possível enviar comprovante para cobranças pendentes ou atrasadas.');
        }

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'arquivo.max' => 'O arquivo não pode ter mais de 10MB.',
            'arquivo.mimes' => 'Formato aceito: PDF, JPG, PNG ou WebP.',
        ]);

        $arquivo = $request->file('arquivo');
        $nomeUnico = Str::uuid() . '.' . $arquivo->getClientOriginalExtension();
        $caminho = $arquivo->storeAs("comprovantes/cobrancas/{$cobranca->id}", $nomeUnico, 'public');

        $comprovante = CobrancaComprovante::create([
            'tenant_id' => app(TenantService::class)->getTenantId(),
            'cobranca_id' => $cobranca->id,
            'caminho' => $caminho,
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'tamanho_bytes' => $arquivo->getSize(),
            'uploaded_by_user_id' => auth()->id(),
        ]);

        // Se inquilino enviou, notificar admin
        if ($this->isInquilino() && ! $this->isAdmin()) {
            try {
                app(\App\Services\NotificacaoAdminService::class)->notificarComprovanteEnviado($cobranca, auth()->user()->name);
            } catch (\Throwable $e) { /* silencioso */ }
        }

        return response()->json($comprovante->fresh(), 201);
    }

    public function update(Request $request, Cobranca $cobranca, CobrancaComprovante $comprovante): JsonResponse
    {
        $request->validate(['observacoes' => ['nullable', 'string', 'max:2000']]);
        $comprovante->update(['observacoes' => $request->observacoes]);

        return response()->json($comprovante);
    }

    public function destroy(Cobranca $cobranca, CobrancaComprovante $comprovante): JsonResponse
    {
        // Inquilino só pode remover comprovantes que ele mesmo enviou
        if ($this->isInquilino() && ! $this->isAdmin()) {
            abort_unless($comprovante->uploaded_by_user_id === auth()->id(), 403, 'Você só pode remover comprovantes que você enviou.');
        }

        if ($comprovante->caminho) {
            Storage::disk('public')->delete($comprovante->caminho);
        }
        $comprovante->delete();

        return response()->json(['message' => 'Comprovante removido.']);
    }
}
