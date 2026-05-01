<x-mail::message>
@if($threshold >= 95)
# {{ $moduleLabel }} próximo do limite

Olá, equipe **{{ $tenant->nome }}**,

Você está usando **{{ $percentUsed }}%** de **{{ $moduleLabel }}** do seu plano atual ({{ $currentValue }} de {{ $limitValue }}).

@if($autoUpgradeEnabled)
@if($nextPlan)
Quando atingir 100%, sua assinatura será **atualizada automaticamente** para o plano **{{ $nextPlan->name }}** (R$ {{ number_format((float) $nextPlan->amount, 2, ',', '.') }} / {{ $nextPlan->billing_cycle }}), com cobrança proporcional pela diferença.
@else
Quando atingir 100%, vamos avaliar com você um plano sob medida — nossa equipe entrará em contato. Até lá, o serviço continua sem interrupção.
@endif
@else
**Você desativou o upgrade automático.** Quando atingir 100%, novas operações ficarão bloqueadas até que você faça upgrade manualmente no seu painel.
@endif

Se quiser antecipar o upgrade ou alterar o plano, faça pelo painel:
@else
# {{ $moduleLabel }} a 80% do limite

Olá, equipe **{{ $tenant->nome }}**,

Um aviso amigável: você já está usando **{{ $percentUsed }}%** de **{{ $moduleLabel }}** do seu plano atual ({{ $currentValue }} de {{ $limitValue }}).

@if($autoUpgradeEnabled)
@if($nextPlan)
Quando você atingir 100%, sua assinatura será atualizada automaticamente para o plano **{{ $nextPlan->name }}** com cobrança proporcional. Sem surpresas.
@else
Você está no plano de maior cobertura. Caso ultrapasse o limite, nossa equipe entrará em contato para um plano sob medida. O serviço continua liberado normalmente.
@endif
@else
**Você desativou o upgrade automático.** Caso atinja o limite, novas operações ficarão bloqueadas até upgrade manual.
@endif

Quer adiantar o upgrade? Acesse seu painel:
@endif

<x-mail::button :url="config('app.url').'/settings/plano'">
Ver minha assinatura
</x-mail::button>

Atenciosamente,
**Equipe Kimobe**
</x-mail::message>
