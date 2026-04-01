<?php

namespace Database\Seeders;

use App\Models\PaginaInstitucional;
use Illuminate\Database\Seeder;

class PaginasInstitucionaisSeeder extends Seeder
{
    public function run(): void
    {
        // Termos de uso
        PaginaInstitucional::updateOrCreate(
            ['slug' => 'termos-de-uso'],
            [
                'titulo' => 'Termos de uso',
                'meta_description' => 'Termos de uso da plataforma Kimobe — gestão de aluguéis de imóveis.',
                'publicado' => true,
                'conteudo' => <<<'HTML'
<h2>1. Aceitação dos termos</h2>
<p>Ao acessar e utilizar a plataforma Kimobe ("Plataforma"), disponível em kimobe.com.br, você declara que leu, compreendeu e concorda com estes Termos de Uso. Se você não concorda com alguma condição, não utilize a Plataforma.</p>
<p>A Kimobe reserva-se o direito de atualizar estes termos a qualquer momento. As alterações entram em vigor a partir da data de publicação. O uso continuado da Plataforma após alterações constitui aceitação dos novos termos.</p>

<h2>2. Descrição do serviço</h2>
<p>A Kimobe é uma plataforma SaaS (Software como Serviço) de gestão de aluguéis de imóveis por longa temporada. O serviço inclui:</p>
<ul>
<li>Gestão de imóveis, contratos de locação e cadastro de proprietários e inquilinos;</li>
<li>Geração e controle de cobranças mensais de aluguel;</li>
<li>Controle de repasses financeiros aos proprietários;</li>
<li>Painel de acompanhamento para proprietários e inquilinos;</li>
<li>Notificações e comunicações por email.</li>
</ul>
<p>A Kimobe é uma ferramenta de gestão e controle. A Kimobe <strong>não é</strong> uma instituição financeira, não realiza intermediação de pagamentos, não emite boletos bancários por conta própria e não se responsabiliza por transações financeiras entre as partes (imobiliária, proprietário e inquilino).</p>

<h2>3. Cadastro e conta</h2>
<p>Para utilizar a Plataforma, é necessário criar uma conta fornecendo informações verdadeiras, completas e atualizadas. Você é responsável por manter a confidencialidade da sua senha e por todas as atividades realizadas na sua conta.</p>
<p>Cada conta de assinante (empresa ou proprietário direto) constitui um ambiente isolado. Os dados de um assinante não são acessíveis por outros assinantes.</p>
<p>Você se compromete a:</p>
<ul>
<li>Fornecer dados cadastrais verdadeiros e atualizados;</li>
<li>Não compartilhar credenciais de acesso com terceiros não autorizados;</li>
<li>Notificar imediatamente a Kimobe sobre qualquer uso não autorizado da sua conta;</li>
<li>Manter seus dados de contato atualizados para recebimento de comunicações importantes.</li>
</ul>

<h2>4. Planos e pagamento</h2>
<p>A utilização da Plataforma requer a contratação de um plano de assinatura mensal. Os planos disponíveis, seus valores e limites estão descritos na página de planos do site.</p>
<p>O pagamento é mensal e deve ser realizado até a data de vencimento da fatura. Em caso de atraso:</p>
<ul>
<li>O assinante receberá avisos por email e notificações na Plataforma;</li>
<li>Após o período de carência configurado, o acesso à Plataforma será temporariamente bloqueado;</li>
<li>O acesso será restabelecido após a regularização do pagamento.</li>
</ul>
<p>A Kimobe reserva-se o direito de alterar os valores dos planos, comunicando os assinantes com antecedência mínima de 30 dias. Alterações de preço não se aplicam ao mês corrente já faturado.</p>

<h2>5. Uso aceitável</h2>
<p>Ao utilizar a Plataforma, você concorda em:</p>
<ul>
<li>Utilizar o serviço exclusivamente para fins legítimos de gestão imobiliária;</li>
<li>Não inserir dados falsos, fraudulentos ou que violem direitos de terceiros;</li>
<li>Não tentar acessar dados de outros assinantes ou contornar mecanismos de segurança;</li>
<li>Não utilizar a Plataforma para envio de spam ou comunicações não solicitadas;</li>
<li>Respeitar a legislação brasileira vigente, incluindo a Lei do Inquilinato (Lei nº 8.245/91) e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</li>
</ul>

<h2>6. Propriedade dos dados</h2>
<p>Todos os dados inseridos pelo assinante na Plataforma (imóveis, contratos, cobranças, documentos, etc.) são de propriedade do assinante. A Kimobe atua apenas como processadora e armazenadora desses dados.</p>
<p>Em caso de cancelamento da assinatura, os dados serão mantidos por 90 dias para possibilitar reativação ou exportação. Após esse período, os dados poderão ser excluídos permanentemente.</p>

<h2>7. Disponibilidade e suporte</h2>
<p>A Kimobe empenhará esforços razoáveis para manter a Plataforma disponível 24 horas por dia, 7 dias por semana. Entretanto, não garantimos disponibilidade ininterrupta, podendo haver períodos de manutenção programada ou indisponibilidade por motivos técnicos.</p>
<p>O suporte técnico é oferecido por email em horário comercial (segunda a sexta, 9h às 18h, horário de Brasília).</p>

<h2>8. Limitação de responsabilidade</h2>
<p>A Kimobe não se responsabiliza por:</p>
<ul>
<li>Decisões tomadas pelo assinante com base nas informações da Plataforma;</li>
<li>Inadimplência de inquilinos ou disputas entre as partes envolvidas na locação;</li>
<li>Perdas financeiras decorrentes do uso ou impossibilidade de uso da Plataforma;</li>
<li>Erros em cálculos de reajuste, multas ou juros que não tenham sido validados pelo assinante;</li>
<li>Danos causados por acesso não autorizado à conta do assinante por falha na guarda de credenciais.</li>
</ul>
<p>Em nenhuma hipótese a responsabilidade total da Kimobe excederá o valor pago pelo assinante nos últimos 12 meses de uso da Plataforma.</p>

<h2>9. Cancelamento</h2>
<p>O assinante pode cancelar sua assinatura a qualquer momento, sem multa ou fidelidade. O cancelamento pode ser solicitado por email ou pelo painel da Plataforma.</p>
<p>Após o cancelamento:</p>
<ul>
<li>O acesso à Plataforma será encerrado ao final do período já pago;</li>
<li>Os dados serão mantidos por 90 dias;</li>
<li>Após 90 dias, os dados poderão ser excluídos permanentemente.</li>
</ul>
<p>A Kimobe pode cancelar a conta de um assinante em caso de violação destes Termos, uso fraudulento ou inadimplência prolongada (superior a 90 dias).</p>

<h2>10. Propriedade intelectual</h2>
<p>A Plataforma Kimobe, incluindo seu código, design, marca, logotipos e documentação, é propriedade exclusiva da Kimobe. Nenhum direito de propriedade intelectual é transferido ao assinante pelo uso da Plataforma.</p>

<h2>11. Disposições gerais</h2>
<p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Para dirimir quaisquer controvérsias, fica eleito o foro da Comarca de Niterói/RJ, com exclusão de qualquer outro, por mais privilegiado que seja.</p>
<p>A tolerância quanto ao descumprimento de qualquer condição destes Termos não constituirá renúncia ou novação.</p>
<p>Se qualquer disposição destes Termos for considerada inválida, as demais disposições permanecerão em pleno vigor.</p>

<h2>12. Contato</h2>
<p>Para dúvidas sobre estes Termos de Uso, entre em contato pelo email <strong>contato@kimobe.com.br</strong>.</p>
HTML,
            ]
        );

        // Política de privacidade
        PaginaInstitucional::updateOrCreate(
            ['slug' => 'politica-de-privacidade'],
            [
                'titulo' => 'Política de privacidade',
                'meta_description' => 'Política de privacidade da plataforma Kimobe — como coletamos, usamos e protegemos seus dados.',
                'publicado' => true,
                'conteudo' => <<<'HTML'
<h2>1. Introdução</h2>
<p>A Kimobe ("nós", "nosso") valoriza a privacidade dos seus usuários. Esta Política de Privacidade descreve como coletamos, usamos, armazenamos e protegemos seus dados pessoais em conformidade com a Lei Geral de Proteção de Dados Pessoais (LGPD — Lei nº 13.709/2018).</p>
<p>Ao utilizar a plataforma Kimobe, você consente com as práticas descritas nesta política.</p>

<h2>2. Dados que coletamos</h2>
<h3>2.1. Dados fornecidos por você</h3>
<ul>
<li><strong>Dados cadastrais:</strong> nome completo, email, telefone, CPF ou CNPJ;</li>
<li><strong>Dados da empresa:</strong> razão social, nome fantasia, tipo de empresa;</li>
<li><strong>Dados de imóveis:</strong> endereço, características, fotos, valor de aluguel;</li>
<li><strong>Dados de contratos:</strong> valores, vigência, garantias, responsabilidades;</li>
<li><strong>Dados financeiros:</strong> valores de cobranças, pagamentos, dados bancários para repasse (banco, agência, conta, chave PIX);</li>
<li><strong>Dados de terceiros:</strong> quando você cadastra proprietários, inquilinos ou fiadores, os dados pessoais dessas pessoas são armazenados sob sua responsabilidade;</li>
<li><strong>Comunicações:</strong> mensagens enviadas pelo formulário de contato.</li>
</ul>

<h3>2.2. Dados coletados automaticamente</h3>
<ul>
<li><strong>Dados de acesso:</strong> endereço IP, navegador, sistema operacional, páginas acessadas;</li>
<li><strong>Dados de email:</strong> registro de emails enviados, data de abertura e leitura (via pixel de rastreamento);</li>
<li><strong>Dados de sessão:</strong> informações de login, contexto ativo, preferências de uso.</li>
</ul>

<h2>3. Como usamos seus dados</h2>
<p>Utilizamos seus dados para:</p>
<ul>
<li>Fornecer e manter o funcionamento da Plataforma;</li>
<li>Processar seu cadastro e gerenciar sua assinatura;</li>
<li>Gerar cobranças, repasses e demais funcionalidades do sistema;</li>
<li>Enviar notificações por email sobre eventos da Plataforma (cobranças, repasses, vencimentos, etc.);</li>
<li>Enviar comunicações sobre sua conta (faturas, avisos, bloqueios);</li>
<li>Melhorar a Plataforma com base em dados de uso agregados e anônimos;</li>
<li>Cumprir obrigações legais e regulatórias;</li>
<li>Prestar suporte técnico.</li>
</ul>
<p>Seus dados <strong>não são vendidos</strong> a terceiros em nenhuma circunstância.</p>

<h2>4. Compartilhamento de dados</h2>
<p>Podemos compartilhar seus dados nas seguintes situações:</p>
<ul>
<li><strong>Dentro do tenant:</strong> dados de proprietários e inquilinos são visíveis para o administrador do respectivo assinante, conforme os papéis de acesso definidos;</li>
<li><strong>Prestadores de serviço:</strong> podemos utilizar serviços de terceiros para envio de emails, hospedagem e processamento de pagamentos. Esses prestadores têm acesso limitado aos dados necessários para executar suas funções;</li>
<li><strong>Obrigação legal:</strong> podemos divulgar dados quando exigido por lei, ordem judicial ou autoridade competente;</li>
<li><strong>Proteção de direitos:</strong> quando necessário para proteger os direitos, propriedade ou segurança da Kimobe, seus usuários ou terceiros.</li>
</ul>

<h2>5. Isolamento de dados (multi-tenancy)</h2>
<p>A Kimobe opera em modelo multi-tenant, o que significa que cada assinante possui um ambiente isolado. Os dados de um assinante (imóveis, contratos, cobranças, etc.) <strong>não são acessíveis por outros assinantes</strong>.</p>
<p>Um mesmo usuário pode ter vínculos em mais de um assinante (ex: ser proprietário em uma imobiliária e inquilino em outra). Nesse caso, os dados de cada contexto permanecem isolados.</p>

<h2>6. Armazenamento e segurança</h2>
<p>Seus dados são armazenados em servidores seguros localizados no Brasil. Adotamos medidas técnicas e organizacionais para proteger seus dados, incluindo:</p>
<ul>
<li>Criptografia de senhas (hash bcrypt);</li>
<li>Autenticação em dois fatores (2FA) disponível;</li>
<li>Comunicação via HTTPS (criptografia em trânsito);</li>
<li>Isolamento de dados entre assinantes;</li>
<li>Controle de acesso baseado em papéis (admin, proprietário, inquilino);</li>
<li>Logs de auditoria de ações sensíveis.</li>
</ul>
<p>Nenhum sistema é 100% seguro. Em caso de incidente de segurança que possa afetar seus dados, notificaremos os afetados e a Autoridade Nacional de Proteção de Dados (ANPD) conforme exigido pela LGPD.</p>

<h2>7. Retenção de dados</h2>
<p>Seus dados são mantidos enquanto sua conta estiver ativa. Após o cancelamento:</p>
<ul>
<li>Os dados são mantidos por 90 dias para possibilitar reativação;</li>
<li>Após 90 dias, os dados poderão ser anonimizados ou excluídos;</li>
<li>Dados necessários para cumprimento de obrigações legais podem ser retidos por prazo superior.</li>
</ul>

<h2>8. Seus direitos (LGPD)</h2>
<p>Conforme a LGPD, você tem direito a:</p>
<ul>
<li><strong>Confirmação e acesso:</strong> saber se tratamos seus dados e acessá-los;</li>
<li><strong>Correção:</strong> solicitar a correção de dados incompletos ou desatualizados;</li>
<li><strong>Anonimização ou exclusão:</strong> solicitar a anonimização ou exclusão de dados desnecessários;</li>
<li><strong>Portabilidade:</strong> solicitar a transferência dos seus dados para outro fornecedor;</li>
<li><strong>Revogação do consentimento:</strong> retirar seu consentimento a qualquer momento;</li>
<li><strong>Informação sobre compartilhamento:</strong> saber com quais entidades seus dados foram compartilhados;</li>
<li><strong>Oposição:</strong> opor-se ao tratamento de dados quando realizado sem seu consentimento.</li>
</ul>
<p>Para exercer qualquer desses direitos, entre em contato pelo email <strong>privacidade@kimobe.com.br</strong>. Responderemos sua solicitação em até 15 dias.</p>

<h2>9. Cookies e rastreamento</h2>
<p>A Plataforma utiliza cookies de sessão para manter seu login ativo e suas preferências. Não utilizamos cookies de terceiros para fins publicitários.</p>
<p>Os emails enviados pela Plataforma contêm um pixel de rastreamento (imagem invisível de 1x1 pixel) que nos permite saber se o email foi aberto. Essa informação é usada exclusivamente para fins de auditoria e melhoria do serviço.</p>

<h2>10. Dados de terceiros</h2>
<p>Ao cadastrar proprietários, inquilinos e fiadores na Plataforma, você se torna responsável por:</p>
<ul>
<li>Obter o consentimento dessas pessoas para o tratamento de seus dados;</li>
<li>Garantir que os dados fornecidos são verdadeiros e atualizados;</li>
<li>Informá-las sobre a existência e finalidade do tratamento.</li>
</ul>
<p>A Kimobe atua como operadora desses dados, seguindo as instruções do assinante (controlador).</p>

<h2>11. Menores de idade</h2>
<p>A Plataforma não é destinada a menores de 18 anos. Não coletamos intencionalmente dados de menores. Se tomarmos conhecimento de que dados de um menor foram coletados, eles serão excluídos.</p>

<h2>12. Alterações nesta política</h2>
<p>Esta política pode ser atualizada periodicamente. Alterações significativas serão comunicadas por email ou aviso na Plataforma. A data da última atualização é exibida no final desta página.</p>

<h2>13. Contato</h2>
<p>Para questões relacionadas à privacidade e proteção de dados:</p>
<ul>
<li><strong>Email:</strong> privacidade@kimobe.com.br</li>
<li><strong>Responsável:</strong> Kimobe Tecnologia</li>
<li><strong>Endereço:</strong> Niterói/RJ — Brasil</li>
</ul>
HTML,
            ]
        );
    }
}
