<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertas preventivos de quota (80%, 95%) por e-mail — varredura horária
Schedule::command('quotas:check')->hourly()->withoutOverlapping();

// Reconcilia status de assinaturas FullFlow (drift detection) a cada 6h
Schedule::command('fullflow:reconcile')->everySixHours()->withoutOverlapping();

// Purge mensal do activity_log: retenção de 5 anos (LGPD/contratual)
Schedule::command('activitylog:purge')->monthlyOn(1, '02:30')->withoutOverlapping();
