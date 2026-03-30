<?php

namespace App\Http\Controllers;

use App\Models\Repasse;
use App\Models\RepasseComprovante;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepasseComprovanteController extends Controller
{
    public function store(Request $request, Repasse $repasse): JsonResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $arquivo = $request->file('arquivo');
        $nomeUnico = Str::uuid() . '.' . $arquivo->getClientOriginalExtension();
        $caminho = $arquivo->storeAs("comprovantes/repasses/{$repasse->id}", $nomeUnico, 'public');

        $comprovante = RepasseComprovante::create([
            'tenant_id' => app(TenantService::class)->getTenantId(),
            'repasse_id' => $repasse->id,
            'caminho' => $caminho,
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'tamanho_bytes' => $arquivo->getSize(),
        ]);

        return response()->json($comprovante->fresh(), 201);
    }

    public function update(Request $request, Repasse $repasse, RepasseComprovante $comprovante): JsonResponse
    {
        $request->validate(['observacoes' => ['nullable', 'string', 'max:2000']]);
        $comprovante->update(['observacoes' => $request->observacoes]);

        return response()->json($comprovante);
    }

    public function destroy(Repasse $repasse, RepasseComprovante $comprovante): JsonResponse
    {
        if ($comprovante->caminho) {
            Storage::disk('public')->delete($comprovante->caminho);
        }
        $comprovante->delete();

        return response()->json(['message' => 'Comprovante removido.']);
    }
}
