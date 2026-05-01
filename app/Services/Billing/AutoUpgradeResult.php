<?php

namespace App\Services\Billing;

/**
 * Retorno do AutoUpgradeService::ensureCapacityFor.
 *
 * isAllowed() indica se o caller pode prosseguir com a operação:
 *   - ok               → o plano atual já cobre, segue
 *   - upgraded         → fizemos upgrade automático, segue
 *   - overage_top_plan → estourou e não há plano superior; LIBERA mesmo assim (decisão δ)
 *   - skipped_disabled → tenant desligou auto_upgrade; BLOQUEIA (caller decide msg)
 *   - failed           → erro técnico (sem sub, FullFlow API down); BLOQUEIA
 */
class AutoUpgradeResult
{
    public const OK = 'ok';

    public const UPGRADED = 'upgraded';

    public const OVERAGE_TOP_PLAN = 'overage_top_plan';

    public const SKIPPED_DISABLED = 'skipped_disabled';

    public const FAILED = 'failed';

    public function __construct(
        public readonly bool $allowed,
        public readonly string $resultType,
        public readonly ?string $newPlanCode = null,
        public readonly ?float $prorationAmount = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function ok(): self
    {
        return new self(true, self::OK);
    }

    public static function upgraded(string $newPlanCode, ?float $prorationAmount): self
    {
        return new self(true, self::UPGRADED, $newPlanCode, $prorationAmount);
    }

    public static function overageTopPlan(): self
    {
        return new self(true, self::OVERAGE_TOP_PLAN);
    }

    public static function skippedDisabled(): self
    {
        return new self(false, self::SKIPPED_DISABLED);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(false, self::FAILED, errorMessage: $errorMessage);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
