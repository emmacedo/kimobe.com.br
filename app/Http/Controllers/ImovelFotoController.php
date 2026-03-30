<?php

namespace App\Http\Controllers;

use App\Models\Imovel;
use App\Models\ImovelFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImovelFotoController extends Controller
{
    /**
     * Upload de uma foto para o imóvel.
     * A nova foto é adicionada ao final da galeria (maior ordem + 1).
     */
    public function store(Request $request, Imovel $imovel): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'foto.required' => 'Selecione uma foto para upload.',
            'foto.image' => 'O arquivo deve ser uma imagem.',
            'foto.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
            'foto.max' => 'A foto não pode ter mais de 5MB.',
        ]);

        $arquivo = $request->file('foto');

        // Gerar nome único para evitar conflitos
        $nomeUnico = Str::uuid() . '.' . $arquivo->getClientOriginalExtension();
        $caminho = $arquivo->storeAs("imoveis/{$imovel->id}", $nomeUnico, 'public');

        // Próxima ordem disponível
        $maxOrdem = $imovel->fotos()->max('ordem') ?? 0;

        $foto = ImovelFoto::create([
            'tenant_id' => $imovel->tenant_id,
            'imovel_id' => $imovel->id,
            'caminho' => $caminho,
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'legenda' => null,
            'ordem' => $maxOrdem + 1,
            'mime_type' => $arquivo->getMimeType(),
            'tamanho_bytes' => $arquivo->getSize(),
        ]);

        return response()->json($foto->fresh(), 201);
    }

    /**
     * Atualiza a legenda de uma foto.
     */
    public function update(Request $request, Imovel $imovel, ImovelFoto $foto): JsonResponse
    {
        $request->validate([
            'legenda' => ['nullable', 'string', 'max:255'],
        ]);

        $foto->update(['legenda' => $request->input('legenda')]);

        return response()->json($foto);
    }

    /**
     * Reordena as fotos do imóvel.
     * Recebe array de {id, ordem} e atualiza cada foto.
     */
    public function reordenar(Request $request, Imovel $imovel): JsonResponse
    {
        $request->validate([
            'fotos' => ['required', 'array'],
            'fotos.*.id' => ['required', 'integer'],
            'fotos.*.ordem' => ['required', 'integer', 'min:1'],
        ]);

        $fotoIds = $imovel->fotos()->pluck('id')->toArray();

        foreach ($request->input('fotos') as $item) {
            // Validar que o ID pertence ao imóvel
            if (! in_array($item['id'], $fotoIds)) {
                continue;
            }
            ImovelFoto::where('id', $item['id'])->update(['ordem' => $item['ordem']]);
        }

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    /**
     * Exclui uma foto do imóvel.
     * Se era a primeira (ordem 1), reordena as restantes.
     */
    public function destroy(Imovel $imovel, ImovelFoto $foto): JsonResponse
    {
        // Excluir arquivo do storage
        if ($foto->caminho) {
            Storage::disk('public')->delete($foto->caminho);
        }

        $ordemExcluida = $foto->ordem;
        $foto->delete();

        // Se a foto excluída era a de menor ordem, reordenar as restantes
        $fotosRestantes = $imovel->fotos()->orderBy('ordem')->get();
        foreach ($fotosRestantes as $index => $f) {
            $f->update(['ordem' => $index + 1]);
        }

        return response()->json(['message' => 'Foto excluída.']);
    }
}
