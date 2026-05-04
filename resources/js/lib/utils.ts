import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Formata valor numérico para moeda brasileira (R$ 2.400,00).
 */
export function formataMoeda(valor: number | string | null | undefined): string {
    const num = typeof valor === 'string' ? parseFloat(valor) : (valor ?? 0);
    return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

/**
 * Formata data ISO 'YYYY-MM-DD' (ou null) para 'DD/MM/YYYY'. Retorna '—' quando nula.
 * Usa parsing manual para evitar conversão de timezone (interpretar como local).
 */
export function formataData(d: string | null | undefined): string {
    if (!d) return '—';
    const [ano, mm, dd] = d.split('-').map(Number);
    return new Date(ano, mm - 1, dd).toLocaleDateString('pt-BR');
}

/**
 * Formata CEP para o padrão 00000-000.
 */
export function formataCep(cep: string): string {
    const digits = cep.replace(/\D/g, '');
    if (digits.length !== 8) return cep;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

/**
 * Formata CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00) conforme a quantidade de dígitos.
 */
export function formataCpfCnpj(valor: string | null | undefined): string {
    if (!valor) return '';
    const d = valor.replace(/\D/g, '');
    if (d.length === 11) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
    if (d.length === 14) return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8, 12)}-${d.slice(12)}`;
    return valor;
}

/**
 * Formata telefone brasileiro: (00) 0000-0000 (fixo) ou (00) 00000-0000 (celular).
 */
export function formataTelefone(valor: string | null | undefined): string {
    if (!valor) return '';
    const d = valor.replace(/\D/g, '');
    if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
    return valor;
}

/**
 * CPF/CNPJ mascarado com asteriscos no meio — usado em listagens de busca onde
 * mostrar o documento completo expõe dado sensível.
 * CPF: 11 dígitos vira 123.[asterisks].[asterisks]-01.
 * CNPJ: 14 dígitos vira 12.[asterisks].[asterisks].[asterisks]-34.
 */
export function formataCpfCnpjMascarado(doc: string | null | undefined): string {
    if (!doc) return '';
    const d = doc.replace(/\D/g, '');
    if (d.length === 11) return `${d.slice(0, 3)}.***.***-${d.slice(9)}`;
    if (d.length === 14) return `${d.slice(0, 2)}.***.***/****-${d.slice(12)}`;
    return doc;
}

/**
 * Retorna saudação baseada no horário atual.
 */
export function saudacao(nome: string): string {
    const hora = new Date().getHours();
    const cumprimento = hora < 12 ? 'Bom dia' : hora < 18 ? 'Boa tarde' : 'Boa noite';
    return `${cumprimento}, ${nome.split(' ')[0]}`;
}
