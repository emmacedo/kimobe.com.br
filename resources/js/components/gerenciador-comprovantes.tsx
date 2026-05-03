import { Download, FileText, Image, Loader2, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';

type Comprovante = {
    id: number;
    arquivo: string;
    url: string;
    nome_original: string;
    mime_type: string;
    tamanho_bytes: number;
    tipo: string;
    observacoes: string | null;
    created_at: string;
};

type Props = {
    entidadeId: number;
    entidadeTipo: 'fatura' | 'repasse' | 'item-cobranca';
    comprovantes: Comprovante[];
    readOnly?: boolean;
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

const ACCEPTED_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE = 10 * 1024 * 1024;

export function GerenciadorComprovantes({ entidadeId, entidadeTipo, comprovantes: comprovantesIniciais, readOnly = false }: Props) {
    const [comprovantes, setComprovantes] = useState<Comprovante[]>(comprovantesIniciais);
    const [uploading, setUploading] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Comprovante | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const uploadPath = (() => {
        switch (entidadeTipo) {
            case 'fatura':
                return `/financeiro/faturas/${entidadeId}/comprovantes`;
            case 'repasse':
                return `/financeiro/repasses/${entidadeId}/comprovantes`;
            case 'item-cobranca':
                return `/itens-cobranca/${entidadeId}/comprovantes`;
        }
    })();

    async function handleUpload(files: FileList | null) {
        if (!files || files.length === 0) return;

        for (const file of Array.from(files)) {
            if (!ACCEPTED_TYPES.includes(file.type)) {
                toast.error(`"${file.name}" não é um formato aceito. Use PDF, JPG, PNG ou WebP.`);
                continue;
            }
            if (file.size > MAX_SIZE) {
                toast.error(`"${file.name}" excede 10MB.`);
                continue;
            }

            setUploading(true);
            const formData = new FormData();
            formData.append('arquivo', file);

            try {
                const response = await fetch(uploadPath, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                    body: formData,
                });
                if (!response.ok) { toast.error('Erro ao enviar arquivo.'); continue; }
                const comprovante: Comprovante = await response.json();
                setComprovantes((prev) => [...prev, comprovante]);
                toast.success(`"${file.name}" enviado.`);
            } catch {
                toast.error(`Erro ao enviar "${file.name}".`);
            } finally {
                setUploading(false);
            }
        }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            await fetch(`/comprovantes/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });
            setComprovantes((prev) => prev.filter((c) => c.id !== deleteTarget.id));
            toast.success('Comprovante removido.');
        } catch { toast.error('Erro ao remover.'); }
        finally { setDeleteLoading(false); setDeleteTarget(null); }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Comprovantes</p>

            {/* Upload zone */}
            {!readOnly && (
                <div
                    onClick={() => fileInputRef.current?.click()}
                    onDrop={(e) => { e.preventDefault(); handleUpload(e.dataTransfer.files); }}
                    onDragOver={(e) => e.preventDefault()}
                    className="mb-3 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[#D8DCDA] bg-[#FAFBFA] px-4 py-4 text-sm transition-colors hover:border-[#0A4F5C] hover:bg-[#F7F8F7]"
                >
                    {uploading ? <Loader2 className="h-4 w-4 animate-spin text-[#0A4F5C]" /> : <Upload className="h-4 w-4 text-[#8A918E]" />}
                    <span className="text-[#6B7370]">{uploading ? 'Enviando...' : 'Arraste ou clique para enviar'}</span>
                    <input ref={fileInputRef} type="file" accept="application/pdf,image/jpeg,image/png,image/webp" multiple className="hidden"
                        onChange={(e) => handleUpload(e.target.files)} />
                </div>
            )}

            {comprovantes.length === 0 ? (
                <p className="text-sm text-[#8A918E]">Nenhum comprovante anexado.</p>
            ) : (
                <div className="space-y-2">
                    {comprovantes.map((c) => (
                        <div key={c.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                            <div className="flex items-center gap-2 min-w-0">
                                {c.mime_type.startsWith('image/') ? (
                                    <Image className="h-4 w-4 shrink-0 text-[#0A4F5C]" />
                                ) : (
                                    <FileText className="h-4 w-4 shrink-0 text-[#A83232]" />
                                )}
                                <div className="min-w-0">
                                    <p className="truncate text-sm text-[#3A4240]">{c.nome_original}</p>
                                    <p className="text-[10px] text-[#8A918E]">{formatBytes(c.tamanho_bytes)} · {formatDate(c.created_at)}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                                <a href={c.url} target="_blank" rel="noopener noreferrer">
                                    <Button variant="ghost" size="icon" className="h-7 w-7" aria-label="Baixar">
                                        <Download className="h-3.5 w-3.5 text-[#6B7370]" />
                                    </Button>
                                </a>
                                {!readOnly && (
                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(c)} aria-label="Remover">
                                        <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <ConfirmDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover comprovante" descricao={deleteTarget ? `Remover "${deleteTarget.nome_arquivo}"?` : ''}
                textoConfirmar="Remover" variante="destructive" loading={deleteLoading} onConfirm={handleExcluir} />
        </div>
    );
}
