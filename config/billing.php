<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-mail interno Kicol
    |--------------------------------------------------------------------------
    | Endereço (ou múltiplos, separados por vírgula) que recebe alertas
    | operacionais — overage no plano topo, falhas críticas de billing, etc.
    | Se ficar vazio, alertas internos são suprimidos (apenas log).
    */
    'kicol_notifications_email' => env('KICOL_BILLING_NOTIFICATIONS_EMAIL', ''),
];
