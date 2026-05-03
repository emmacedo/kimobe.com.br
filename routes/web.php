<?php

use App\Http\Controllers\Admin\AdminAssinanteController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminConfiguracaoController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmailLogController;
use App\Http\Controllers\Admin\AdminMensagemController;
use App\Http\Controllers\Admin\AdminMinhaContaController;
use App\Http\Controllers\Admin\AdminPaginaController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTwoFactorChallengeController;
use App\Http\Controllers\Admin\AdminUsuarioController;
use App\Http\Controllers\ComprovanteController;
use App\Http\Controllers\CondominioController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ContratoInquilinoController;
use App\Http\Controllers\ContratoReajusteController;
use App\Http\Controllers\DadosBancariosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\EntidadeExternaController;
use App\Http\Controllers\FaturaController;
use App\Http\Controllers\FiadorController;
use App\Http\Controllers\ImovelController;
use App\Http\Controllers\ImovelFotoController;
use App\Http\Controllers\InquilinoController;
use App\Http\Controllers\ItemCobrancaController;
use App\Http\Controllers\PaginaInstitucionalController;
use App\Http\Controllers\ProprietarioController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\RepasseController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TenantSelectionController;
use App\Http\Controllers\TitularidadeController;
use Illuminate\Support\Facades\Route;
use Kicol\FullFlow\Http\Controllers\FullFlowWebhookController;

// Pixel de rastreamento de email (rota pública sem auth)
Route::get('/email/pixel/{token}', [EmailTrackingController::class, 'pixel'])->name('email.pixel');

// SEO — sitemap (rota pública sem auth)
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Webhook FullFlow (HMAC + idempotência tratados pelo controller do package)
Route::post('/webhooks/fullflow', FullFlowWebhookController::class)
    ->name('webhooks.fullflow');

// Site público
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/planos', [PublicController::class, 'planos'])->name('planos');
Route::get('/faq', [PublicController::class, 'faq'])->name('faq');
Route::get('/contato', [PublicController::class, 'contato'])->name('contato');
Route::post('/contato', [PublicController::class, 'enviarContato'])->name('contato.enviar');

// Páginas institucionais (termos de uso, privacidade)
Route::get('/termos-de-uso', [PaginaInstitucionalController::class, 'show'])->defaults('slug', 'termos-de-uso')->name('termos');
Route::get('/politica-de-privacidade', [PaginaInstitucionalController::class, 'show'])->defaults('slug', 'politica-de-privacidade')->name('privacidade');

// Registro / assinatura
Route::get('/registro', [RegistroController::class, 'index'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->name('registro.store');
Route::post('/registro/verificar-email', [RegistroController::class, 'verificarEmail'])->name('registro.verificar-email');

// Rotas de seleção de tenant — apenas auth, SEM middleware tenant
Route::middleware(['auth'])->group(function () {
    Route::get('selecionar-contexto', [TenantSelectionController::class, 'index'])->name('tenant.selecionar');
    Route::post('selecionar-contexto', [TenantSelectionController::class, 'store'])->name('tenant.selecionar.store');
    Route::get('sem-acesso', [TenantSelectionController::class, 'semAcesso'])->name('tenant.sem-acesso');
    Route::get('bloqueado', [TenantSelectionController::class, 'bloqueado'])->name('tenant.bloqueado');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('trocar-contexto', [TenantSelectionController::class, 'trocar'])->name('tenant.trocar');
});

// ========================================================================
// Rotas protegidas do app — auth + verified + tenant
// ========================================================================
Route::middleware(['auth', 'verified', 'tenant', 'tenant.ativo', 'subscription.active'])->group(function () {

    // Dashboard — todos os papéis
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ----------------------------------------------------------------
    // Imóveis — admin gerencia, proprietário visualiza
    // ----------------------------------------------------------------
    // Gestão: apenas admin (rotas estáticas ANTES das parametrizadas para evitar conflito)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('imoveis/criar', [ImovelController::class, 'create'])->name('imoveis.create');
        Route::post('imoveis', [ImovelController::class, 'store'])->name('imoveis.store');
        Route::get('imoveis/{imovel}/editar', [ImovelController::class, 'edit'])->name('imoveis.edit');
        Route::put('imoveis/{imovel}', [ImovelController::class, 'update'])->name('imoveis.update');
        Route::delete('imoveis/{imovel}', [ImovelController::class, 'destroy'])->name('imoveis.destroy');

        // Fotos
        Route::post('imoveis/{imovel}/fotos', [ImovelFotoController::class, 'store']);
        Route::patch('imoveis/{imovel}/fotos/{foto}', [ImovelFotoController::class, 'update']);
        Route::put('imoveis/{imovel}/fotos/reordenar', [ImovelFotoController::class, 'reordenar']);
        Route::delete('imoveis/{imovel}/fotos/{foto}', [ImovelFotoController::class, 'destroy']);

        // Titularidades
        Route::post('imoveis/{imovel}/titularidades', [TitularidadeController::class, 'store']);
        Route::put('imoveis/{imovel}/titularidades/{titularidade}', [TitularidadeController::class, 'update']);
        Route::delete('imoveis/{imovel}/titularidades/{titularidade}', [TitularidadeController::class, 'destroy']);

        // Condomínio do imóvel (sub-recurso 1:1)
        Route::put('imoveis/{imovel}/condominio', [CondominioController::class, 'upsert'])->name('imoveis.condominio.upsert');
        Route::delete('imoveis/{imovel}/condominio', [CondominioController::class, 'destroy'])->name('imoveis.condominio.destroy');

        // Entidades externas (CRUD próprio + endpoint inline para o dialog do imóvel)
        Route::get('entidades-externas', [EntidadeExternaController::class, 'index'])->name('entidades-externas.index');
        Route::get('entidades-externas/criar', [EntidadeExternaController::class, 'create'])->name('entidades-externas.create');
        Route::post('entidades-externas', [EntidadeExternaController::class, 'store'])->name('entidades-externas.store');
        Route::post('entidades-externas/inline', [EntidadeExternaController::class, 'storeInline'])->name('entidades-externas.inline');
        Route::get('entidades-externas/{entidadeExterna}/editar', [EntidadeExternaController::class, 'edit'])->name('entidades-externas.edit');
        Route::put('entidades-externas/{entidadeExterna}', [EntidadeExternaController::class, 'update'])->name('entidades-externas.update');
        Route::delete('entidades-externas/{entidadeExterna}', [EntidadeExternaController::class, 'destroy'])->name('entidades-externas.destroy');

        // Proprietários (CRUD + endpoints JSON para autocomplete e criação inline)
        // Rotas estáticas/específicas ANTES das parametrizadas para evitar conflito.
        Route::get('proprietarios', [ProprietarioController::class, 'index'])->name('proprietarios.index');
        Route::get('proprietarios/criar', [ProprietarioController::class, 'create'])->name('proprietarios.create');
        Route::get('proprietarios/buscar', [ProprietarioController::class, 'buscar'])->name('proprietarios.buscar');
        Route::post('proprietarios', [ProprietarioController::class, 'store'])->name('proprietarios.store');
        Route::post('proprietarios/inline', [ProprietarioController::class, 'storeInline'])->name('proprietarios.inline');
        Route::get('proprietarios/{proprietario}/editar', [ProprietarioController::class, 'edit'])->name('proprietarios.edit');
        Route::put('proprietarios/{proprietario}', [ProprietarioController::class, 'update'])->name('proprietarios.update');
        Route::delete('proprietarios/{proprietario}', [ProprietarioController::class, 'destroy'])->name('proprietarios.destroy');
    });

    // Endpoint JSON: contas bancárias por vínculo — admin e proprietário (próprio)
    Route::middleware(['role:admin,proprietario'])->group(function () {
        Route::get('vinculos/{vinculo}/dados-bancarios', [DadosBancariosController::class, 'byVinculo'])->name('vinculos.dados-bancarios');
    });

    // Visualização: admin e proprietário (APÓS rotas estáticas para evitar conflito com {imovel})
    Route::middleware(['role:admin,proprietario'])->group(function () {
        Route::get('imoveis', [ImovelController::class, 'index'])->name('imoveis.index');
        Route::get('imoveis/{imovel}', [ImovelController::class, 'show'])->name('imoveis.show');
    });

    // ----------------------------------------------------------------
    // Contratos — todos os papéis veem, admin gerencia
    // ----------------------------------------------------------------
    // Gestão: apenas admin (rotas estáticas ANTES das parametrizadas)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('contratos/criar', [ContratoController::class, 'create'])->name('contratos.create');
        Route::get('contratos/imoveis-disponiveis', [ContratoController::class, 'imoveisDisponiveis'])->name('contratos.imoveis-disponiveis');
        Route::post('contratos', [ContratoController::class, 'store'])->name('contratos.store');
        Route::get('contratos/{contrato}/editar', [ContratoController::class, 'edit'])->name('contratos.edit');
        Route::put('contratos/{contrato}', [ContratoController::class, 'update'])->name('contratos.update');
        Route::patch('contratos/{contrato}/encerrar', [ContratoController::class, 'encerrar'])->name('contratos.encerrar');
        Route::patch('contratos/{contrato}/cancelar', [ContratoController::class, 'cancelar'])->name('contratos.cancelar');

        // Fiadores
        Route::post('contratos/{contrato}/fiadores', [FiadorController::class, 'store']);
        Route::put('contratos/{contrato}/fiadores/{fiador}', [FiadorController::class, 'update']);
        Route::delete('contratos/{contrato}/fiadores/{fiador}', [FiadorController::class, 'destroy']);

        // Inquilinos do contrato (gerenciador na edição: pivot contrato_inquilinos)
        Route::post('contratos/{contrato}/inquilinos', [ContratoInquilinoController::class, 'store'])->name('contratos.inquilinos.store');
        Route::put('contratos/{contrato}/inquilinos/{contratoInquilino}', [ContratoInquilinoController::class, 'update'])->name('contratos.inquilinos.update');
        Route::delete('contratos/{contrato}/inquilinos/{contratoInquilino}', [ContratoInquilinoController::class, 'destroy'])->name('contratos.inquilinos.destroy');

        // Itens de cobrança do contrato (modelo unificado)
        Route::get('contratos/{contrato}/itens-cobranca', [ItemCobrancaController::class, 'index'])->name('itens-cobranca.index');
        Route::post('contratos/{contrato}/itens-cobranca', [ItemCobrancaController::class, 'store'])->name('itens-cobranca.store');
        Route::patch('itens-cobranca/{itemCobranca}', [ItemCobrancaController::class, 'update'])->name('itens-cobranca.update');
        Route::delete('itens-cobranca/{itemCobranca}', [ItemCobrancaController::class, 'destroy'])->name('itens-cobranca.destroy');

        // Reajustes do contrato (Camada 3 — auditoria estruturada)
        Route::post('contratos/{contrato}/reajustes', [ContratoReajusteController::class, 'store'])->name('contratos.reajustes.store');

        // Inquilinos (CRUD próprio + endpoints JSON)
        Route::get('inquilinos', [InquilinoController::class, 'index'])->name('inquilinos.index');
        Route::get('inquilinos/criar', [InquilinoController::class, 'create'])->name('inquilinos.create');
        Route::get('inquilinos/buscar', [InquilinoController::class, 'buscar'])->name('inquilinos.buscar');
        Route::post('inquilinos', [InquilinoController::class, 'store'])->name('inquilinos.store');
        Route::post('inquilinos/inline', [InquilinoController::class, 'storeInline'])->name('inquilinos.inline');
        Route::get('inquilinos/{inquilino}/editar', [InquilinoController::class, 'edit'])->name('inquilinos.edit');
        Route::put('inquilinos/{inquilino}', [InquilinoController::class, 'update'])->name('inquilinos.update');
        Route::delete('inquilinos/{inquilino}', [InquilinoController::class, 'destroy'])->name('inquilinos.destroy');
    });

    // Visualização: todos os papéis (APÓS rotas estáticas para evitar conflito com {contrato})
    Route::middleware(['role:admin,proprietario,inquilino'])->group(function () {
        Route::get('contratos', [ContratoController::class, 'index'])->name('contratos.index');
        Route::get('contratos/{contrato}', [ContratoController::class, 'show'])->name('contratos.show');
    });

    // ----------------------------------------------------------------
    // Financeiro — Cobranças
    // ----------------------------------------------------------------
    // Gestão: apenas admin (rotas estáticas ANTES das parametrizadas)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('financeiro/faturas/criar', [FaturaController::class, 'create'])->name('faturas.create');
        Route::post('financeiro/faturas', [FaturaController::class, 'store'])->name('faturas.store');
        Route::get('financeiro/faturas/preview-mensais', [FaturaController::class, 'previewMensais'])->name('faturas.preview-mensais');
        Route::post('financeiro/faturas/gerar-mensais', [FaturaController::class, 'gerarMensais'])->name('faturas.gerar-mensais');
        Route::patch('financeiro/faturas/{fatura}/cancelar', [FaturaController::class, 'cancelar'])->name('faturas.cancelar');
        Route::patch('financeiro/faturas/{fatura}/pagamento', [FaturaController::class, 'registrarPagamento'])->name('faturas.pagamento');
    });

    // Upload de comprovante para fatura (admin e inquilino)
    Route::middleware(['role:admin,inquilino'])->group(function () {
        Route::post('financeiro/faturas/{fatura}/comprovantes', [ComprovanteController::class, 'storeForFatura']);
    });

    // Visualização de faturas: todos os papéis (APÓS rotas estáticas para evitar conflito com {fatura})
    Route::middleware(['role:admin,proprietario,inquilino'])->group(function () {
        Route::get('financeiro/faturas', [FaturaController::class, 'index'])->name('faturas.index');
        Route::get('financeiro/faturas/{fatura}', [FaturaController::class, 'show'])->name('faturas.show');
    });

    // ----------------------------------------------------------------
    // Financeiro — Repasses
    // ----------------------------------------------------------------
    // Gestão: apenas admin (estáticas ANTES das parametrizadas)
    Route::middleware(['role:admin'])->group(function () {
        Route::patch('financeiro/repasses/confirmar-lote', [RepasseController::class, 'confirmarLote'])->name('repasses.confirmar-lote');
        Route::patch('financeiro/repasses/{repasse}/confirmar', [RepasseController::class, 'confirmar'])->name('repasses.confirmar');
        Route::patch('financeiro/repasses/{repasse}/cancelar', [RepasseController::class, 'cancelar'])->name('repasses.cancelar');

        // Upload de comprovante para repasse e item de cobrança (apenas admin)
        Route::post('financeiro/repasses/{repasse}/comprovantes', [ComprovanteController::class, 'storeForRepasse']);
        Route::post('itens-cobranca/{itemCobranca}/comprovantes', [ComprovanteController::class, 'storeForItemCobranca']);

        // Edição/remoção de comprovante (sem importar owner)
        Route::patch('comprovantes/{comprovante}', [ComprovanteController::class, 'update'])->name('comprovantes.update');
        Route::delete('comprovantes/{comprovante}', [ComprovanteController::class, 'destroy'])->name('comprovantes.destroy');
    });

    // Visualização de repasses: admin e proprietário (APÓS rotas estáticas)
    Route::middleware(['role:admin,proprietario'])->group(function () {
        Route::get('financeiro/repasses', [RepasseController::class, 'index'])->name('repasses.index');
        Route::get('financeiro/repasses/{repasse}', [RepasseController::class, 'show'])->name('repasses.show');
    });

    // ----------------------------------------------------------------
    // Auditoria de emails (apenas admin do tenant)
    // ----------------------------------------------------------------
    Route::middleware(['role:admin'])->group(function () {
        Route::get('emails', [EmailLogController::class, 'index'])->name('emails.index');
        Route::get('emails/{emailLog}', [EmailLogController::class, 'show'])->name('emails.show');
    });

    // ----------------------------------------------------------------
    // Dados bancários: admin e proprietário
    // ----------------------------------------------------------------
    Route::middleware(['role:admin,proprietario'])->group(function () {
        Route::get('dados-bancarios', [DadosBancariosController::class, 'index'])->name('dados-bancarios.index');
        Route::post('dados-bancarios', [DadosBancariosController::class, 'store'])->name('dados-bancarios.store');
        Route::put('dados-bancarios/{dadosBancarios}', [DadosBancariosController::class, 'update'])->name('dados-bancarios.update');
        Route::delete('dados-bancarios/{dadosBancarios}', [DadosBancariosController::class, 'destroy'])->name('dados-bancarios.destroy');
    });
});

require __DIR__.'/settings.php';

// ========================================================================
// Rotas do painel super admin — guard 'admin', SEM middleware tenant/role
// ========================================================================
Route::prefix('admin')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // 2FA challenge — sem admin.auth (admin ainda não está autenticado, apenas pendente)
    Route::get('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'show'])->name('admin.two-factor.challenge');
    Route::post('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1')->name('admin.two-factor.verify');

    Route::middleware(['admin.auth'])->group(function () {
        // Minha Conta — acessível antes de configurar 2FA (isenta do admin.require2fa)
        Route::get('minha-conta', [AdminMinhaContaController::class, 'show'])->name('admin.minha-conta');
        Route::post('minha-conta/two-factor', [AdminMinhaContaController::class, 'enableTwoFactor'])->name('admin.minha-conta.2fa.enable');
        Route::post('minha-conta/two-factor/confirm', [AdminMinhaContaController::class, 'confirmTwoFactor'])->name('admin.minha-conta.2fa.confirm');
        Route::delete('minha-conta/two-factor', [AdminMinhaContaController::class, 'disableTwoFactor'])->name('admin.minha-conta.2fa.disable');
        Route::get('minha-conta/two-factor/qr-code', [AdminMinhaContaController::class, 'qrCode'])->name('admin.minha-conta.2fa.qr-code');
        Route::get('minha-conta/two-factor/secret-key', [AdminMinhaContaController::class, 'secretKey'])->name('admin.minha-conta.2fa.secret-key');
        Route::get('minha-conta/two-factor/recovery-codes', [AdminMinhaContaController::class, 'recoveryCodes'])->name('admin.minha-conta.2fa.recovery-codes');
        Route::post('minha-conta/two-factor/recovery-codes', [AdminMinhaContaController::class, 'regenerateRecoveryCodes'])->name('admin.minha-conta.2fa.regenerate');

        Route::middleware(['admin.require2fa'])->group(function () {
            Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

            // Assinantes
            Route::get('assinantes', [AdminAssinanteController::class, 'index'])->name('admin.assinantes.index');
            Route::get('assinantes/{tenant}', [AdminAssinanteController::class, 'show'])->name('admin.assinantes.show');
            Route::patch('assinantes/{tenant}/cortesia', [AdminAssinanteController::class, 'toggleCortesia'])->name('admin.assinantes.toggle-cortesia');
            Route::patch('assinantes/{tenant}/suspender', [AdminAssinanteController::class, 'suspender'])->name('admin.assinantes.suspender');
            Route::patch('assinantes/{tenant}/reativar', [AdminAssinanteController::class, 'reativar'])->name('admin.assinantes.reativar');
            Route::patch('assinantes/{tenant}/cancelar', [AdminAssinanteController::class, 'cancelar'])->name('admin.assinantes.cancelar');
            Route::patch('assinantes/{tenant}/desbloquear', [AdminAssinanteController::class, 'desbloquear'])->name('admin.assinantes.desbloquear');

            // Usuários da plataforma
            Route::get('usuarios', [AdminUsuarioController::class, 'index'])->name('admin.usuarios.index');
            Route::get('usuarios/{user}', [AdminUsuarioController::class, 'show'])->name('admin.usuarios.show');

            // Configurações
            Route::get('configuracoes', [AdminConfiguracaoController::class, 'index'])->name('admin.configuracoes.index');
            Route::put('configuracoes', [AdminConfiguracaoController::class, 'update'])->name('admin.configuracoes.update');

            // Ação manual
            Route::post('executar-inadimplencia', [AdminDashboardController::class, 'executarInadimplencia'])->name('admin.executar-inadimplencia');

            // Mensagens de contato
            Route::get('mensagens', [AdminMensagemController::class, 'index'])->name('admin.mensagens.index');
            Route::get('mensagens/{mensagem}', [AdminMensagemController::class, 'show'])->name('admin.mensagens.show');
            Route::patch('mensagens/{mensagem}/lida', [AdminMensagemController::class, 'marcarLida'])->name('admin.mensagens.lida');
            Route::patch('mensagens/{mensagem}/respondida', [AdminMensagemController::class, 'marcarRespondida'])->name('admin.mensagens.respondida');

            // Templates de email
            Route::get('templates', [AdminTemplateController::class, 'index'])->name('admin.templates.index');
            Route::get('templates/{template}/editar', [AdminTemplateController::class, 'edit'])->name('admin.templates.edit');
            Route::put('templates/{template}', [AdminTemplateController::class, 'update'])->name('admin.templates.update');
            Route::patch('templates/{template}/toggle-status', [AdminTemplateController::class, 'toggleStatus']);
            Route::post('templates/{template}/enviar-teste', [AdminTemplateController::class, 'enviarTeste']);
            Route::post('templates/{template}/preview', [AdminTemplateController::class, 'preview']);

            // Páginas institucionais
            Route::get('paginas', [AdminPaginaController::class, 'index'])->name('admin.paginas.index');
            Route::get('paginas/{pagina}/editar', [AdminPaginaController::class, 'edit'])->name('admin.paginas.edit');
            Route::put('paginas/{pagina}', [AdminPaginaController::class, 'update'])->name('admin.paginas.update');

            // Auditoria de emails
            Route::get('emails', [AdminEmailLogController::class, 'index'])->name('admin.emails.index');
            Route::get('emails/{emailLog}', [AdminEmailLogController::class, 'show']);
            Route::post('emails/{emailLog}/reenviar', [AdminEmailLogController::class, 'reenviar']);
        });
    });
});
