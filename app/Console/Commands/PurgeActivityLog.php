<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

#[Signature('activitylog:purge {--anos=5 : Idade mínima (em anos) para purge}')]
#[Description('Remove registros de activity_log com mais que --anos de idade. Padrão: 5 anos (LGPD/contratual).')]
class PurgeActivityLog extends Command
{
    public function handle(): int
    {
        $anos = (int) $this->option('anos');

        if ($anos < 1) {
            $this->error('Opção --anos deve ser >= 1.');

            return self::FAILURE;
        }

        $cutoff = CarbonImmutable::now()->subYears($anos)->startOfDay();

        $total = Activity::where('created_at', '<', $cutoff)->count();

        if ($total === 0) {
            $this->info("Nenhum registro de activity_log anterior a {$cutoff->toDateString()} encontrado.");

            return self::SUCCESS;
        }

        $this->info("Removendo {$total} registros de activity_log anteriores a {$cutoff->toDateString()}...");

        $removidos = Activity::where('created_at', '<', $cutoff)->delete();

        $this->info("Removidos {$removidos} registros.");

        return self::SUCCESS;
    }
}
