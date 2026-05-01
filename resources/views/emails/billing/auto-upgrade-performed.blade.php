<x-mail::message>
# Seu plano foi atualizado

Olá, equipe **{{ $tenant->nome }}**,

A operação atingiu o limite de **{{ $moduleLabel }}** do plano **{{ $fromPlan?->name ?? '—' }}**. Para que sua imobiliária continue funcionando sem interrupções, atualizamos automaticamente sua assinatura para o plano **{{ $toPlan->name }}**.

**Resumo da alteração:**

- Plano anterior: {{ $fromPlan?->name ?? '—' }}@if($fromPlan) (R$ {{ number_format((float) $fromPlan->amount, 2, ',', '.') }} / {{ $fromPlan->billing_cycle }})@endif
- Plano atual: **{{ $toPlan->name }} (R$ {{ number_format((float) $toPlan->amount, 2, ',', '.') }} / {{ $toPlan->billing_cycle }})**
@if($prorationAmount !== null && $prorationAmount > 0)
- Cobrança proporcional gerada: **R$ {{ number_format($prorationAmount, 2, ',', '.') }}**
@endif

A cobrança proporcional cobre apenas a diferença entre o plano antigo e o novo no período atual. A partir do próximo ciclo, o valor cobrado será o do novo plano.

Você pode revisar sua assinatura, ver as cobranças geradas ou desativar o upgrade automático no seu painel:

<x-mail::button :url="config('app.url').'/settings/plano'">
Ver minha assinatura
</x-mail::button>

Se você não autorizou esta atualização ou tem dúvidas sobre a cobrança, responda este e-mail e nossa equipe te ajuda.

Atenciosamente,
**Equipe Kimobe**
</x-mail::message>
