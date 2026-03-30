import {
    DndContext,
    closestCenter,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    rectSortingStrategy,
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Camera, GripVertical, Loader2, Upload, X } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Input } from '@/components/ui/input';
import type { ImovelFoto } from '@/types/models';

type Props = {
    imovelId: number;
    fotos: ImovelFoto[];
    maxFotos?: number;
};

// Tipos de arquivo aceitos
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE = 5 * 1024 * 1024; // 5MB

// Obter CSRF token do meta tag
function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Componente de thumbnail individual, sortable via dnd-kit.
 */
function FotoThumbnail({
    foto,
    onLegendaChange,
    onDelete,
}: {
    foto: ImovelFoto;
    onLegendaChange: (id: number, legenda: string) => void;
    onDelete: (foto: ImovelFoto) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: foto.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div ref={setNodeRef} style={style} className="group relative">
            <div className="overflow-hidden rounded-lg border border-[#D8DCDA] bg-white">
                {/* Imagem */}
                <div className="relative aspect-[4/3]">
                    <img
                        src={foto.url}
                        alt={foto.legenda ?? foto.nome_arquivo}
                        className="h-full w-full object-cover"
                    />
                    {/* Badge principal */}
                    {foto.ordem === 1 && (
                        <span className="absolute left-2 top-2 rounded-full bg-[#C9A84C] px-2 py-0.5 text-[10px] font-medium text-white">
                            Principal
                        </span>
                    )}
                    {/* Botão excluir */}
                    <button
                        type="button"
                        onClick={() => onDelete(foto)}
                        className="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity hover:bg-[#A83232] group-hover:opacity-100"
                        aria-label="Excluir foto"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                    {/* Grip para arrastar */}
                    <button
                        type="button"
                        {...attributes}
                        {...listeners}
                        className="absolute bottom-2 left-2 flex h-6 w-6 cursor-grab items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100"
                        aria-label="Arrastar para reordenar"
                    >
                        <GripVertical className="h-3.5 w-3.5" />
                    </button>
                </div>
                {/* Legenda editável */}
                <div className="p-2">
                    <Input
                        value={foto.legenda ?? ''}
                        onChange={(e) => onLegendaChange(foto.id, e.target.value)}
                        placeholder="Adicionar legenda..."
                        className="h-7 border-0 bg-transparent px-1 text-xs text-[#3A4240] shadow-none focus-visible:ring-0"
                    />
                </div>
            </div>
        </div>
    );
}

export function GerenciadorFotos({ imovelId, fotos: fotosIniciais, maxFotos = 20 }: Props) {
    const [fotos, setFotos] = useState<ImovelFoto[]>(fotosIniciais);
    const [uploading, setUploading] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ImovelFoto | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const legendaTimeouts = useRef<Record<number, ReturnType<typeof setTimeout>>>({});

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    );

    // Upload de arquivo individual
    const uploadFile = useCallback(async (file: File) => {
        if (!ACCEPTED_TYPES.includes(file.type)) {
            toast.error(`O arquivo "${file.name}" não é um formato aceito. Use JPG, PNG ou WebP.`);
            return;
        }
        if (file.size > MAX_SIZE) {
            toast.error(`O arquivo "${file.name}" excede 5MB.`);
            return;
        }

        const formData = new FormData();
        formData.append('foto', file);

        try {
            const response = await fetch(`/imoveis/${imovelId}/fotos`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao fazer upload.');
                return;
            }

            const novaFoto: ImovelFoto = await response.json();
            setFotos((prev) => [...prev, novaFoto]);
            toast.success(`"${file.name}" enviada.`);
        } catch {
            toast.error(`Erro ao enviar "${file.name}".`);
        }
    }, [imovelId]);

    // Processar múltiplos arquivos
    async function handleFiles(files: FileList | File[]) {
        const fileArray = Array.from(files);
        if (fotos.length + fileArray.length > maxFotos) {
            toast.error(`Máximo de ${maxFotos} fotos por imóvel.`);
            return;
        }

        setUploading(true);
        for (const file of fileArray) {
            await uploadFile(file);
        }
        setUploading(false);
    }

    // Drop zone handlers
    function handleDrop(e: React.DragEvent) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    }

    function handleDragOver(e: React.DragEvent) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Salvar legenda com debounce
    function handleLegendaChange(fotoId: number, legenda: string) {
        // Atualizar estado local imediatamente
        setFotos((prev) =>
            prev.map((f) => (f.id === fotoId ? { ...f, legenda } : f)),
        );

        // Debounce do save
        if (legendaTimeouts.current[fotoId]) {
            clearTimeout(legendaTimeouts.current[fotoId]);
        }
        legendaTimeouts.current[fotoId] = setTimeout(async () => {
            try {
                const imovel = fotos.find((f) => f.id === fotoId);
                await fetch(`/imoveis/${imovelId}/fotos/${fotoId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ legenda }),
                });
            } catch {
                // Silencioso — legenda não é crítica
            }
        }, 500);
    }

    // Reordenação via drag & drop
    async function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = fotos.findIndex((f) => f.id === active.id);
        const newIndex = fotos.findIndex((f) => f.id === over.id);

        const novaOrdem = arrayMove(fotos, oldIndex, newIndex).map((f, i) => ({
            ...f,
            ordem: i + 1,
        }));

        setFotos(novaOrdem);

        try {
            await fetch(`/imoveis/${imovelId}/fotos/reordenar`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    fotos: novaOrdem.map((f) => ({ id: f.id, ordem: f.ordem })),
                }),
            });
        } catch {
            toast.error('Erro ao reordenar fotos.');
        }
    }

    // Excluir foto
    async function handleDelete() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            const response = await fetch(`/imoveis/${imovelId}/fotos/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
            });
            if (response.ok) {
                // Remover e reordenar localmente
                setFotos((prev) => {
                    const restantes = prev.filter((f) => f.id !== deleteTarget.id);
                    return restantes.map((f, i) => ({ ...f, ordem: i + 1 }));
                });
                toast.success('Foto excluída.');
            } else {
                toast.error('Erro ao excluir foto.');
            }
        } catch {
            toast.error('Erro ao excluir foto.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Fotos</h2>

            {/* Zona de upload */}
            <div
                onDrop={handleDrop}
                onDragOver={handleDragOver}
                onClick={() => fileInputRef.current?.click()}
                className="mb-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-[#D8DCDA] bg-[#FAFBFA] px-6 py-8 transition-colors hover:border-[#0A4F5C] hover:bg-[#F7F8F7]"
            >
                {uploading ? (
                    <Loader2 className="mb-2 h-8 w-8 animate-spin text-[#0A4F5C]" />
                ) : (
                    <Upload className="mb-2 h-8 w-8 text-[#8A918E]" />
                )}
                <p className="text-sm text-[#3A4240]">
                    {uploading ? 'Enviando...' : 'Arraste fotos aqui ou clique para selecionar'}
                </p>
                <p className="mt-1 text-xs text-[#8A918E]">
                    JPG, PNG ou WebP. Máximo 5MB por foto.
                </p>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    className="hidden"
                    onChange={(e) => e.target.files && handleFiles(e.target.files)}
                />
            </div>

            {/* Galeria */}
            {fotos.length === 0 ? (
                <div className="flex flex-col items-center py-8 text-center">
                    <Camera className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhuma foto adicionada</p>
                </div>
            ) : (
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={fotos.map((f) => f.id)} strategy={rectSortingStrategy}>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            {fotos.map((foto) => (
                                <FotoThumbnail
                                    key={foto.id}
                                    foto={foto}
                                    onLegendaChange={handleLegendaChange}
                                    onDelete={setDeleteTarget}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}

            {/* Dialog de exclusão */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Excluir foto"
                descricao="Tem certeza que deseja excluir esta foto?"
                textoConfirmar="Excluir"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleDelete}
            />
        </div>
    );
}
