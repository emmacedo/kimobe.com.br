<?php

namespace App\Observers;

use App\Models\Plano;
use Illuminate\Support\Facades\Cache;

class PlanoObserver
{
    public function saved(Plano $plano): void
    {
        $this->invalidarCachePublico();
    }

    public function deleted(Plano $plano): void
    {
        $this->invalidarCachePublico();
    }

    private function invalidarCachePublico(): void
    {
        Cache::forget('public.planos.ativos_ordenados');
    }
}
