/**
 * Lê o token CSRF do meta tag injetado pelo Blade na raiz do app.
 * Usado em chamadas fetch() para endpoints internos que não passam pelo router Inertia.
 */
export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
