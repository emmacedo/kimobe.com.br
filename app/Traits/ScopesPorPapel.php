<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait que centraliza a lógica de filtragem por papel do usuário.
 * Admin vê tudo. Proprietário vê seus imóveis/repasses. Inquilino vê suas cobranças/contratos.
 */
trait ScopesPorPapel
{
    protected function getPapeis(): array
    {
        $service = app(TenantService::class);
        $tenant = $service->getTenant();
        $user = auth()->user();

        if (! $user || ! $tenant) {
            return [];
        }

        return $service->getUserPapeis($user, $tenant);
    }

    protected function isAdmin(): bool
    {
        return in_array('admin', $this->getPapeis());
    }

    protected function isProprietario(): bool
    {
        return in_array('proprietario', $this->getPapeis());
    }

    protected function isInquilino(): bool
    {
        return in_array('inquilino', $this->getPapeis());
    }

    /**
     * Filtra imóveis: admin vê todos, proprietário vê os que tem titularidade.
     */
    protected function scopeImoveisDoUsuario(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereHas('titularidades.vinculo', fn ($q) => $q->where('user_id', auth()->id()));
    }

    /**
     * Filtra contratos: admin vê todos, proprietário por imóvel, inquilino por vínculo.
     */
    protected function scopeContratosDoUsuario(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isProprietario()) {
            return $query->whereHas('imovel.titularidades.vinculo', fn ($q) => $q->where('user_id', auth()->id()));
        }

        if ($this->isInquilino()) {
            return $query->whereHas('inquilino', fn ($q) => $q->where('user_id', auth()->id()));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Filtra cobranças: admin vê todas, proprietário por imóvel, inquilino por contrato.
     */
    protected function scopeCobrancasDoUsuario(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isProprietario()) {
            return $query->whereHas('contrato.imovel.titularidades.vinculo', fn ($q) => $q->where('user_id', auth()->id()));
        }

        if ($this->isInquilino()) {
            return $query->whereHas('contrato.inquilino', fn ($q) => $q->where('user_id', auth()->id()));
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Filtra repasses: admin vê todos, proprietário vê os seus.
     */
    protected function scopeRepassesDoUsuario(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereHas('titularidade.vinculo', fn ($q) => $q->where('user_id', auth()->id()));
    }

    /**
     * Verifica se o user tem acesso a um imóvel específico.
     */
    protected function podeVerImovel($imovel): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $imovel->titularidades()
            ->whereHas('vinculo', fn ($q) => $q->where('user_id', auth()->id()))
            ->exists();
    }

    /**
     * Verifica se o user tem acesso a um contrato específico.
     */
    protected function podeVerContrato($contrato): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isProprietario()) {
            return $contrato->imovel->titularidades()
                ->whereHas('vinculo', fn ($q) => $q->where('user_id', auth()->id()))
                ->exists();
        }

        if ($this->isInquilino()) {
            return $contrato->inquilino->user_id === auth()->id();
        }

        return false;
    }

    /**
     * Verifica se o user tem acesso a uma cobrança específica.
     */
    protected function podeVerCobranca($cobranca): bool
    {
        return $this->podeVerContrato($cobranca->contrato);
    }

    /**
     * Verifica se o user tem acesso a um repasse específico.
     */
    protected function podeVerRepasse($repasse): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $repasse->titularidade->vinculo->user_id === auth()->id();
    }
}
