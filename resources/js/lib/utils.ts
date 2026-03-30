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
 * Formata CEP para o padrão 00000-000.
 */
export function formataCep(cep: string): string {
    const digits = cep.replace(/\D/g, '');
    if (digits.length !== 8) return cep;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

/**
 * Retorna saudação baseada no horário atual.
 */
export function saudacao(nome: string): string {
    const hora = new Date().getHours();
    const cumprimento = hora < 12 ? 'Bom dia' : hora < 18 ? 'Boa tarde' : 'Boa noite';
    return `${cumprimento}, ${nome.split(' ')[0]}`;
}
