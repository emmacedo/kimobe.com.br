<x-mail::message>
# [Alerta interno] Tenant Kimobe precisa de plano custom

Um tenant atingiu o teto da escada de planos e precisa de plano sob medida.

**Tenant:**

- Nome / razão social: **{{ $tenant->nome }}**
- Razão social: {{ $tenant->legal_name ?? '—' }}
- Tipo: {{ $tenant->tipo }}
- Documento: {{ ($tenant->tipo_documento ?? 'cpf') === 'cnpj' ? 'CNPJ' : 'CPF' }} {{ $tenant->documento ?? '—' }}
- Telefone: {{ $tenant->telefone_comercial ?? $tenant->whatsapp ?? '—' }}
- E-mail de contato: {{ $tenant->email_contato ?? '—' }}

**Contexto:**

- Plano atual: `{{ $currentPlanCode ?? '—' }}`
- Módulo que estourou: `{{ $triggerModule }}`
- A operação foi liberada (decisão δ — não bloqueamos)
- O tenant recebeu e-mail informando que entraremos em contato

**Próximos passos sugeridos:**

1. Avaliar uso real (imóveis cadastrados, contratos ativos)
2. Criar plano custom no FullFlow se fizer sentido
3. Sincronizar catálogo (`fullflow:catalog-sync`) e oferecer ao cliente
4. Migrar a assinatura no FullFlow

<x-mail::button :url="config('app.url').'/admin/assinantes'">
Ver no painel admin
</x-mail::button>

— Kimobe (auto)
</x-mail::message>
