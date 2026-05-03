KIMOBE
EVOLUÇÃO DO MÓDULO FINANCEIRO

Documento complementar ao escopo-completo.md
Versão 1.0 — Maio 2026
Desenvolvido por Kicol


# Sobre este documento

Este documento descreve evoluções do módulo financeiro do Kimobe que estão fora do escopo do MVP (definido em `escopo-completo.md`). Cada parte é independente, com escopo fechado, e pode ser implementada em momentos diferentes.

A motivação dessas evoluções vem do uso real previsto pelas imobiliárias:
- A modelagem atual de cobranças tem campos fixos que não acomodam responsabilidades customizadas.
- Cobranças extras com regras de quem paga vs. quem é responsável pelo custo não são suportadas.
- Não há histórico estruturado de mudanças em campos financeiros críticos (valor de aluguel, taxas, status).
- Cobranças e repasses não preservam os percentuais aplicados, apenas os valores calculados.

Janela ideal para implementação: o sistema ainda tem volume baixo de dados em produção. Refatorações de modelagem são triviais agora e ficam custosas conforme o uso cresce.

---

# 1. Visão Geral do Domínio

Antes de qualquer detalhe técnico, fica fixado aqui o modelo mental do sistema. Tudo nas seções seguintes se encaixa nessa estrutura única. Sempre que uma discussão parecer abstrata, voltar aqui para se reorientar.

## 1.1. A árvore única do domínio

Todo o universo financeiro do sistema se organiza assim:

```
Imóvel
└── Contrato de locação (guarda-chuva)
    ├── Itens de cobrança (estrutura única — qualquer despesa, receita ou ajuste)
    │   ├── Aluguel (recorrente mensal)
    │   ├── Responsabilidades recorrentes (condomínio, IPTU, seguro)
    │   ├── Despesas parceladas (IPTU 12x, seguro em 4x)
    │   ├── Despesas avulsas (chuveiro, frete, multa)
    │   └── Ajustes (abatimentos, reembolsos)
    │
    └── Faturas mensais
        └── Agrupam os itens de cobrança conciliados naquele mês
```

A única tabela onde se cadastra qualquer movimentação financeira é `itens_cobranca`. A `faturas` (renomeação conceitual de `cobrancas`) é apenas o agrupador mensal — não tem itens próprios. Não há "lançamentos avulsos" como módulo separado, não há "responsabilidades" como tabela separada. Tudo é item.

## 1.2. Os três atores econômicos e a entidade externa

Toda movimentação tem dois polos internos e, opcionalmente, um destino externo:

| Eixo | Pergunta | Valores |
|---|---|---|
| pagante | Quem é debitado? De cujo patrimônio sai o dinheiro? | inquilino \| proprietario \| administradora |
| recebedor | Quem é creditado? Para cujo patrimônio o dinheiro vai? | inquilino \| proprietario \| administradora |
| entidade_externa | Destinatário externo (síndico, prefeitura, seguradora, prestador) | FK opcional |

Quando `recebedor = administradora` e `entidade_externa_id` está preenchida, é **intermediação** — a admin recebe e repassa a um terceiro fora do sistema. Aqui mora o controle "admin já pagou ao fornecedor?".

Quando `recebedor = administradora` sem entidade externa, é **receita real** da admin (taxa de admin, frete reembolsado, multa retida).

### Cenários canônicos

| Item | tipo | pagante | recebedor | entidade externa |
|---|---|---|---|---|
| Aluguel | recorrente mensal | inquilino | proprietario | — |
| Taxa de admin | recorrente mensal | proprietario | administradora | — (receita real) |
| Condomínio | recorrente mensal | inquilino | administradora | Imodata (síndico) |
| IPTU 12x | parcelado | inquilino | administradora | Prefeitura |
| Cota extra obra | avulso | proprietario | administradora | Imodata |
| Chuveiro adiantado pelo inquilino | avulso | proprietario | inquilino | — |
| Frete de documentos | avulso | proprietario | administradora | Correios |

## 1.3. Pré-geração e imutabilidade

Itens recorrentes e parcelados são **pré-gerados** desde a criação. Aluguel mensal de contrato de 30 meses gera 30 ocorrências de uma vez. Cada ocorrência é uma linha em `itens_cobranca`, agrupada via `parent_item_id` (padrão validado no MoneyMagnet).

A primeira ocorrência tem `parent_item_id = NULL`. As demais apontam pra ela. Edição pode ser feita em três modos:

| Modo | Efeito |
|---|---|
| Só esta | Atualiza só a linha selecionada |
| Esta e futuras | Atualiza pendentes a partir do mês selecionado |
| Todas | Atualiza todas as ocorrências pendentes da série |

**Itens conciliados (já em uma fatura fechada) são imutáveis.** Esse é o mecanismo que protege o histórico — não há duplicação de snapshot, há proteção por status.

## 1.4. Como se conectam itens e faturas

Não há tabela intermediária `fatura_itens`. O modelo é direto: cada `item_cobranca` ganha `fatura_id` quando é conciliado. A fatura calcula seu `valor_total` pela soma dos itens conciliados nela.

```
itens_cobranca (cada linha = uma ocorrência)
       │
       │  fatura_id (preenchido ao conciliar)
       ▼
faturas (apenas agrupa)
```

## 1.5. Princípios da evolução

Seis princípios que guiam todas as decisões deste documento.

1. **Modularidade e responsabilidade única.** Cada evolução respeita as fronteiras de responsabilidade dos módulos. Módulos se comunicam por interfaces explícitas (services, events), nunca por acoplamento direto a tabelas ou colunas internas de outro módulo.

2. **Hierarquia de rigor na compatibilidade.** Banco de dados e regras de negócio são invioláveis — sem perda de dados, sem mudança de comportamento. UI/UX pode evoluir quando a melhoria justificar.

3. **Histórico não destrói o presente.** Toda mudança em valor preserva o registro anterior. Faturas passadas nunca mudam quando o contrato é alterado.

4. **Imutabilidade do conciliado.** Itens com `status='conciliado'` não são editados. Para corrigir efeitos passados, cria-se item de ajuste no mês corrente. Faturas passadas ficam blindadas porque seus itens estão imutáveis.

5. **Preparação para multi-usuário.** Mesmo com sistema mono-usuário hoje, todo registro captura `criado_por_user_id` e `atualizado_por_user_id`. Quando virar multi-usuário, não há migration retroativa.

6. **Três atores econômicos.** Inquilino, proprietário e administradora são entidades de primeira classe nos lançamentos financeiros. A administradora não é apenas o operador — é também credora, devedora e intermediária.

## 1.6. Mapa das evoluções

| Evolução | Frente | Função |
|---|---|---|
| Modelo unificado: `itens_cobranca`, `faturas`, `entidades_externas`, pré-geração, padrão `parent_item_id` | Frente 1 | Substitui o modelo atual fragmentado |
| Snapshot de percentuais aplicados | Frente 2 | Preserva valores e percentuais aplicados em faturas e repasses |
| Auditoria estruturada (`activitylog` + campos de autoria) | Frente 3 | Histórico fino de mudanças críticas |

## 1.7. Frentes técnicas

A antiga Frente 3 (Lançamentos Avulsos) deixou de existir como módulo separado. Seu escopo foi totalmente absorvido pela Frente 1. A antiga Frente 4 (Auditoria) passa a ser a Frente 3.

| # | Frente | Pré-requisitos | Risco |
|---|---|---|---|
| 1 | Modelo unificado | Nenhum | Médio (refatoração ampla) |
| 2 | Snapshot de percentuais | Frente 1 | Baixo |
| 3 | Auditoria estruturada | Nenhum | Baixo |

Frentes 1 e 3 podem ser feitas em paralelo. Frente 2 depende da 1.

---

# 2. Frente 1 — Modelo Unificado de Cobrança

Esta frente substitui a modelagem atual de cobranças (que separa "responsabilidades recorrentes" + "5 colunas fixas em cobrancas" + "itens extras") por uma estrutura única e genérica, inspirada no padrão validado pelo MoneyMagnet. Absorve integralmente o escopo da antiga Frente 3 (Lançamentos Avulsos), que deixa de existir como módulo separado.

## 2.1. Problemas resolvidos

A modelagem do MVP fragmenta a representação financeira em várias estruturas:

- `contrato_responsabilidades`: tabela genérica para recorrências do contrato.
- `cobrancas`: cobrança mensal com 5 colunas fixas hardcoded (`valor_aluguel`, `valor_condominio`, `valor_iptu`, `valor_seguro_incendio`, `valor_taxa_bombeiros`, `valor_taxa_extra_condominio`).
- `cobranca_itens_extras`: tabela auxiliar para itens que não cabem nas 5 colunas fixas.

Essa fragmentação gera os problemas mapeados na análise prévia, e mais:

1. Mapeamento de responsabilidade para cobrança via string matching frágil (ver `CobrancaService::mapearResponsabilidades`).
2. Sem vínculo entre cobrança e responsabilidade originária — mudanças em responsabilidades órfãm cobranças passadas.
3. Itens extras só somam — não suportam abatimentos (valor negativo).
4. Sem suporte a 3 atores (cenário do chuveiro pago pelo inquilino, frete da admin, etc.).
5. Sem controle de pagamentos a entidades externas (síndico, prefeitura, seguradora).
6. Sem visibilidade seletiva por papel (taxa de admin não deveria aparecer no extrato do inquilino).
7. Sem versionamento natural de alterações ao longo do tempo.

## 2.2. Tabela `entidades_externas` (renomeação de `administradoras`)

A tabela atual `administradoras` foi modelada para representar a administradora do condomínio do imóvel. O nome causa ambiguidade — "administradora" no Kimobe pode significar tanto isso quanto o tenant (a imobiliária). A renomeação resolve a ambiguidade e generaliza o uso para qualquer entidade externa: síndico, prefeitura, seguradora, prestador de serviço, empresa, pessoa física.

### Estrutura

| Campo | Tipo | Descrição |
|---|---|---|
| id | bigint PK | Identificador único |
| tenant_id | FK tenants | Isolamento multi-tenant |
| nome | string(255) | Razão social ou nome |
| tipo | enum | administradora_condominio \| sindico \| prefeitura \| seguradora \| prestador_servico \| empresa \| pessoa_fisica \| outro |
| documento | string(14) nullable | CPF (11 dígitos) ou CNPJ (14 dígitos) |
| telefone, email, site | strings nullable | Contatos |
| contato_interno_nome | string(255) nullable | Pessoa de contato |
| Campos de endereço | strings nullable | cep, logradouro, numero, complemento, bairro, cidade, uf |
| observacoes | text nullable | Notas internas |
| timestamps + softDeletes | — | Padrão Laravel |

### Migração

- Renomear tabela `administradoras` → `entidades_externas`.
- Adicionar coluna `tipo` com backfill `tipo='administradora_condominio'` para registros existentes.
- Renomear FK `condominios.administradora_id` → `condominios.entidade_externa_id`.
- Atualizar model `Administradora` → `EntidadeExterna`.
- Atualizar referências em controllers, views, factories, testes.
- **Reuso automático na UI:** ao criar item de cobrança "Condomínio" para um imóvel cujo `condominio.entidade_externa_id` está preenchido, sugerir automaticamente a entidade vinculada (operador pode trocar).

## 2.3. Tabela `itens_cobranca` (nova — coração do modelo)

Cada linha representa **uma ocorrência** de uma despesa, receita ou ajuste no contrato. Itens recorrentes e parcelados são pré-gerados em N linhas (uma por mês de competência), agrupadas via `parent_item_id`.

### Estrutura

| Campo | Tipo | Descrição |
|---|---|---|
| id | bigint PK | Identificador único da ocorrência |
| parent_item_id | FK self nullable | NULL na primeira ocorrência da série; preenchido nas demais |
| tenant_id | FK tenants | Isolamento |
| contrato_id | FK contratos | Contrato pai |
| descricao | string(255) | "Aluguel", "IPTU 2026", "Troca de chuveiro" |
| pagante | enum | inquilino \| proprietario \| administradora |
| recebedor | enum | inquilino \| proprietario \| administradora |
| entidade_externa_id | FK entidades_externas nullable | Destino externo (intermediação) |
| tipo | enum | recorrente \| parcelado \| avulso |
| periodicidade | enum nullable | mensal \| bimestral \| trimestral \| semestral \| anual (apenas se tipo=recorrente) |
| num_parcela | unsignedSmallInt nullable | Posição (parcela 5/12). Apenas se tipo=parcelado |
| num_parcelas_total | unsignedSmallInt nullable | Total da série. Apenas se tipo=parcelado |
| valor_unitario | decimal(10,2) | Valor desta ocorrência. Pode ser negativo (abatimento) |
| mes_referencia | char(7) | MM/YYYY — mês onde a ocorrência cai na fatura |
| visivel_inquilino | boolean default true | Aparece no extrato do inquilino. Forçado true se pagante=inquilino |
| status | enum | pendente \| conciliado \| cancelado |
| fatura_id | FK faturas nullable | Preenchido quando conciliado |
| data_pagamento_externo | date nullable | Quando admin pagou a entidade externa (intermediação) |
| pagamento_externo_por_user_id | FK users nullable | Operador que registrou o pagamento externo |
| observacoes | text nullable | Notas livres |
| criado_por_user_id | FK users | Quem criou (preparação multi-usuário) |
| atualizado_por_user_id | FK users nullable | Quem editou pela última vez |
| timestamps | — | created_at, updated_at |

### Regras de domínio

1. Itens com `status='conciliado'` são imutáveis. Para corrigir efeitos passados, criar item de ajuste no mês corrente.
2. `pagante=inquilino` força `visivel_inquilino=true`.
3. `tipo=recorrente` exige `periodicidade` preenchida; proíbe `num_parcela` e `num_parcelas_total`.
4. `tipo=parcelado` exige `num_parcela` e `num_parcelas_total`; proíbe `periodicidade`.
5. `tipo=avulso` não tem periodicidade nem parcelas.
6. `recebedor=administradora` + `entidade_externa_id` preenchido marca intermediação. Habilita `data_pagamento_externo` e `pagamento_externo_por_user_id`.
7. Mudanças em `tipo`, `parent_item_id` ou `mes_referencia` exigem cancelamento + recriação (não é alteração — é nova série).

## 2.4. Tabela `faturas` (renomeação de `cobrancas`)

A atual `cobrancas` passa a ser `faturas` — apenas o agrupador mensal. Modelo direto: cada item conciliado aponta pra fatura via `fatura_id`. Não há tabela intermediária `fatura_itens` (decisão Modelo A — ver Seção 1.4).

### Mudanças

**Remove (5 colunas fixas):**
- `valor_aluguel`, `valor_condominio`, `valor_iptu`, `valor_seguro_incendio`, `valor_taxa_bombeiros`, `valor_taxa_extra_condominio`

**Mantém:**
- `referencia` (07/2026), `data_vencimento`, `data_pagamento`, `metodo_pagamento`
- `valor_pago`, `valor_desconto`, `valor_juros`, `valor_multa`
- `tipo_geracao` (automatica \| manual)
- `status` (pendente \| pago \| atrasado \| cancelado)
- `url_boleto`, `observacoes`

**Mantém com nova semântica:**
- `valor_total`: agora é denormalização da soma dos itens conciliados (`SUM(itens_cobranca.valor_unitario WHERE fatura_id=X)`). Atualizado via observer ou na transação de fechamento.

**Drop:**
- Tabela `cobranca_itens_extras` (substituída por `itens_cobranca` com `tipo=avulso`).
- Tabela `contrato_responsabilidades` (substituída por `itens_cobranca`).

## 2.5. Pré-geração de ocorrências (padrão MoneyMagnet)

Quando o operador cria um item recorrente ou parcelado, o sistema pré-gera todas as ocorrências previstas:

| Tipo | Pré-geração |
|---|---|
| Recorrente | Da `mes_referencia` inicial até o `data_fim` do contrato (uma linha por período conforme `periodicidade`) |
| Parcelado | Da `mes_referencia` inicial pelo número de parcelas (uma linha por parcela) |
| Avulso | Uma linha apenas, no `mes_referencia` informado |

A primeira ocorrência tem `parent_item_id=NULL`. As demais apontam pra ela. Exemplo: aluguel recorrente mensal em contrato de 30 meses gera 30 linhas; a primeira é a "pai" e as 29 seguintes referenciam ela via `parent_item_id`.

## 2.6. Edição em três modos

Quando o operador edita um item, o sistema pergunta o escopo da mudança:

### Editar somente esta ocorrência

```sql
UPDATE itens_cobranca SET ... WHERE id = ?
```

Ajuste pontual de uma ocorrência específica. Útil para correções localizadas (ex: condomínio em janeiro foi maior por taxa extra pontual).

### Editar esta e as futuras

```sql
UPDATE itens_cobranca SET ...
WHERE (id = ? OR parent_item_id = ?)
  AND status = 'pendente'
  AND mes_referencia >= ?
```

Mudança a partir da ocorrência editada. Itens passados (conciliados) ficam intocados. Útil para reajuste anual, mudança de valor por aditivo, troca de responsável.

### Editar todas

```sql
UPDATE itens_cobranca SET ...
WHERE (id = ? OR parent_item_id = ?)
  AND status = 'pendente'
```

Aplica a todas as ocorrências futuras pendentes. Útil para correção do erro original (descrição errada, pagante errado desde a criação).

### Restrições

- Itens conciliados nunca são afetados em qualquer modo.
- Itens cancelados nunca são afetados.

## 2.7. Migração de dados

Cenário em produção: 1 contrato, zero faturas geradas, zero itens extras. Drop-and-recreate sem necessidade de backfill.

### Sequência de migrations

1. Criar tabela `itens_cobranca`.
2. Renomear `administradoras` → `entidades_externas`; adicionar coluna `tipo`.
3. Renomear FK `condominios.administradora_id` → `condominios.entidade_externa_id`.
4. Renomear tabela `cobrancas` → `faturas`.
5. Remover as 5 colunas fixas de `faturas`.
6. Drop tabela `cobranca_itens_extras`.
7. Drop tabela `contrato_responsabilidades`.

## 2.8. Impacto no código

**Models:**
- Criar: `ItemCobranca`, `EntidadeExterna`, `Fatura`.
- Atualizar: `Contrato` (relação `responsabilidades` → `itensCobranca`), `Imovel`/`Condominio` (FK renomeada).
- Deletar: `Administradora`, `CobrancaItemExtra`, `Cobranca`, `ContratoResponsabilidade`.

**Services:**
- Criar `ItemCobrancaService` (criação, pré-geração, edição em 3 modos).
- Criar `FaturaService` (substitui `CobrancaService` — geração mensal vira "fechar fatura").

**Controllers:**
- Criar `ItemCobrancaController`, `FaturaController`, `EntidadeExternaController`.
- Deletar `CobrancaController`, `CobrancaItemExtraController`.

**Form Requests:**
- Criar `StoreItemCobrancaRequest`, `UpdateItemCobrancaRequest` (com escopo de edição todas/só esta/esta e futuras).

**Routes:**
- Atualizar `routes/web.php`.
- Rodar `php artisan wayfinder:generate` (regenerar TypeScript actions).

**Frontend:**
- Renomear `resources/js/pages/financeiro/cobrancas/` → `resources/js/pages/financeiro/faturas/`.
- Substituir `gerenciador-itens-extras.tsx` por `gerenciador-itens-cobranca.tsx`.
- Reescrever telas de criação e detalhes (lista única de itens, sem 5 seções fixas).
- Atualizar componentes que dependiam da estrutura antiga.

**Templates de e-mail:**
- Atualizar variáveis ("cobrança" → "fatura").

**Tests:**
- Atualizar `CobrancaControllerTest`, `CobrancaServiceTest`, `CobrancaFactory`, `CobrancaItemExtraFactory`, `FinanceiroSeeder`.

## 2.9. Bugs descobertos durante a análise (resolvidos pela Frente 1)

A análise prévia do `CobrancaService` identificou dois bugs reais. Ambos são resolvidos automaticamente pela nova modelagem:

- **Bug A — `periodicidade=avulso` tratada como mensal recorrente.** Resolvido: avulso é `tipo` próprio, sem periodicidade.
- **Bug B — Responsabilidades do proprietário não descontavam do repasse.** Resolvido: o modelo unificado trata `recebedor=proprietario` com `pagante=outro` automaticamente como abatimento via item de cobrança com valor negativo ou ajuste explícito.

## 2.10. Auditoria

Os campos `criado_por_user_id` e `atualizado_por_user_id` ficam diretamente em `itens_cobranca`, capturando quem criou e quem editou pela última vez (info básica). O histórico fino de cada alteração (de/para por campo, datado e atribuído) é coberto pela Frente 3 via `spatie/laravel-activitylog`.

## 2.11. Comprovantes unificados

A modelagem atual tem duas tabelas separadas com mesma estrutura (`cobranca_comprovantes`, `repasse_comprovantes`). Com a Frente 1 surge um terceiro caso: comprovantes de pagamento da administradora a entidades externas, em itens com intermediação. Em vez de criar uma terceira tabela, unificar tudo em uma única estrutura polimórfica.

### Tabela `comprovantes`

| Campo | Tipo | Descrição |
|---|---|---|
| id | bigint PK | Identificador único |
| tenant_id | FK tenants | Isolamento multi-tenant |
| owner_type | string | Classe Eloquent dona: `App\Models\Fatura`, `App\Models\Repasse`, `App\Models\ItemCobranca` |
| owner_id | bigint | ID do registro dono |
| tipo | enum | pagamento_pix \| pagamento_boleto \| transferencia \| recibo \| nota_fiscal \| outro |
| arquivo | string(500) | Path no storage |
| nome_original | string(255) | Nome do arquivo no upload |
| mime_type | string(100) | image/png, application/pdf, etc. |
| tamanho_bytes | bigint | Para validação de quota |
| valor | decimal(10,2) nullable | Quando o comprovante refere-se a valor específico |
| data_referencia | date nullable | Data do pagamento comprovado |
| observacoes | text nullable | Notas |
| enviado_por_user_id | FK users | Quem fez upload |
| enviado_por_papel | enum | admin \| proprietario \| inquilino — útil para fluxo de revisão |
| timestamps | — | created_at, updated_at |

### Vantagens

- Componente único de upload no frontend (`UploadComprovante`).
- Controller único (`ComprovanteController`).
- Modelos `Fatura`, `Repasse`, `ItemCobranca` ganham relação `morphMany('comprovantes')`.
- Lógica de validação (mime types, tamanho, permissões) centralizada.

### Casos de uso cobertos

- Inquilino envia comprovante de pagamento de fatura: `owner=Fatura, enviado_por_papel=inquilino, tipo=pagamento_pix`.
- Admin anexa comprovante ao confirmar repasse: `owner=Repasse, enviado_por_papel=admin, tipo=transferencia`.
- Admin anexa comprovante de pagamento a entidade externa (síndico, prefeitura): `owner=ItemCobranca, enviado_por_papel=admin, tipo=pagamento_pix`.

### Migração

1. Criar tabela `comprovantes` com estrutura polimórfica.
2. Backfill: copiar registros de `cobranca_comprovantes` (com `owner_type=App\Models\Fatura`) e `repasse_comprovantes` (com `owner_type=App\Models\Repasse`) para a nova tabela.
3. Drop tabelas `cobranca_comprovantes` e `repasse_comprovantes`.
4. Atualizar models, controllers, form requests, frontend.

---

# 3. Frente 2 — Snapshot de percentuais aplicados

## 3.1. Problema atual

As tabelas `cobrancas` e `repasses` guardam valores monetários calculados, mas não os percentuais aplicados:
- `cobrancas.valor_multa` existe, mas não há `multa_atraso_pct_aplicada`.
- `repasses.taxa_administracao_valor` existe, mas não há `taxa_administracao_pct_aplicada`.
- `repasses.valor_aluguel_bruto` reflete percentual da titularidade, mas o percentual em si não fica registrado.

Consequência: se o contrato muda a `taxa_administracao_pct` ou se a `titularidade.percentual` é alterado, a cobrança/repasse antigo continua com os valores corretos, mas o percentual aplicado só pode ser deduzido por divisão. Cálculo reverso é frágil em casos de arredondamento, valor parcial, ou ajustes manuais.

## 3.2. Campos a adicionar

### Em `cobrancas`

| Campo | Tipo | Descrição |
|---|---|---|
| multa_atraso_pct_aplicada | decimal(5,2) nullable | Percentual de multa vigente no momento da baixa |
| juros_atraso_pct_dia_aplicada | decimal(5,4) nullable | Percentual de juros diário vigente no momento da baixa |
| desconto_pontualidade_pct_aplicada | decimal(5,2) nullable | Percentual de desconto vigente quando aplicável |
| dias_carencia_aplicada | unsignedTinyInt nullable | Dias de carência vigentes no momento do cálculo |
| gerada_por_user_id | FK users nullable | Operador que gerou (null se geração automática) |
| baixada_por_user_id | FK users nullable | Operador que registrou o pagamento |

### Em `repasses`

| Campo | Tipo | Descrição |
|---|---|---|
| taxa_administracao_pct_aplicada | decimal(5,2) nullable | Percentual de admin vigente na geração do repasse |
| taxa_seguro_inadimplencia_pct_aplicada | decimal(5,2) nullable | Percentual de seguro inadimplência aplicado |
| percentual_titularidade_aplicado | decimal(5,2) nullable | Percentual da titularidade naquele momento |
| gerado_por_user_id | FK users nullable | Operador ou null se job automático |
| realizado_por_user_id | FK users nullable | Operador que confirmou o repasse |

## 3.3. Comportamento

- Geração nova preenche todos os campos.
- Registros antigos ficam null (aceitável — é dado retroativo, não há base para preencher).
- Telas de detalhe exibem o percentual aplicado quando disponível, com fallback para o percentual atual do contrato (com indicação visual de que é estimado).

---

# 4. Frente 3 — Histórico e Auditoria

Esta frente combina três camadas complementares: campos de autoria nas próprias tabelas de domínio, log fino de mudanças via `spatie/laravel-activitylog`, e tabelas dedicadas para mudanças críticas do contrato que exigem queryabilidade estruturada.

## 4.1. Princípio das três camadas

| Camada | Mecanismo | Cobre |
|---|---|---|
| 1 — Autoria direta | Colunas `criado_por_user_id` e `atualizado_por_user_id` nas tabelas de domínio | Quem criou e quem editou pela última vez |
| 2 — Log genérico | `spatie/laravel-activitylog` | Histórico fino (de/para por campo) na maior parte do sistema |
| 3 — Tabelas estruturadas | `contrato_reajustes`, `contrato_alteracoes` | Mudanças críticas do contrato com queries estruturadas e referência em relatórios |

## 4.2. Camada 1 — Autoria direta

Todas as tabelas que registram ação humana relevante recebem duas colunas:

| Campo | Tipo | Descrição |
|---|---|---|
| criado_por_user_id | FK users | Quem criou o registro |
| atualizado_por_user_id | FK users nullable | Quem fez a última edição |

### Tabelas afetadas

- `itens_cobranca` (já previsto na Frente 1)
- `faturas`
- `entidades_externas`
- `repasses` (também tratado na Frente 2)
- `garantias`, `fiadores`
- `contratos`, `imoveis`
- `vinculos`, `titularidades`

`tenants` e `users` ficam fora — mudanças nessas tabelas pertencem à administração da plataforma e seguem fluxo próprio.

### Implementação

- Trait `BelongsToCreator` (ou similar) com observer que preenche `criado_por_user_id` no `creating` e `atualizado_por_user_id` no `updating`.
- Aplicar trait nos models afetados.
- Migration única adicionando as duas colunas em todas as tabelas listadas.

## 4.3. Camada 2 — Log genérico (`spatie/laravel-activitylog`)

### Models alvo

| Model | Campos rastreados |
|---|---|
| `Contrato` | Todos exceto os cobertos pela Camada 3: `multa_atraso_pct`, `juros_atraso_pct_dia`, `dias_carencia`, `multa_rescisoria_pct`, `desconto_pontualidade_pct`, `indice_reajuste`, `mes_reajuste`, `dia_vencimento`, `tipo_garantia`, `observacoes` |
| `Imovel` | Todos os campos relevantes |
| `Garantia`, `Fiador` | Todos |
| `Vinculo` | `status`, `papel` |
| `Titularidade` | `percentual`, `responsavel_id` |
| `User` | `name`, `email`, `telefone`, `tipo_pessoa`, `documento` (relevante para LGPD) |
| `EntidadeExterna` | Todos |
| `EmailTemplate` | `corpo_html`, `corpo_texto`, `variaveis_disponiveis` |
| `ItemCobranca` | Todos os campos editáveis (descricao, pagante, recebedor, valor_unitario, etc.) |

### Sem auditoria

- `tenants` (mudanças raras, fluxo de administração da plataforma)
- Tabelas append-only (`imovel_fotos`, `cobranca_comprovantes`, `repasse_comprovantes`, `email_logs`)
- Tabelas técnicas Laravel (`migrations`, `cache`, `sessions`, `jobs`, `failed_jobs`)

### Implementação

- `composer require spatie/laravel-activitylog`
- Publicar e rodar migration do pacote.
- Configurar trait `LogsActivity` em cada model alvo.
- Definir `getActivitylogOptions()` com lista explícita de campos rastreados.
- Causer (autor) capturado automaticamente via `auth()->user()`.

### Política de retenção

Tabela `activity_log` cresce sem limite. Política recomendada: retenção de 5 anos (alinhada com LGPD e prazos contratuais brasileiros). Purge mensal via comando agendado removendo registros mais antigos.

## 4.4. Camada 3 — Tabelas estruturadas dedicadas

Para mudanças críticas do contrato que afetam diretamente cálculos financeiros e podem ser referenciadas em relatórios, disputas legais ou prestação de contas. Não delegamos ao `activitylog` porque queremos queries estruturadas (filtrar reajustes por índice, somar taxa de admin média, etc.).

### Tabela `contrato_reajustes`

Para mudanças no `valor_aluguel` do contrato (reajustes anuais, aditivos, renegociações, correções).

| Campo | Tipo | Descrição |
|---|---|---|
| id | bigint PK | Identificador único |
| tenant_id | FK tenants | Isolamento multi-tenant |
| contrato_id | FK contratos | Contrato afetado |
| alterado_por_user_id | FK users nullable | Operador (null se job automático) |
| alterado_em | timestamp | Quando o registro foi criado |
| data_aplicacao | date | A partir de quando o novo valor passa a valer |
| valor_anterior | decimal(10,2) | Valor antes do reajuste |
| valor_novo | decimal(10,2) | Valor após o reajuste |
| percentual | decimal(7,4) | Percentual aplicado |
| indice_usado | enum | igpm, ipca, fixo, manual |
| origem | enum | reajuste_anual, aditivo, renegociacao, correcao |
| observacao | text nullable | Justificativa ou contexto |

### Tabela `contrato_alteracoes`

Tabela única para mudanças em outros campos críticos do contrato.

| Campo | Tipo | Descrição |
|---|---|---|
| id | bigint PK | Identificador único |
| tenant_id | FK tenants | Isolamento multi-tenant |
| contrato_id | FK contratos | Contrato afetado |
| alterado_por_user_id | FK users nullable | Operador |
| alterado_em | timestamp | Quando aconteceu |
| campo | string(50) | Nome do campo: `taxa_administracao_pct`, `modelo_repasse`, `status`, `data_fim` |
| valor_anterior | json | Valor antes |
| valor_novo | json | Valor depois |
| data_efetiva | date | A partir de quando passa a valer |
| motivo | text nullable | Justificativa |

### Sobre `itens_cobranca`

Não há tabela `responsabilidade_alteracoes` ou equivalente. O versionamento natural via `parent_item_id` + a regra de imutabilidade dos conciliados (Seção 1.5, Princípio 4) já cobre o histórico. Mudanças em itens pendentes são rastreadas pelo `activitylog`.

### Implementação

- Tabelas estruturadas: via Eloquent Observer no `Contrato`. Observer detecta mudanças em campos específicos no `updating` e cria registro antes do save.
- Reajustes: fluxo dedicado via `ContratoReajusteService`. Não confiar no observer — a operação tem semântica de negócio explícita (data de aplicação no futuro, índice, origem).

## 4.5. UI de visualização

Tela de detalhes do contrato exibe timeline com:
- Reajustes (de `contrato_reajustes`).
- Alterações críticas (de `contrato_alteracoes`).
- Outras mudanças (de `activity_log`).

Filtros por tipo de evento e período.

### Visibilidade por papel

| Papel | Acesso à timeline |
|---|---|
| Admin do tenant | Completa, todos os contratos do tenant |
| Proprietário | Filtrada — apenas eventos dos contratos dos seus imóveis |
| Inquilino | Sem acesso |

## 4.6. Integridade dos registros de auditoria

- Sem soft delete em tabelas de auditoria.
- Sem rotas de update/delete expostas.
- Toda alteração que cria registro de histórico ocorre dentro da mesma transação de banco da operação principal.
- Jobs e seeders nunca pulam o registro — usam a mesma camada de service.

---

# 5. Plano de implementação ordenado

| Ordem | Item | Frente | Esforço | Risco | Dependências |
|---|---|---|---|---|---|
| 1 | Renomear `administradoras` → `entidades_externas` + adicionar coluna `tipo` | 1 | Baixo | Baixo | — |
| 2 | Criar tabela `itens_cobranca` (estrutura, FKs, índices) | 1 | Médio | Baixo | — |
| 3 | Renomear `cobrancas` → `faturas` + remover 5 colunas fixas + ajustar `valor_total` denormalizado | 1 | Médio | Médio | Itens 1, 2 |
| 4 | Drop `cobranca_itens_extras` e `contrato_responsabilidades` | 1 | Baixo | Baixo | Item 3 |
| 5 | `ItemCobrancaService` — criação, pré-geração de ocorrências, edição em 3 modos | 1 | Alto | Médio | Itens 2, 3 |
| 6 | `FaturaService` — fechamento mensal (substitui `CobrancaService`) | 1 | Alto | Médio | Item 5 |
| 7 | Unificar comprovantes: criar tabela `comprovantes` polimórfica + backfill + drop `cobranca_comprovantes` e `repasse_comprovantes` | 1 | Médio | Baixo | Item 6 |
| 8 | Reescrita do frontend de cobranças → faturas (criar, listar, mostrar, gerenciar itens, upload de comprovantes) | 1 | Alto | Médio | Itens 6, 7 |
| 9 | Atualização de templates de e-mail e notificações | 1 | Baixo | Baixo | Item 6 |
| 10 | Snapshot de percentuais em `faturas` e `repasses` | 2 | Baixo | Baixo | Item 6 |
| 11 | Trait `BelongsToCreator` + colunas `criado_por_user_id`/`atualizado_por_user_id` em todas as tabelas alvo | 3 | Baixo | Baixíssimo | — |
| 12 | Instalação `spatie/laravel-activitylog` + configuração nos models (incluindo `EmailTemplate`) | 3 | Baixo | Baixíssimo | — |
| 13 | Tabela `contrato_reajustes` + `ContratoReajusteService` + UI de reajuste | 3 | Médio | Médio | Item 10 |
| 14 | Tabela `contrato_alteracoes` + observer no `Contrato` | 3 | Médio | Baixo | Item 10 |
| 15 | UI de timeline de auditoria no detalhe do contrato (admin + proprietário com filtro) | 3 | Médio | Baixo | Itens 12, 13, 14 |
| 16 | Comando agendado de purge do `activity_log` (retenção 5 anos) | 3 | Baixo | Baixíssimo | Item 12 |

### Caminhos paralelos

- **Caminho crítico**: itens 1 → 2 → 3 → 5 → 6 → 8 (Frente 1). Domina o cronograma.
- **Frente 3** (itens 11, 12) pode começar em paralelo com a Frente 1 desde o início.
- **Frente 2** (item 10) e itens 13, 14, 15 só fazem sentido após item 6.

---

# 6. Decisões pendentes para fase posterior

As decisões de modelagem, auditoria, comprovantes e visibilidade foram fechadas e incorporadas no documento. O único tema que permanece em aberto — não bloqueia a Frente 1, será tratado depois — é a renovação de contratos.

## 6.1. Renovação de contratos

Contratos de locação por longa temporada normalmente renovam (típico: 30 meses + renovação por mais 30, podendo se estender por anos). **Decisão fechada:** abordagem **B — novo contrato com link**. Ao renovar, cria-se um contrato novo com FK `contrato_anterior_id` apontando para o anterior. Histórico fica preservado por encadeamento.

Mas há sub-decisões pendentes para a fase de implementação dessa funcionalidade:

- **Renovação automática ou manual?** O sistema sugere/avisa proximidade do `data_fim` e o operador decide, ou pode haver renovação automática se configurado no contrato?
- **Itens parcelados em aberto.** Quando um contrato termina no 6º mês de um IPTU 12x (parcelas pré-geradas até o 12º), o que acontece com as 6 parcelas restantes?
  - (a) Migram automaticamente para o novo contrato preservando o `parent_item_id` original.
  - (b) Ficam vinculadas ao contrato antigo (novo contrato não vê).
  - (c) Operador escolhe na hora da renovação.
- **Reajuste no momento da renovação.** Aplicar índice acumulado, valor renegociado manualmente, ou reajuste calculado conforme cláusula contratual?
- **Snapshot do contrato anterior.** Ao renovar, alguma informação volátil precisa ser "fotografada" para preservar contexto histórico?
- **Multa rescisória em caso de quebra durante o contrato renovado.** Calcula sobre o saldo do contrato vigente ou desde o original?
- **Itens recorrentes pré-gerados além de `data_fim`.** Quando o contrato é renovado, o sistema simplesmente estende a série existente (criando novas ocorrências para o período renovado) ou encerra a série e cria nova série vinculada?

Esta funcionalidade não bloqueia a Frente 1. Implementar após o modelo unificado estabilizar.

---

# 7. Princípios para a equipe de implementação

- Toda alteração que cria registro de histórico deve estar na mesma transação de banco da operação principal.
- Nenhum job ou seeder pula o registro de autoria ou auditoria — usa a mesma camada de service.
- Todas as colunas novas têm `->comment()` em português, seguindo o padrão do escopo original.
- Migrations da Frente 1 alteram tabelas com impacto em UI, API e templates de e-mail — requerem revisão coordenada.
- Itens com `status='conciliado'` em `itens_cobranca` são imutáveis. Tentativas de edição direta devem falhar via Form Request ou Policy.
- Pré-geração de ocorrências (Seção 2.5) deve estar dentro de transação atômica — falha em uma ocorrência reverte todas.
- Edição em três modos (Seção 2.6) passa pelo `ItemCobrancaService` via método explícito (`atualizarOcorrencia`, `atualizarEstaEFuturas`, `atualizarTodas`). Não permitir bypass via Eloquent direto.
- Renomeações de tabela exigem regeneração do Wayfinder (`php artisan wayfinder:generate`) e atualização das referências TypeScript em `resources/js/actions/`.
- Testes Pest cobrindo cada cenário canônico da Seção 1.2 (aluguel, taxa de admin, condomínio com intermediário, IPTU 12x, chuveiro, frete, cota extra) são obrigatórios antes de mesclar a Frente 1.
