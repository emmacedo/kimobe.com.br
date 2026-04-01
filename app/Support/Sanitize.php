<?php

namespace App\Support;

/**
 * Helper de sanitização para campos com máscara de input.
 * Remove formatação antes de validar/salvar, garantindo dados limpos
 * independente do formato enviado pelo frontend.
 */
class Sanitize
{
    /**
     * Remove tudo que não é dígito.
     */
    public static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Sanitiza telefone: retorna apenas dígitos.
     * (21) 99999-8888 → 21999998888
     */
    public static function telefone(string $value): string
    {
        return self::digits($value);
    }

    /**
     * Sanitiza CPF: retorna apenas dígitos.
     * 123.456.789-00 → 12345678900
     */
    public static function cpf(string $value): string
    {
        return self::digits($value);
    }

    /**
     * Sanitiza CNPJ: retorna apenas dígitos.
     * 12.345.678/0001-00 → 12345678000100
     */
    public static function cnpj(string $value): string
    {
        return self::digits($value);
    }

    /**
     * Sanitiza CEP: retorna apenas dígitos.
     * 20040-020 → 20040020
     */
    public static function cep(string $value): string
    {
        return self::digits($value);
    }

    /**
     * Sanitiza valor monetário pt-BR para float.
     * R$ 2.500,00 → 2500.00 | 2500.00 → 2500.00 | 2500 → 2500.00
     */
    public static function moeda(string|int|float $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove R$, espaços e pontos de milhar, troca vírgula por ponto
        $clean = str_replace(['R$', ' ', '.'], '', (string) $value);
        $clean = str_replace(',', '.', $clean);

        return (float) $clean;
    }
}
