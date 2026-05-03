





KIMOBE
GESTÃO INTELIGENTE DE IMÓVEIS

Documento de Escopo Completo do Projeto




Versão 1.0 — Maio 2026
Desenvolvido por Kicol
 
1. Visão Geral do Projeto
1.1. O que é o Kimobe
O Kimobe é uma plataforma SaaS (Software como Serviço) de gestão de aluguéis de imóveis por longa temporada, desenvolvida para o mercado brasileiro. A plataforma atende imobiliárias que administram imóveis de terceiros e proprietários que gerenciam seus próprios imóveis de aluguel.
O nome Kimobe é uma fusão de "Kicol" (a empresa desenvolvedora) com "Imobiliária" — um significado interno que não é comunicado publicamente.
O sistema centraliza todo o ciclo operacional da administração de aluguéis: cadastro de imóveis, contratos de locação, geração de cobranças, registro de pagamentos, cálculo e confirmação de repasses aos proprietários, e comunicação com todas as partes envolvidas (administrador, proprietário e inquilino).
1.2. Problema que resolve
Imobiliárias e proprietários de múltiplos imóveis no Brasil ainda gerenciam aluguéis com planilhas, cadernos e sistemas desconectados. O Kimobe oferece uma solução unificada, acessível e acessível financeiramente (a partir de R$ 49,90/mês) que elimina retrabalho, reduz erros de cálculo, e dá visibilidade em tempo real para todas as partes envolvidas na locação.
1.3. Público-alvo
•	Imobiliárias: empresas que administram imóveis de terceiros, cobrando taxa de administração. De pequenas (3-10 imóveis) a grandes (200+ imóveis).
•	Proprietários diretos: pessoas físicas que possuem múltiplos imóveis de aluguel e os gerenciam sem intermediário.
•	Proprietários de imóveis: donos que delegam a administração a imobiliárias mas desejam acompanhar rendimentos e repasses.
•	Inquilinos: locatários que precisam acessar cobranças, contratos e enviar comprovantes de pagamento.
1.4. Modelo de negócio
O Kimobe cobra uma assinatura mensal fixa baseada em faixas de imóveis cadastrados:
Plano	Limite de imóveis	Valor mensal
Starter	Até 15 imóveis	R$ 49,90
Profissional	Até 50 imóveis	R$ 129,90
Business	Até 200 imóveis	R$ 349,90
Enterprise	Ilimitado	R$ 899,90

O modelo é baseado no conceito de R$ 3,00 por imóvel, organizado em faixas de preço fixo para simplicidade e previsibilidade. Todos os planos incluem as mesmas funcionalidades — a diferença é apenas o limite de imóveis.
Assinantes parceiros podem receber cortesia (isenção de cobrança) por decisão do super admin, com registro do motivo para auditoria.
 
2. Stack Tecnológica
2.1. Tecnologias principais
Camada	Tecnologia	Versão
Backend	Laravel	13
Frontend	React	19
Bridge	Inertia.js	2
Linguagem frontend	TypeScript	—
CSS	Tailwind CSS	4
Componentes UI	shadcn/ui	—
Banco de dados	MySQL	8+

2.2. Infraestrutura
•	Servidor: Hostinger (srv738235.hstgr.cloud), Apache + PHP 8.4 FPM
•	DNS: Cloudflare para kimobe.com.br com CNAME flattening apontando para server1.kcl.srv.br (217.196.63.163)
•	SSL: Cloudflare Full mode + Let's Encrypt no servidor
•	Repositório: GitHub — emmacedo/kimobe.com.br
•	CI: GitHub Actions (lint.yml — workflow_dispatch)
2.3. Decisões arquiteturais
•	Multi-tenancy: Banco compartilhado com isolamento por tenant_id em todas as tabelas de negócio. Identificação do tenant via sessão (não subdomínio).
•	Autenticação: Laravel Fortify com 2FA (OTP + recovery codes). Tabela users global com vínculos por tenant.
•	Super admin: Guard separado (admin) com tabela admin_users própria. Rotas /admin/* completamente isoladas.
•	Papéis: admin, proprietário, inquilino — definidos na tabela vinculos como relação N:N entre users e tenants.
•	Emails: Templates editáveis no banco, envio assíncrono via queue/job, pixel de rastreamento de abertura.
 
3. Arquitetura de Identidade e Acesso
3.1. Modelo de identidade
A identidade do usuário é global — um CPF/email existe uma única vez no sistema. Os papéis são contextuais, definidos por vínculo entre o usuário e cada tenant (assinante).
Isso permite cenários como: Eduardo é proprietário na Imobiliária Carioca (RJ) e inquilino na Imobiliária Paulista (SP). Ao fazer login, ele seleciona qual contexto acessar e pode trocar sem fazer logout.
3.2. Estrutura de dados de acesso
Tabela users (global)
Armazena credenciais e dados pessoais. Uma entrada por pessoa física, independente de quantos tenants participa.
Campo	Tipo	Descrição
id	bigint PK	Identificador único
nome	string	Nome completo
email	string unique	Email de acesso (login)
cpf	string unique	CPF do usuário
telefone	string	Telefone de contato
senha	string hash	Senha criptografada (bcrypt)
2FA	campos Fortify	Suporte a autenticação em dois fatores

Tabela tenants
Cada assinante do Kimobe — imobiliária ou proprietário direto.
Campo	Tipo	Descrição
id	bigint PK	Identificador único
nome	string	Nome fantasia ou razão social
tipo	enum	imobiliaria | proprietario_direto
documento	string unique	CNPJ ou CPF
plano_id	FK → planos	Plano de assinatura atual
status	enum	ativo | suspenso | bloqueado | cancelado
cortesia	boolean	Isento de cobrança (parceiro)
bloqueado_em	datetime	Data/hora do bloqueio por inadimplência
Endereço	campos	cep, logradouro, numero, complemento, bairro, cidade, uf
Contato	campos	email_contato, telefone_comercial, whatsapp, site

Tabela vinculos
Relação N:N entre users e tenants com papel definido. Um usuário pode ter múltiplos vínculos em múltiplos tenants.
Campo	Tipo	Descrição
id	bigint PK	Identificador único
user_id	FK → users	Usuário vinculado
tenant_id	FK → tenants	Tenant ao qual está vinculado
papel	enum	admin | proprietario | inquilino
status	enum	ativo | inativo | pendente

Unique composto: [user_id, tenant_id, papel] — permite que o mesmo usuário tenha papéis diferentes no mesmo tenant (ex: Carlos é admin e proprietário ao mesmo tempo), mas impede duplicatas.
3.3. Cenários de acesso
Cenário 1: Imobiliária padrão
A Imobiliária Horizonte é uma empresa que administra imóveis de terceiros. O administrador Marcelo gerencia tudo. Ana Costa é proprietária de 3 imóveis. Pedro Lima é inquilino de um apartamento. Cada um faz login com seu email e vê apenas o que é relevante para seu papel.
Cenário 2: Proprietário direto
Carlos Mendes possui 5 imóveis e os administra sozinho, sem imobiliária. Ele assina o Kimobe como 'proprietário direto' e recebe automaticamente dois vínculos: admin (para gerenciar) e proprietário (para acompanhar rendimentos). Seu painel combina as duas visões.
Cenário 3: Cross-tenant
Eduardo tem um apartamento no Rio de Janeiro administrado pela Imobiliária Horizonte (papel: proprietário). Ele se mudou para São Paulo e aluga um imóvel cuja administração é feita pela Imobiliária Paulista (papel: inquilino). Ao fazer login, Eduardo escolhe qual contexto acessar. Na Horizonte vê seus rendimentos; na Paulista vê suas cobranças e contratos.
Cenário 4: Copropriedade (herança)
Um apartamento herdado por três irmãos: Ana (50%, responsável), João (25%, observador) e Maria (25%, observador). Ana toma decisões e assina documentos. João e Maria acompanham rendimentos e recebem sua parte do aluguel. O sistema faz o split automático dos repasses conforme os percentuais definidos.
3.4. Fluxo pós-login
•	Usuário faz login via Fortify
•	Middleware verifica vínculos ativos no sistema
•	Se 0 vínculos → tela 'Sem acesso'
•	Se vínculos em 1 tenant → auto-seleção, vai direto pro dashboard
•	Se vínculos em 2+ tenants → tela de seleção de contexto
•	Após seleção, tenant_id salvo na sessão → todas as queries filtram por tenant_id
•	Usuário pode trocar de contexto a qualquer momento sem logout
3.5. Permissões por papel
Funcionalidade	Admin	Proprietário	Inquilino
Ver dashboard	Completo	Seus rendimentos	Suas cobranças
Gerenciar imóveis	CRUD completo	Ver os seus (read-only)	Não acessa
Gerenciar contratos	CRUD completo	Ver dos seus imóveis	Ver os seus (read-only)
Gerenciar cobranças	CRUD + pagamento	Ver dos seus imóveis	Ver as suas + enviar comprovante
Gerenciar repasses	Confirmar + lote	Ver os seus	Não acessa
Dados bancários	Ver todos do tenant	Gerenciar os seus	Não acessa
Configurações empresa	Editar	Não vê	Não vê
Alterar plano	Sim	Não	Não
 
4. Módulo de Imóveis
4.1. Visão geral
O módulo de imóveis é a base do sistema. Todo contrato, cobrança e repasse está vinculado a um imóvel. O imóvel armazena localização, características físicas, fotos, e a titularidade (quem é dono e em qual proporção).
4.2. Cadastro do imóvel
Endereço com auto-preenchimento
O operador digita o CEP e o sistema consulta a API ViaCEP para preencher automaticamente logradouro, bairro, cidade e UF. Os campos permanecem editáveis para correções. Campos: cep, logradouro, numero, complemento, bairro, cidade, uf.
Tipos de imóvel
Apartamento, Casa, Sala comercial, Loja, Galpão. O tipo influencia quais campos de características são exibidos: casas não têm 'andar', salas e galpões não têm 'quartos' e 'suítes'.
Características
Quartos, suítes, banheiros, vagas de garagem, andar, área (m²) — todos nullable, preenchidos conforme o tipo do imóvel.
Status do imóvel
Status	Significado
Disponível	Sem contrato ativo, pode ser alugado
Alugado	Com contrato ativo vigente
Manutenção	Temporariamente indisponível
Inativo	Retirado da carteira de gestão

Valor de aluguel sugerido
Valor de referência para quando o imóvel está disponível. O valor real é definido no contrato. Serve para estimativas e anúncios.
4.3. Fotos do imóvel
Cada imóvel pode ter múltiplas fotos. A foto de ordem 1 é automaticamente a foto principal (destaque) — não existe campo 'destaque' separado, a posição define. O operador pode reordenar fotos via drag & drop.
•	Upload: JPG, PNG ou WebP, máximo 5MB por foto, múltiplos arquivos de uma vez.
•	Galeria: Grid de thumbnails com drag & drop para reordenar, legenda editável, exclusão com confirmação.
•	Lightbox: Ao clicar na foto, abre visualização em tamanho grande com navegação prev/next.
•	Storage: Disco público do Laravel, path: imoveis/{id}/{nome_unico}.{ext}
4.4. Titularidade (proprietários do imóvel)
A titularidade define quem é dono do imóvel e em qual proporção. Um imóvel pode ter um ou mais titulares, e a soma dos percentuais deve ser 100%.
Tipos de titular
•	Pessoa física: Proprietário individual (CPF)
•	Empresa: Pessoa jurídica dona do imóvel (CNPJ)
•	Inventário: Espólio em processo de inventário, com inventariante como responsável
Papéis na titularidade
•	Responsável: Toma decisões, assina documentos, aprova manutenções. Normalmente há apenas um por imóvel.
•	Observador: Acompanha rendimentos e recebe sua parte do repasse, mas não toma ações.
Split financeiro
Quando o inquilino paga o aluguel, o sistema gera automaticamente repasses separados para cada titular, conforme seu percentual. Exemplo: aluguel de R$ 2.000, Ana (50%) recebe R$ 1.000, João (25%) recebe R$ 500, Maria (25%) recebe R$ 500 — já descontadas as taxas.
Dados bancários
Cada titular cadastra suas contas bancárias (banco, agência, conta, tipo, PIX). Na titularidade, define qual conta usar para o repasse daquele imóvel específico — um titular pode receber repasses de imóveis diferentes em contas diferentes.
4.5. Validação de limite do plano
Ao tentar cadastrar um novo imóvel, o sistema verifica se o tenant atingiu o limite do plano contratado. Se atingiu, o cadastro é bloqueado com mensagem: 'Limite do plano atingido. Faça upgrade para cadastrar mais imóveis.'
 
5. Módulo de Contratos
5.1. Visão geral
O contrato de locação é o documento central que conecta imóvel, inquilino e proprietário. Ele define valores, vigência, modelo de repasse, garantias, responsabilidades financeiras e regras de multa e reajuste.
5.2. Dados do contrato
•	Imóvel: Selecionado entre imóveis com status 'disponível'. Ao criar o contrato, o imóvel muda automaticamente para 'alugado'.
•	Inquilino: Vínculo com papel 'inquilino' no tenant. Imóvel e inquilino são imutáveis após criação — se errou, cancela e cria novo.
•	Valor do aluguel: Valor mensal base. Pode ser pré-preenchido com o valor sugerido do imóvel.
•	Dia de vencimento: 1 a 28 (evita problemas com meses curtos).
•	Vigência: Data início e data fim. O sistema calcula a duração automaticamente.
•	Índice de reajuste: IGPM, IPCA ou fixo, com mês de aplicação definido.
5.3. Modelo de repasse
Cada contrato define como o proprietário recebe o aluguel. São dois modelos:
Por recebimento
O proprietário só recebe o repasse quando o inquilino efetivamente paga. Se o inquilino atrasa, o proprietário não recebe. A taxa de administração é menor porque o risco de inadimplência é do proprietário.
Garantido
O proprietário recebe o repasse na data fixa combinada, independente do inquilino ter pago ou não. A imobiliária assume o risco da inadimplência e cobra uma taxa adicional de seguro inadimplência. Os repasses são gerados junto com as cobranças, não na baixa do pagamento.
5.4. Taxas
•	Taxa de administração: Percentual cobrado pela imobiliária sobre o aluguel (ex: 10%). Descontada do repasse ao proprietário.
•	Taxa de seguro inadimplência: Percentual adicional, só no modelo garantido (ex: 4%). Cobre o risco da imobiliária.
No caso de proprietário direto (que administra sozinho), a taxa pode ser 0%.
5.5. Multas, juros e descontos
Campo	Default	Descrição
Multa por atraso	2%	Sobre o valor da cobrança quando o inquilino atrasa
Juros por dia	0,0333%	Equivale a 1% ao mês, calculado por dia de atraso
Dias de carência	0	Dias após vencimento antes de aplicar multa/juros
Multa rescisória	Variável	Sobre saldo restante em caso de rescisão antecipada
Desconto pontualidade	Variável	Concedido quando o inquilino paga até o vencimento

5.6. Responsabilidades financeiras
Cada contrato define quem paga o quê: IPTU, condomínio, seguro incêndio, taxa de bombeiros, taxa extra de condomínio, etc. Para cada item, define-se se o responsável é o proprietário ou o inquilino.
Itens pré-definidos sugeridos ao criar o contrato: IPTU, Condomínio, Taxa extra de condomínio, Seguro incêndio, Taxa dos Bombeiros. O operador pode aceitar, remover ou adicionar itens customizados.
Os itens cuja responsabilidade é do inquilino são incluídos na cobrança mensal. Os do proprietário são descontados do repasse.
5.7. Garantia locatícia
Cada contrato tem um tipo de garantia:
Tipo	Dados armazenados
Caução	Valor depositado (normalmente 1-3 meses de aluguel)
Fiador	Dados completos de 1-2 fiadores (nome, CPF, RG, profissão, estado civil, endereço, contato)
Seguro fiança	Seguradora, número da apólice, valor do prêmio, vigência
Título de capitalização	Número do título, valor, vigência
Sem garantia	Nenhum dado adicional (aviso ao operador)

5.8. Ações sobre contratos
•	Encerrar: Contrato chega ao fim natural. Status muda para 'encerrado', imóvel volta a 'disponível'.
•	Cancelar: Rescisão antecipada. Status muda para 'cancelado', imóvel volta a 'disponível'. Pode envolver multa rescisória.
Contratos nunca são excluídos do sistema — apenas encerrados ou cancelados. O histórico é sempre preservado.
 
6. Módulo Financeiro
6.1. Cobranças
Geração de cobranças
As cobranças podem ser geradas de duas formas:
•	Automática: O operador clica 'Gerar cobranças do mês', seleciona a referência (MM/YYYY), vê um preview dos contratos que serão cobrados, e confirma. O sistema cria uma cobrança por contrato ativo que ainda não tem cobrança naquele mês.
•	Manual: O operador cria uma cobrança individual, selecionando o contrato e ajustando os valores conforme necessário.
Composição da cobrança
Cada cobrança é composta por campos fixos e itens extras:
•	Campos fixos: valor_aluguel, valor_condominio, valor_iptu, valor_seguro_incendio, valor_taxa_bombeiros, valor_taxa_extra_condominio — preenchidos automaticamente com base nas responsabilidades do contrato.
•	Itens extras: Tabela separada para itens que não se enquadram nos campos fixos (ex: 'Rateio de pintura do hall — R$ 75,00'). O operador adiciona manualmente.
•	Valor total: Soma de todos os campos fixos + itens extras. Recalculado automaticamente ao adicionar/remover itens.
Status da cobrança
Status	Significado
Pendente	Gerada, aguardando pagamento
Pago	Pagamento registrado com sucesso
Atrasado	Vencimento passou e não foi paga (marcado automaticamente por job diário)
Cancelado	Estornada ou anulada pelo operador

Registro de pagamento (baixa)
O operador registra o pagamento informando data, método (boleto, PIX, transferência, dinheiro) e valor recebido. O sistema calcula automaticamente:
•	Se pago antes/no vencimento: aplica desconto por pontualidade (se configurado no contrato)
•	Se pago após o vencimento + carência: aplica multa e juros proporcionais aos dias de atraso
•	O operador pode sobrescrever o valor calculado se o valor recebido foi diferente
Ao registrar pagamento em contrato com modelo 'por recebimento': o sistema gera automaticamente os repasses para cada titular do imóvel.
Comprovantes
A cobrança aceita múltiplos comprovantes de pagamento (PDF, JPG, PNG, WebP, máx 10MB). O inquilino pode enviar comprovantes pelo seu painel — isso NÃO registra o pagamento automaticamente, apenas notifica o admin.
6.2. Repasses
Geração de repasses
Os repasses são sempre gerados automaticamente, nunca manualmente:
•	Modelo por recebimento: Repasses criados no momento em que o pagamento da cobrança é registrado.
•	Modelo garantido: Repasses criados junto com a cobrança (na geração mensal), independente do pagamento do inquilino.
Cálculo do repasse
Para cada titular do imóvel, o sistema calcula:
•	Valor bruto = valor do aluguel × percentual do titular
•	Taxa de administração = valor bruto × taxa_administracao_pct do contrato
•	Taxa seguro inadimplência = valor bruto × taxa_seguro_inadimplencia_pct (só se modelo garantido)
•	Valor líquido = valor bruto − taxa administração − seguro inadimplência
Exemplo: Split com 3 titulares
Aluguel: R$ 1.900,00. Taxa admin: 10%. Modelo: por recebimento.
Titular	Percentual	Bruto	Taxa admin	Líquido
Ana Costa	50%	R$ 950,00	R$ 95,00	R$ 855,00
João Costa	25%	R$ 475,00	R$ 47,50	R$ 427,50
Maria Costa	25%	R$ 475,00	R$ 47,50	R$ 427,50

Confirmação de repasse
O admin confirma os repasses individualmente ou em lote. Na confirmação em lote, seleciona múltiplos repasses pendentes, revisa o total, e confirma todos de uma vez em uma única transação.
Dados bancários do titular aparecem no dialog de confirmação para facilitar a transferência.
Comprovantes de repasse
O admin pode anexar comprovante de transferência em cada repasse confirmado (mesmo componente reutilizável dos comprovantes de cobrança).
 
7. Painéis por Papel
7.1. Painel do Admin (Assinante)
O admin tem acesso total a todas as funcionalidades do tenant. Sua navegação inclui: Dashboard, Imóveis, Contratos, Financeiro (Cobranças e Repasses), Dados Bancários, Emails enviados, e Settings.
Dashboard do admin
•	Receita mensal: soma dos pagamentos recebidos no mês
•	Taxa de ocupação: imóveis alugados / total × 100
•	Inadimplência: cobranças atrasadas / total do mês × 100
•	Contratos ativos: quantidade
•	Tabela 'Últimas movimentações' com as 10 cobranças mais recentes
7.2. Painel do Proprietário
O proprietário vê apenas dados relacionados aos seus imóveis. A navbar mostra: Dashboard, Meus Imóveis, Meus Repasses, Cobranças, Dados Bancários. Todas as listagens são filtradas automaticamente.
Dashboard do proprietário
•	Receita do mês: soma dos repasses realizados no mês
•	Repasses pendentes: quantidade e valor
•	Meus imóveis: quantidade
•	Tabela 'Últimos repasses' com os 10 mais recentes
7.3. Painel do Inquilino
O inquilino vê apenas suas cobranças e contratos. A navbar mostra: Dashboard, Minhas Cobranças, Meus Contratos. Não acessa imóveis nem repasses.
Dashboard do inquilino
•	Próximo vencimento: valor e data da cobrança pendente mais próxima
•	Total pago no ano: soma dos pagamentos
•	Cobranças em dia: percentual de pontualidade
•	Tabela 'Minhas cobranças' com as 10 mais recentes
Envio de comprovante
O inquilino pode enviar comprovante de pagamento para cobranças pendentes ou atrasadas. Isso NÃO registra o pagamento — apenas notifica o admin. O campo uploaded_by_user_id garante que o inquilino só pode remover comprovantes que ele mesmo enviou.
Privacidade
O inquilino não vê: dados dos proprietários, percentuais de titularidade, repasses, dados bancários de terceiros, observações internas do admin. A garantia aparece simplificada (tipo apenas, sem dados do fiador).
7.4. Scoping de dados
O trait ScopesPorPapel centraliza toda a lógica de filtragem nos controllers. Cada método scope (scopeImoveisDoUsuario, scopeContratosDoUsuario, scopeCobrancasDoUsuario, scopeRepassesDoUsuario) verifica o papel e filtra a query adequadamente. O admin sempre vê tudo do tenant.
 
8. Módulo Super Admin
8.1. Visão geral
O super admin é o painel de gestão da plataforma Kimobe. Acessado em /admin com autenticação própria (tabela admin_users, guard separado). Layout com sidebar lateral em petróleo escuro (#073B45), visualmente distinto do painel do assinante que usa navbar horizontal.
O super admin não pertence a nenhum tenant — ele está acima de todos, gerenciando assinantes, planos, faturamento e métricas da plataforma.
8.2. Gestão de planos
CRUD de planos de assinatura. Cada plano define nome, limite de imóveis, valor mensal e status (ativo/inativo). Planos inativos não podem ser contratados por novos assinantes mas continuam válidos para os existentes. A interface usa cards lado a lado em vez de tabela.
8.3. Gestão de assinantes
Listagem de todos os tenants com filtros (status, plano, cortesia). Detalhes completos de cada assinante: dados cadastrais, plano com barra de uso visual, usuários vinculados, faturas, resumo financeiro.
Ações sobre assinantes
•	Alterar plano: Muda o plano do assinante. Se downgrade com imóveis acima do novo limite, permite mas o assinante não poderá cadastrar novos.
•	Cortesia: Marca assinante como parceiro isento de cobrança. Exige motivo para registro. Faturas não são geradas enquanto cortesia estiver ativa.
•	Suspender: Bloqueia acesso temporariamente (decisão do admin, não por inadimplência).
•	Reativar: Restaura acesso de assinante suspenso ou bloqueado.
•	Cancelar: Encerramento definitivo. Requer motivo. Irreversível.
•	Desbloquear: Restaura acesso de assinante bloqueado por inadimplência.
8.4. Faturamento
Geração mensal de faturas para cada tenant ativo e não-cortesia. O super admin seleciona o mês, vê o preview (quais tenants serão faturados, com plano e valor), e confirma. Faturas individuais podem ter pagamento registrado ou ser canceladas.
Registro de pagamento
Ao registrar pagamento de uma fatura, se o tenant estava bloqueado por inadimplência e não restam mais faturas atrasadas, o sistema desbloqueia automaticamente.
8.5. Sistema de bloqueio por inadimplência
Configurações (editáveis pelo super admin)
Configuração	Default	Descrição
Dias aviso antes	5	Dias antes do vencimento para enviar aviso por email
Aviso no dia	Sim	Enviar lembrete no dia do vencimento
Dias de graça	7	Dias após vencimento antes de bloquear
Dias aviso bloqueio	3	Dias após vencimento para avisar que bloqueio está próximo
Aviso ao bloquear	Sim	Enviar email informando o bloqueio
Dia vencimento	10	Dia fixo do mês para vencimento das faturas

Fluxo de inadimplência
•	Dia X antes do vencimento: email de aviso de cobrança
•	Dia do vencimento: email de lembrete (se configurado)
•	Após vencimento: fatura muda de 'pendente' para 'atrasado'
•	Dia Y após vencimento: email de aviso de bloqueio iminente
•	Dia Z (graça) após vencimento: acesso bloqueado + email de bloqueio (se configurado)
Banners in-app no painel do assinante
Quando o assinante está com pendência financeira, banners aparecem no topo do painel:
•	Nível 1 (amarelo): Fatura próxima do vencimento. Discreto, pode ser fechado.
•	Nível 2 (laranja): Fatura vencida, dentro do prazo de graça. Permanente.
•	Nível 3 (vermelho): Bloqueio iminente. Permanente, com pulsação sutil.
Tenants com cortesia nunca veem banners.
Tela de bloqueio
Quando o tenant é bloqueado, os usuários do tenant não conseguem acessar o painel. Veem uma tela dedicada com o motivo do bloqueio e instruções de contato para regularização. Se o usuário tem vínculos em outros tenants, pode acessar os que estão em dia.
8.6. Dashboard do super admin
•	Assinantes ativos: count com variação
•	Receita mensal: soma de faturas pagas no mês
•	Inadimplentes: count e valor em aberto
•	Imóveis na plataforma: total cross-tenant
•	Gráfico de receita dos últimos 6 meses (bar chart)
•	Tabela de assinantes recentes e faturas pendentes
•	Botão para executar verificação de inadimplência manualmente
8.7. Gestão de usuários da plataforma
Visão global de todos os users da plataforma com seus vínculos em cada tenant. Painel de visibilidade para suporte — o super admin encontra qualquer pessoa e entende em quais tenants ela participa. Read-only: a gestão de vínculos é do admin de cada tenant.
8.8. Mensagens de contato
Mensagens enviadas pelo formulário de contato do site público ficam armazenadas na tabela mensagens_contato. O super admin vê, marca como lida e marca como respondida. Badge de contagem de não-lidas na sidebar.
8.9. Páginas institucionais
Termos de uso e política de privacidade armazenados no banco e editáveis pelo super admin via editor WYSIWYG. Páginas públicas acessíveis em /termos-de-uso e /politica-de-privacidade.
 
9. Site Público
9.1. Visão geral
O site público é a vitrine do Kimobe — onde novos clientes conhecem o produto e decidem assinar. Fica dentro do mesmo projeto Laravel, em rotas públicas sem autenticação. Layout próprio com navbar horizontal e footer.
9.2. Landing page (/)
Seções da landing page, em ordem:
•	Hero: Fundo petróleo, título grande, subtítulo, 2 CTAs (Comece agora + Ver planos), mockup do dashboard.
•	Funcionalidades: 6 cards com ícone — gestão de imóveis, contratos completos, cobranças automáticas, repasses inteligentes, painel do proprietário, painel do inquilino.
•	Como funciona: 3 passos — crie sua conta, cadastre imóveis, gerencie tudo.
•	Números: Métricas em destaque — R$ 3/imóvel, 100% online, múltiplos papéis.
•	Planos: Cards dinâmicos buscados do banco de dados, com badge 'Mais popular' no Profissional.
•	CTA final: Fundo petróleo escuro, chamada para ação.
9.3. Página de planos (/planos)
Cards maiores com checklist de funcionalidades, tabela comparativa e FAQ inline sobre planos. Tudo dinâmico — do banco de dados.
9.4. FAQ (/faq)
Perguntas organizadas em categorias com accordion: Sobre o Kimobe, Planos e pagamento, Funcionalidades, Segurança e dados.
9.5. Contato (/contato)
Formulário com nome, email, telefone, assunto (select) e mensagem. Mensagens armazenadas na tabela mensagens_contato e visíveis no super admin.
9.6. Fluxo de cadastro (/registro)
Stepper de 3 etapas sem recarregar página:
Etapa 1 — Escolha do plano
Cards de planos clicáveis. Se acessado via /registro?plano={id}, plano pré-selecionado.
Etapa 2 — Dados pessoais
Nome, email (verificação AJAX de disponibilidade on blur), telefone, CPF (com validação de dígitos), senha com indicador de força.
Etapa 3 — Dados da empresa
Tipo (imobiliária ou proprietário direto — radio cards). Se imobiliária: CNPJ obrigatório. Se proprietário direto: usa o CPF da etapa 2. Resumo do pedido na lateral. Aceite de termos obrigatório.
Processamento
Em uma única transação: cria user + cria tenant + cria vínculo admin (+ vínculo proprietário se proprietário direto). Login automático, seta tenant na sessão, redireciona pro dashboard com toast de boas-vindas.
9.7. Navbar do site público
Transparente sobre o hero (logo dourado, links brancos), sólida branca ao scrollar (logo petróleo, links petróleo). Links: Funcionalidades, Planos, FAQ, Contato. Botões: Entrar e Criar conta. Se o usuário está logado e acessa /, redireciona para /dashboard.
 
10. Sistema de Notificações por Email
10.1. Visão geral
O Kimobe possui 20 templates de email organizados em 2 módulos. Todos os templates são editáveis pelo super admin via editor WYSIWYG com preview em tempo real. Os emails incluem pixel de rastreamento de abertura e todo o histórico de envios é registrado para auditoria.
10.2. Módulo Kimobe (Kimobe → assinante) — 8 templates
Template	Evento disparador
Boas-vindas	Novo assinante completa o cadastro
Aviso de vencimento	X dias antes do vencimento da fatura Kimobe
Lembrete no dia	No dia do vencimento da fatura
Cobrança atrasada	Fatura marcada como atrasada
Aviso de bloqueio	Bloqueio iminente (X dias após vencimento)
Bloqueio efetivado	Acesso bloqueado por inadimplência
Confirmação pagamento	Pagamento da fatura registrado
Novo cadastro (admin)	Notificação ao super admin sobre novo assinante

10.3. Módulo Admin (administrador → proprietário/inquilino) — 12 templates
Template	Destinatário	Evento
Cobrança gerada	Inquilino	Nova cobrança mensal criada
Lembrete vencimento	Inquilino	3 dias antes do vencimento
Cobrança atrasada	Inquilino	Cobrança marcada como atrasada
Confirmação pagamento	Inquilino	Pagamento registrado
Contrato vencendo	Inquilino	30 dias antes do fim do contrato
Repasse pendente	Proprietário(s)	Repasse gerado aguardando transferência
Repasse realizado	Proprietário(s)	Transferência confirmada
Inquilino em atraso	Proprietário(s)	Cobrança do inquilino está atrasada
Novo contrato	Proprietário(s)	Contrato criado no imóvel
Contrato encerrado	Proprietário(s)	Contrato encerrado ou cancelado
Comprovante enviado	Admin	Inquilino enviou comprovante de pagamento
Contrato vencendo (admin)	Admin	Alerta interno de contrato próximo do fim

10.4. Infraestrutura técnica
•	Templates: Armazenados na tabela email_templates com variáveis {{nome}}, {{valor}}, etc. O super admin edita via WYSIWYG (TipTap).
•	Template base HTML: Wrapper com header Kimobe em petróleo, corpo branco, botões CTA em dourado, footer cinza. O conteúdo específico é inserido dentro do wrapper.
•	Envio: Via Laravel Mail, assíncrono (queue/job). Retry: 3 tentativas com backoff exponencial.
•	Controle de duplicidade: Antes de enviar, verifica se já existe log com mesma chave + email + referência no período recente.
10.5. Pixel de rastreamento
Cada email enviado contém uma imagem invisível (1x1 pixel GIF transparente) com URL única. Quando o destinatário abre o email, o pixel é carregado e o sistema registra: data/hora da abertura, IP, user-agent, e contagem de aberturas. A rota do pixel é pública e sempre retorna a imagem (mesmo com token inválido, por segurança).
10.6. Auditoria
Dois níveis de auditoria:
•	Super admin (/admin/emails): Visão global de todos os emails enviados na plataforma. Filtros por template, módulo, status, abertura. Dialog de detalhes com variáveis usadas e possibilidade de reenvio.
•	Admin do tenant (/emails): Visão dos emails enviados aos proprietários e inquilinos do seu tenant. Apenas módulo 'admin'. Read-only.
 
11. Identidade Visual
11.1. Paleta de cores
Cor	Hex	Uso
Petróleo (primária)	#0A4F5C	Navbar assinante, botões primários, fundos de hero
Petróleo escuro	#073B45	Sidebar super admin, barra de contexto, hover
Dourado (accent)	#C9A84C	CTAs, badges destaque, logo, links especiais
Dourado claro	#E4CC82	Logo no fundo escuro
Azul claro	#8DCAD6	Links inativos na navbar, tagline
Fundo corpo	#EEF0EF	Background da área de conteúdo
Fundo cards	#FFFFFF	Cards, modais
Borda sutil	#D8DCDA	Bordas de cards, inputs, divisores
Texto principal	#1E2D30	Títulos, texto principal
Texto secundário	#6B7370	Subtítulos, labels, texto de apoio
Verde sucesso	#E7F7ED	Badges positivos (ativo, pago)
Amarelo aviso	#FFF4E5	Badges pendente, avisos
Vermelho erro	#FDECEC	Badges negativos (atrasado, bloqueado)

11.2. Layouts do sistema
Contexto	Layout	Navegação	Diferencial visual
Painel assinante	app-layout	Navbar horizontal no topo	Fundo petróleo #0A4F5C
Painel super admin	admin-layout	Sidebar lateral fixa	Fundo petróleo escuro #073B45 + badge 'Administração'
Site público	public-layout	Navbar transparente/sólida + footer	Marketing, espaçamento generoso
Telas de auth	auth-layout	Sem navegação	Fundo dividido petróleo/branco, card centralizado

11.3. Padrões de UX
•	Confirmações: Sempre via Dialog/AlertDialog do shadcn/ui. NUNCA window.alert() ou window.confirm().
•	Feedback: Toast (sonner) para sucesso e erros não-bloqueantes.
•	Loading: Spinner ou disabled em TODOS os botões durante requests.
•	Empty states: Ícone + título + descrição + ação sugerida.
•	Máscaras: Componentes reutilizáveis para telefone, CPF, CNPJ, CEP, moeda — padronizados em todo o sistema.
•	Responsivo: Mobile-first em todas as telas.
11.4. Créditos
Todas as páginas do sistema (exceto super admin) exibem no rodapé: 'Desenvolvido por' com logo da Kicol (link para kicol.com.br). Componente CreditosKicol reutilizável com variantes light/dark.
 
12. Configurações do Assinante (Settings)
12.1. Meu perfil
Dados pessoais do usuário: nome, email, telefone (editáveis), CPF (read-only). Todos os papéis acessam.
12.2. Minha empresa (admin only)
Dados da empresa: nome, tipo (read-only), documento (read-only). Endereço com auto-preenchimento por CEP. Contato: email comercial, telefone, WhatsApp, site.
12.3. Meu plano (admin only)
Plano atual com barra de uso visual, tabela de faturas do Kimobe com status, próxima fatura, e dialog para alteração de plano diretamente pelo painel.
12.4. Segurança
Alterar senha com indicador de força. Autenticação em dois fatores (ativar/desativar com QR code e códigos de recuperação).
 
13. Modelo de Dados Completo
13.1. Tabelas do sistema
Camada	Tabela	Descrição
Global	users	Usuários da plataforma (credenciais, dados pessoais)
Global	tenants	Assinantes (imobiliárias e proprietários diretos)
Global	vinculos	Relação N:N entre users e tenants com papel
Imóveis	imoveis	Cadastro de imóveis com endereço e características
Imóveis	imovel_fotos	Fotos dos imóveis com ordem e legenda
Imóveis	dados_bancarios	Contas bancárias dos proprietários
Imóveis	titularidades	Relação imóvel↔proprietário com percentual
Contratos	contratos	Contratos de locação com valores e regras
Contratos	contrato_responsabilidades	Responsabilidades financeiras (IPTU, condomínio, etc.)
Contratos	garantias	Dados da garantia locatícia
Contratos	fiadores	Dados cadastrais dos fiadores
Financeiro	cobrancas	Cobranças mensais com composição de valores
Financeiro	cobranca_itens_extras	Itens extras na cobrança
Financeiro	cobranca_comprovantes	Comprovantes de pagamento
Financeiro	repasses	Repasses financeiros aos proprietários
Financeiro	repasse_comprovantes	Comprovantes de transferência
Super admin	admin_users	Usuários do painel administrativo Kimobe
Super admin	planos	Planos de assinatura (faixas de imóveis)
Super admin	faturas_kimobe	Faturas de cobrança do Kimobe aos assinantes
Super admin	configuracoes_cobranca_kimobe	Configurações de cobrança e bloqueio
Notificações	email_templates	Templates de email editáveis
Notificações	email_logs	Registro de emails enviados com rastreamento
Suporte	mensagens_contato	Mensagens do formulário de contato
Institucional	paginas_institucionais	Páginas editáveis (termos, privacidade)

Total: 24 tabelas + tabelas auxiliares do Laravel (sessions, cache, jobs, password_resets).
Padrão obrigatório: TODA coluna em TODA migration deve ter ->comment() explicando o propósito do campo no contexto do negócio, em português.
 
14. Tarefas Realizadas
O projeto foi desenvolvido em 27+ tarefas incrementais, cada uma com escopo fechado e prompt detalhado para o Claude Code:
Tarefa	Escopo
3A	Migrations e models: tenants e vinculos
3B	Middleware multi-tenant e seleção de contexto
4A	Migrations e models: imóveis, fotos, dados bancários, titularidades
4B	Migrations e models: contratos, responsabilidades, garantias, fiadores
4C	Migrations e models: cobranças, itens extras, comprovantes, repasses
5A	Layout do app + listagem de imóveis com filtros
5B	Formulário criar/editar imóvel + detalhes
5C	Gestão de fotos e titulares do imóvel
6A	Listagem de contratos + detalhes
6B	Formulário criar/editar contrato
6C	Gestão de responsabilidades e fiadores
7A	Listagem de cobranças + detalhes
7B	Geração de cobranças + registro de pagamento + itens extras + comprovantes
7C	Listagem de repasses + confirmação + lote + comprovantes
8A	Middleware de autorização + navbar adaptável + dashboards por papel
8B	Scoping do proprietário + dados bancários
8C	Scoping do inquilino + envio de comprovante
9A	Super admin: migrations, auth, layout
9B	Super admin: gestão de planos, assinantes, usuários
9C	Super admin: faturamento, bloqueio, banners, dashboard
10A	Site público: layout + landing page + planos
10B	Site público: FAQ + contato + cadastro com stepper
11A	Notificações: infraestrutura, templates, pixel tracking
11B	Notificações: editor de templates + notificações Kimobe
11C	Notificações: notificações admin + auditoria no painel
—	Redesign telas de auth + créditos Kicol
—	Correção visual cards de planos
—	Auditoria e padronização de máscaras
—	Correção 404 rotas nomeadas vs parametrizadas
—	Termos de uso e política de privacidade editáveis
—	Redesign completo da página de settings
 
15. Princípios e Padrões de Desenvolvimento
15.1. Metodologia
Arquitetura e design decididos em conversa → traduzidos em prompts pequenos e incrementais para o Claude Code. Cada tarefa tem escopo fechado, arquivos a criar/modificar, regras claras, cenários de teste e itens que NÃO devem ser alterados.
15.2. Convenções de código
•	Idioma: Todo o sistema em português — labels, validações, mensagens, comentários em migrations, nomes de tabelas.
•	Exceção: Tabela users mantida em inglês (padrão do Laravel/Fortify).
•	Migrations: Toda coluna com ->comment() descritivo em português.
•	Models: Trait BelongsToTenant em todos os models de negócio para isolamento automático.
•	Controllers: Form Requests separados para validação. Trait ScopesPorPapel para filtragem.
•	Frontend: Componentes reutilizáveis (StatusBadge, ConfirmDialog, DataTable, InputMoeda, etc.).
•	UX: Dialog/AlertDialog para confirmações, Toast para feedback, loading states em botões.
15.3. Aprendizados técnicos
•	Ordem de rotas: Rotas nomeadas (/imoveis/criar) ANTES de rotas parametrizadas (/imoveis/{id}) para evitar 404.
•	CSS em fundos escuros: Texto e botões em contextos de fundo petróleo devem usar estilos inline para evitar herança de cor.
•	TenantScope em jobs: Jobs que rodam fora de request HTTP precisam setar o tenant manualmente via TenantService.
•	DNS: Todos os domínios de clientes apontam via CNAME para server1.kcl.srv.br — se o IP do servidor mudar, só atualiza um A record.
