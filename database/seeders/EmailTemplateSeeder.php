<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ==========================================
            // MÓDULO KIMOBE (plataforma → assinante)
            // ==========================================
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.boas_vindas',
                'nome' => 'Boas-vindas ao novo assinante',
                'descricao' => 'Enviado quando um novo assinante cria sua conta no Kimobe.',
                'assunto' => 'Bem-vindo ao Kimobe, {{nome}}!',
                'variaveis_disponiveis' => [
                    ['var' => 'nome', 'desc' => 'Nome do usuário'], ['var' => 'email', 'desc' => 'Email'], ['var' => 'nome_empresa', 'desc' => 'Nome da empresa'], ['var' => 'plano_nome', 'desc' => 'Nome do plano contratado'],
                ],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Bem-vindo ao Kimobe, {{nome}}!</h2><p style="color:#3A4240;line-height:1.6;">Sua conta foi criada com sucesso. Você está no plano <strong>{{plano_nome}}</strong> para a empresa <strong>{{nome_empresa}}</strong>.</p><p style="color:#3A4240;line-height:1.6;">Comece cadastrando seus imóveis e configurando contratos.</p><table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;"><tr><td style="background-color:#C9A84C;border-radius:6px;padding:12px 24px;"><a href="{{link_sistema}}" style="color:#2E2410;text-decoration:none;font-weight:500;font-size:14px;">Acessar o sistema</a></td></tr></table>',
                'corpo_texto' => "Bem-vindo ao Kimobe, {{nome}}!\n\nSua conta foi criada com sucesso no plano {{plano_nome}} para {{nome_empresa}}.\n\nAcesse o sistema para começar.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.aviso_vencimento',
                'nome' => 'Aviso de vencimento de fatura',
                'descricao' => 'Enviado X dias antes do vencimento da fatura do Kimobe.',
                'assunto' => 'Sua fatura Kimobe vence em {{dias}} dias',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'data_vencimento', 'desc' => 'Data'], ['var' => 'dias', 'desc' => 'Dias até vencimento']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Fatura próxima do vencimento</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. A fatura da <strong>{{nome_empresa}}</strong> referente a <strong>{{referencia}}</strong> vence em <strong>{{dias}} dias</strong>.</p><div style="background:#F7F8F7;border-radius:8px;padding:16px;margin:16px 0;text-align:center;"><p style="margin:0;font-size:24px;font-weight:600;color:#0A4F5C;">R$ {{valor}}</p><p style="margin:4px 0 0;font-size:12px;color:#8A918E;">Vencimento: {{data_vencimento}}</p></div>',
                'corpo_texto' => "Olá, {{nome}}. Sua fatura de {{referencia}} (R$ {{valor}}) vence em {{dias}} dias ({{data_vencimento}}).",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.lembrete_vencimento',
                'nome' => 'Lembrete de vencimento no dia',
                'descricao' => 'Enviado no dia do vencimento da fatura.',
                'assunto' => 'Sua fatura Kimobe vence hoje — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'data_vencimento', 'desc' => 'Data']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Sua fatura vence hoje</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. A fatura de <strong>{{referencia}}</strong> no valor de <strong>R$ {{valor}}</strong> vence hoje.</p>',
                'corpo_texto' => "Olá, {{nome}}. Sua fatura de {{referencia}} (R$ {{valor}}) vence hoje.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.cobranca_atrasada',
                'nome' => 'Fatura em atraso',
                'descricao' => 'Enviado quando a fatura do Kimobe passa do vencimento.',
                'assunto' => 'Sua fatura Kimobe está em atraso — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'data_vencimento', 'desc' => 'Data'], ['var' => 'dias_atraso', 'desc' => 'Dias em atraso']],
                'corpo_html' => '<h2 style="color:#A83232;margin:0 0 16px;">Fatura em atraso</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. A fatura de <strong>{{referencia}}</strong> no valor de <strong>R$ {{valor}}</strong> está em atraso há <strong>{{dias_atraso}} dias</strong>.</p><p style="color:#A83232;font-size:13px;">Regularize para evitar o bloqueio do acesso ao sistema.</p>',
                'corpo_texto' => "ATENÇÃO: Sua fatura de {{referencia}} (R$ {{valor}}) está em atraso há {{dias_atraso}} dias. Regularize para evitar bloqueio.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.aviso_bloqueio',
                'nome' => 'Aviso de bloqueio iminente',
                'descricao' => 'Enviado quando o bloqueio por inadimplência está próximo.',
                'assunto' => 'ATENÇÃO: Seu acesso ao Kimobe será bloqueado em {{dias_para_bloqueio}} dias',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'dias_atraso', 'desc' => 'Dias atraso'], ['var' => 'dias_para_bloqueio', 'desc' => 'Dias para bloqueio']],
                'corpo_html' => '<div style="background:#FDECEC;border-radius:8px;padding:16px;margin:0 0 16px;"><h2 style="color:#A83232;margin:0 0 8px;font-size:18px;">⚠️ Bloqueio iminente</h2><p style="color:#A83232;margin:0;font-size:14px;">Seu acesso será bloqueado em <strong>{{dias_para_bloqueio}} dias</strong> se a fatura não for regularizada.</p></div><p style="color:#3A4240;line-height:1.6;">Fatura {{referencia}} — R$ {{valor}} — em atraso há {{dias_atraso}} dias.</p>',
                'corpo_texto' => "URGENTE: Seu acesso ao Kimobe será bloqueado em {{dias_para_bloqueio}} dias. Fatura {{referencia}} (R$ {{valor}}) em atraso há {{dias_atraso}} dias.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.bloqueio',
                'nome' => 'Acesso bloqueado por inadimplência',
                'descricao' => 'Enviado quando o acesso é efetivamente bloqueado.',
                'assunto' => 'Seu acesso ao Kimobe foi bloqueado',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'motivo', 'desc' => 'Motivo do bloqueio']],
                'corpo_html' => '<div style="background:#FDECEC;border-radius:8px;padding:16px;margin:0 0 16px;text-align:center;"><h2 style="color:#A83232;margin:0 0 8px;">🔒 Acesso bloqueado</h2><p style="color:#A83232;margin:0;">{{motivo}}</p></div><p style="color:#3A4240;line-height:1.6;">Para regularizar, entre em contato pelo email financeiro@kimobe.com.br.</p>',
                'corpo_texto' => "Seu acesso ao Kimobe foi bloqueado. Motivo: {{motivo}}. Contato: financeiro@kimobe.com.br.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.confirmacao_pagamento',
                'nome' => 'Confirmação de pagamento da fatura',
                'descricao' => 'Enviado quando o pagamento da fatura Kimobe é registrado.',
                'assunto' => 'Pagamento confirmado — Fatura {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor', 'desc' => 'Valor R$'], ['var' => 'data_pagamento', 'desc' => 'Data pgto'], ['var' => 'metodo_pagamento', 'desc' => 'Método']],
                'corpo_html' => '<div style="background:#E7F7ED;border-radius:8px;padding:16px;margin:0 0 16px;text-align:center;"><h2 style="color:#1B6B3A;margin:0 0 8px;">✓ Pagamento confirmado</h2><p style="color:#1B6B3A;margin:0;font-size:20px;font-weight:600;">R$ {{valor}}</p></div><p style="color:#3A4240;line-height:1.6;">Fatura {{referencia}} paga em {{data_pagamento}} via {{metodo_pagamento}}. Obrigado, {{nome}}!</p>',
                'corpo_texto' => "Pagamento confirmado! Fatura {{referencia}} — R$ {{valor}} — paga em {{data_pagamento}} via {{metodo_pagamento}}.",
            ],
            [
                'modulo' => 'kimobe', 'chave' => 'kimobe.novo_cadastro_admin',
                'nome' => 'Notificação de novo cadastro (para super admin)',
                'descricao' => 'Enviado para a equipe Kimobe quando um novo assinante se cadastra.',
                'assunto' => 'Novo assinante: {{nome_empresa}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'email', 'desc' => 'Email'], ['var' => 'nome_empresa', 'desc' => 'Empresa'], ['var' => 'tipo_tenant', 'desc' => 'Tipo'], ['var' => 'plano_nome', 'desc' => 'Plano'], ['var' => 'documento', 'desc' => 'Documento']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Novo assinante cadastrado</h2><table style="width:100%;font-size:14px;color:#3A4240;"><tr><td style="padding:4px 0;"><strong>Empresa:</strong> {{nome_empresa}}</td></tr><tr><td style="padding:4px 0;"><strong>Tipo:</strong> {{tipo_tenant}}</td></tr><tr><td style="padding:4px 0;"><strong>Documento:</strong> {{documento}}</td></tr><tr><td style="padding:4px 0;"><strong>Responsável:</strong> {{nome}} ({{email}})</td></tr><tr><td style="padding:4px 0;"><strong>Plano:</strong> {{plano_nome}}</td></tr></table>',
                'corpo_texto' => "Novo assinante: {{nome_empresa}} ({{tipo_tenant}}) — Plano: {{plano_nome}} — Responsável: {{nome}} ({{email}})",
            ],

            // ==========================================
            // MÓDULO ADMIN (administrador → proprietário/inquilino)
            // ==========================================
            [
                'modulo' => 'admin', 'chave' => 'admin.cobranca_gerada',
                'nome' => 'Nova cobrança gerada',
                'descricao' => 'Enviado ao inquilino quando uma cobrança é gerada.',
                'assunto' => 'Sua cobrança de {{referencia}} está disponível',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome inquilino'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_total', 'desc' => 'Valor R$'], ['var' => 'data_vencimento', 'desc' => 'Data venc.']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Nova cobrança disponível</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. A cobrança referente a <strong>{{referencia}}</strong> do imóvel <strong>{{imovel_endereco}}</strong> está disponível.</p><div style="background:#F7F8F7;border-radius:8px;padding:16px;margin:16px 0;text-align:center;"><p style="margin:0;font-size:24px;font-weight:600;color:#0A4F5C;">R$ {{valor_total}}</p><p style="margin:4px 0 0;font-size:12px;color:#8A918E;">Vencimento: {{data_vencimento}}</p></div>',
                'corpo_texto' => "Olá, {{nome}}. Cobrança de {{referencia}} — R$ {{valor_total}} — Vencimento: {{data_vencimento}} — Imóvel: {{imovel_endereco}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.lembrete_vencimento_cobranca',
                'nome' => 'Lembrete de vencimento de cobrança',
                'descricao' => 'Enviado ao inquilino dias antes do vencimento da cobrança.',
                'assunto' => 'Sua cobrança vence em {{dias}} dias — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_total', 'desc' => 'Valor'], ['var' => 'data_vencimento', 'desc' => 'Data'], ['var' => 'dias', 'desc' => 'Dias']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Lembrete de vencimento</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. Sua cobrança de <strong>R$ {{valor_total}}</strong> vence em <strong>{{dias}} dias</strong> ({{data_vencimento}}).</p>',
                'corpo_texto' => "Lembrete: cobrança de R$ {{valor_total}} vence em {{dias}} dias ({{data_vencimento}}).",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.cobranca_atrasada',
                'nome' => 'Cobrança em atraso',
                'descricao' => 'Enviado ao inquilino quando a cobrança atrasa.',
                'assunto' => 'Cobrança em atraso — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_total', 'desc' => 'Valor'], ['var' => 'data_vencimento', 'desc' => 'Data'], ['var' => 'dias_atraso', 'desc' => 'Dias atraso'], ['var' => 'valor_multa', 'desc' => 'Multa'], ['var' => 'valor_juros', 'desc' => 'Juros']],
                'corpo_html' => '<h2 style="color:#A83232;margin:0 0 16px;">Cobrança em atraso</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. A cobrança de {{referencia}} ({{imovel_endereco}}) está em atraso há <strong>{{dias_atraso}} dias</strong>.</p>',
                'corpo_texto' => "Cobrança em atraso: {{referencia}} — R$ {{valor_total}} — {{dias_atraso}} dias de atraso.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.confirmacao_pagamento_inquilino',
                'nome' => 'Confirmação de pagamento ao inquilino',
                'descricao' => 'Enviado ao inquilino quando o pagamento é registrado.',
                'assunto' => 'Pagamento registrado — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_pago', 'desc' => 'Valor pago'], ['var' => 'data_pagamento', 'desc' => 'Data']],
                'corpo_html' => '<div style="background:#E7F7ED;border-radius:8px;padding:16px;margin:0 0 16px;text-align:center;"><p style="color:#1B6B3A;margin:0;font-weight:600;">✓ Pagamento registrado</p></div><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. O pagamento de <strong>R$ {{valor_pago}}</strong> referente a {{referencia}} foi registrado em {{data_pagamento}}.</p>',
                'corpo_texto' => "Pagamento registrado: {{referencia}} — R$ {{valor_pago}} — {{data_pagamento}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.contrato_vencendo',
                'nome' => 'Contrato próximo do vencimento',
                'descricao' => 'Enviado ao inquilino quando o contrato está próximo de vencer.',
                'assunto' => 'Seu contrato vence em {{dias}} dias',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'data_fim', 'desc' => 'Data fim'], ['var' => 'dias', 'desc' => 'Dias']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Contrato próximo do vencimento</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. Seu contrato do imóvel <strong>{{imovel_endereco}}</strong> vence em <strong>{{dias}} dias</strong> ({{data_fim}}).</p>',
                'corpo_texto' => "Seu contrato de {{imovel_endereco}} vence em {{dias}} dias ({{data_fim}}).",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.repasse_pendente',
                'nome' => 'Repasse pendente',
                'descricao' => 'Enviado ao proprietário quando um repasse é gerado.',
                'assunto' => 'Repasse de {{referencia}} gerado — R$ {{valor_liquido}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome proprietário'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_liquido', 'desc' => 'Valor líquido'], ['var' => 'data_prevista', 'desc' => 'Data prevista']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Repasse pendente</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. Um repasse de <strong>R$ {{valor_liquido}}</strong> referente a {{referencia}} do imóvel {{imovel_endereco}} está previsto para {{data_prevista}}.</p>',
                'corpo_texto' => "Repasse pendente: {{referencia}} — R$ {{valor_liquido}} — Previsto: {{data_prevista}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.repasse_realizado',
                'nome' => 'Repasse realizado',
                'descricao' => 'Enviado ao proprietário quando o repasse é transferido.',
                'assunto' => 'Repasse de {{referencia}} transferido — R$ {{valor_liquido}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_liquido', 'desc' => 'Valor'], ['var' => 'data_realizada', 'desc' => 'Data'], ['var' => 'banco_nome', 'desc' => 'Banco'], ['var' => 'conta', 'desc' => 'Conta']],
                'corpo_html' => '<div style="background:#E7F7ED;border-radius:8px;padding:16px;margin:0 0 16px;text-align:center;"><p style="color:#1B6B3A;margin:0;font-size:20px;font-weight:600;">R$ {{valor_liquido}}</p><p style="color:#1B6B3A;margin:4px 0 0;font-size:12px;">Transferido para {{banco_nome}} — CC {{conta}}</p></div><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. O repasse de {{referencia}} ({{imovel_endereco}}) foi transferido em {{data_realizada}}.</p>',
                'corpo_texto' => "Repasse transferido: {{referencia}} — R$ {{valor_liquido}} — {{banco_nome}} CC {{conta}} — {{data_realizada}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.cobranca_inquilino_atrasada_proprietario',
                'nome' => 'Aviso ao proprietário: inquilino em atraso',
                'descricao' => 'Enviado ao proprietário quando o inquilino atrasa o pagamento.',
                'assunto' => 'Inquilino em atraso — {{imovel_endereco}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome proprietário'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'inquilino_nome', 'desc' => 'Inquilino'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'valor_total', 'desc' => 'Valor'], ['var' => 'dias_atraso', 'desc' => 'Dias atraso']],
                'corpo_html' => '<h2 style="color:#8C5A10;margin:0 0 16px;">Inquilino em atraso</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. O inquilino <strong>{{inquilino_nome}}</strong> do imóvel <strong>{{imovel_endereco}}</strong> está com a cobrança {{referencia}} em atraso há {{dias_atraso}} dias.</p>',
                'corpo_texto' => "Inquilino {{inquilino_nome}} em atraso: {{referencia}} — {{imovel_endereco}} — {{dias_atraso}} dias.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.novo_contrato_proprietario',
                'nome' => 'Novo contrato no imóvel',
                'descricao' => 'Enviado ao proprietário quando um contrato é criado em seu imóvel.',
                'assunto' => 'Novo contrato criado — {{imovel_endereco}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome proprietário'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'inquilino_nome', 'desc' => 'Inquilino'], ['var' => 'valor_aluguel', 'desc' => 'Aluguel'], ['var' => 'data_inicio', 'desc' => 'Início'], ['var' => 'data_fim', 'desc' => 'Fim']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Novo contrato criado</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. Um novo contrato foi criado para o imóvel <strong>{{imovel_endereco}}</strong>.</p><table style="width:100%;font-size:14px;color:#3A4240;margin:12px 0;"><tr><td style="padding:4px 0;"><strong>Inquilino:</strong> {{inquilino_nome}}</td></tr><tr><td style="padding:4px 0;"><strong>Aluguel:</strong> R$ {{valor_aluguel}}</td></tr><tr><td style="padding:4px 0;"><strong>Vigência:</strong> {{data_inicio}} a {{data_fim}}</td></tr></table>',
                'corpo_texto' => "Novo contrato: {{imovel_endereco}} — Inquilino: {{inquilino_nome}} — R$ {{valor_aluguel}} — {{data_inicio}} a {{data_fim}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.contrato_encerrado_proprietario',
                'nome' => 'Contrato encerrado',
                'descricao' => 'Enviado ao proprietário quando um contrato é encerrado.',
                'assunto' => 'Contrato encerrado — {{imovel_endereco}}',
                'variaveis_disponiveis' => [['var' => 'nome', 'desc' => 'Nome'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'inquilino_nome', 'desc' => 'Inquilino'], ['var' => 'data_encerramento', 'desc' => 'Data']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Contrato encerrado</h2><p style="color:#3A4240;line-height:1.6;">Olá, {{nome}}. O contrato de <strong>{{inquilino_nome}}</strong> no imóvel <strong>{{imovel_endereco}}</strong> foi encerrado em {{data_encerramento}}.</p>',
                'corpo_texto' => "Contrato encerrado: {{imovel_endereco}} — Inquilino: {{inquilino_nome}} — {{data_encerramento}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.comprovante_enviado',
                'nome' => 'Inquilino enviou comprovante',
                'descricao' => 'Enviado ao admin quando o inquilino envia comprovante de pagamento.',
                'assunto' => 'Comprovante recebido — {{inquilino_nome}} — {{referencia}}',
                'variaveis_disponiveis' => [['var' => 'inquilino_nome', 'desc' => 'Inquilino'], ['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'referencia', 'desc' => 'MM/YYYY'], ['var' => 'data_envio', 'desc' => 'Data envio']],
                'corpo_html' => '<h2 style="color:#1E2D30;margin:0 0 16px;">Comprovante recebido</h2><p style="color:#3A4240;line-height:1.6;">O inquilino <strong>{{inquilino_nome}}</strong> enviou um comprovante de pagamento referente a <strong>{{referencia}}</strong> do imóvel {{imovel_endereco}} em {{data_envio}}.</p>',
                'corpo_texto' => "Comprovante recebido de {{inquilino_nome}} — {{referencia}} — {{imovel_endereco}} — {{data_envio}}.",
            ],
            [
                'modulo' => 'admin', 'chave' => 'admin.contrato_vencendo_admin',
                'nome' => 'Contrato vencendo (notificação ao admin)',
                'descricao' => 'Enviado ao admin quando um contrato está próximo de vencer.',
                'assunto' => 'Contrato vence em {{dias}} dias — {{imovel_endereco}}',
                'variaveis_disponiveis' => [['var' => 'imovel_endereco', 'desc' => 'Endereço'], ['var' => 'inquilino_nome', 'desc' => 'Inquilino'], ['var' => 'data_fim', 'desc' => 'Data fim'], ['var' => 'dias', 'desc' => 'Dias']],
                'corpo_html' => '<h2 style="color:#8C5A10;margin:0 0 16px;">Contrato vencendo</h2><p style="color:#3A4240;line-height:1.6;">O contrato de <strong>{{inquilino_nome}}</strong> no imóvel <strong>{{imovel_endereco}}</strong> vence em <strong>{{dias}} dias</strong> ({{data_fim}}).</p>',
                'corpo_texto' => "Contrato vencendo: {{imovel_endereco}} — {{inquilino_nome}} — vence em {{dias}} dias ({{data_fim}}).",
            ],
        ];

        foreach ($templates as $data) {
            EmailTemplate::create($data);
        }
    }
}
