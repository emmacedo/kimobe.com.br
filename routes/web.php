<?php

use App\Http\Controllers\Admin\AdminAssinanteController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminConfiguracaoController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmailLogController;
use App\Http\Controllers\Admin\AdminFaturamentoController;
use App\Http\Controllers\Admin\AdminMensagemController;
use App\Http\Controllers\Admin\AdminMinhaContaController;
use App\Http\Controllers\Admin\AdminPaginaController;
use App\Http\Controllers\Admin\AdminPlanoController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTwoFactorChallengeController;
use App\Http\Controllers\Admin\AdminUsuarioController;
use App\Http\Controllers\CobrancaComprovanteController;
use App\Http\Controllers\CobrancaController;
use App\Http\Controllers\CobrancaItemExtraController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ContratoResponsabilidadeController;
use App\Http\Controllers\DadosBancariosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\FiadorController;
use App\Http\Controllers\ImovelController;
use App\Http\Controllers\ImovelFotoController;
use App\Http\Controllers\PaginaInstitucionalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\RepasseComprovanteController;
use App\Http\Controllers\RepasseController;
use App\Http\Controllers\TenantSelectionController;
use App\Http\Controllers\TitularidadeController;
use Illuminate\Support\Facades\Route;

// Pixel de rastreamento de email (rota pública sem auth)
Route::get('/email/pixel/{token}', [EmailTrackingController::class, 'pixel'])->name('email.pixel');

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
Route::middleware(['auth', 'verified', 'tenant', 'tenant.ativo'])->group(function () {

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
        Route::post('contratos', [ContratoController::class, 'store'])->name('contratos.store');
        Route::get('contratos/{contrato}/editar', [ContratoController::class, 'edit'])->name('contratos.edit');
        Route::put('contratos/{contrato}', [ContratoController::class, 'update'])->name('contratos.update');
        Route::patch('contratos/{contrato}/encerrar', [ContratoController::class, 'encerrar'])->name('contratos.encerrar');
        Route::patch('contratos/{contrato}/cancelar', [ContratoController::class, 'cancelar'])->name('contratos.cancelar');

        // Responsabilidades
        Route::post('contratos/{contrato}/responsabilidades', [ContratoResponsabilidadeController::class, 'store']);
        Route::put('contratos/{contrato}/responsabilidades/{responsabilidade}', [ContratoResponsabilidadeController::class, 'update']);
        Route::delete('contratos/{contrato}/responsabilidades/{responsabilidade}', [ContratoResponsabilidadeController::class, 'destroy']);

        // Fiadores
        Route::post('contratos/{contrato}/fiadores', [FiadorController::class, 'store']);
        Route::put('contratos/{contrato}/fiadores/{fiador}', [FiadorController::class, 'update']);
        Route::delete('contratos/{contrato}/fiadores/{fiador}', [FiadorController::class, 'destroy']);
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
        Route::get('financeiro/cobrancas/criar', [CobrancaController::class, 'create'])->name('cobrancas.create');
        Route::post('financeiro/cobrancas', [CobrancaController::class, 'store'])->name('cobrancas.store');
        Route::get('financeiro/cobrancas/preview-mensais', [CobrancaController::class, 'previewMensais'])->name('cobrancas.preview-mensais');
        Route::post('financeiro/cobrancas/gerar-mensais', [CobrancaController::class, 'gerarMensais'])->name('cobrancas.gerar-mensais');
        Route::patch('financeiro/cobrancas/{cobranca}/cancelar', [CobrancaController::class, 'cancelar'])->name('cobrancas.cancelar');
        Route::patch('financeiro/cobrancas/{cobranca}/pagamento', [CobrancaController::class, 'registrarPagamento'])->name('cobrancas.pagamento');

        // Itens extras
        Route::post('financeiro/cobrancas/{cobranca}/itens-extras', [CobrancaItemExtraController::class, 'store']);
        Route::put('financeiro/cobrancas/{cobranca}/itens-extras/{item}', [CobrancaItemExtraController::class, 'update']);
        Route::delete('financeiro/cobrancas/{cobranca}/itens-extras/{item}', [CobrancaItemExtraController::class, 'destroy']);
    });

    // Comprovantes de cobrança: admin e inquilino
    Route::middleware(['role:admin,inquilino'])->group(function () {
        Route::post('financeiro/cobrancas/{cobranca}/comprovantes', [CobrancaComprovanteController::class, 'store']);
        Route::patch('financeiro/cobrancas/{cobranca}/comprovantes/{comprovante}', [CobrancaComprovanteController::class, 'update']);
        Route::delete('financeiro/cobrancas/{cobranca}/comprovantes/{comprovante}', [CobrancaComprovanteController::class, 'destroy']);
    });

    // Visualização de cobranças: todos os papéis (APÓS rotas estáticas para evitar conflito com {cobranca})
    Route::middleware(['role:admin,proprietario,inquilino'])->group(function () {
        Route::get('financeiro/cobrancas', [CobrancaController::class, 'index'])->name('cobrancas.index');
        Route::get('financeiro/cobrancas/{cobranca}', [CobrancaController::class, 'show'])->name('cobrancas.show');
    });

    // ----------------------------------------------------------------
    // Financeiro — Repasses
    // ----------------------------------------------------------------
    // Gestão: apenas admin (estáticas ANTES das parametrizadas)
    Route::middleware(['role:admin'])->group(function () {
        Route::patch('financeiro/repasses/confirmar-lote', [RepasseController::class, 'confirmarLote'])->name('repasses.confirmar-lote');
        Route::patch('financeiro/repasses/{repasse}/confirmar', [RepasseController::class, 'confirmar'])->name('repasses.confirmar');
        Route::patch('financeiro/repasses/{repasse}/cancelar', [RepasseController::class, 'cancelar'])->name('repasses.cancelar');

        // Comprovantes de repasse
        Route::post('financeiro/repasses/{repasse}/comprovantes', [RepasseComprovanteController::class, 'store']);
        Route::patch('financeiro/repasses/{repasse}/comprovantes/{comprovante}', [RepasseComprovanteController::class, 'update']);
        Route::delete('financeiro/repasses/{repasse}/comprovantes/{comprovante}', [RepasseComprovanteController::class, 'destroy']);
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

            // Planos
            Route::get('planos', [AdminPlanoController::class, 'index'])->name('admin.planos.index');
            Route::post('planos', [AdminPlanoController::class, 'store'])->name('admin.planos.store');
            Route::put('planos/{plano}', [AdminPlanoController::class, 'update'])->name('admin.planos.update');
            Route::patch('planos/{plano}/toggle-status', [AdminPlanoController::class, 'toggleStatus'])->name('admin.planos.toggle-status');
            Route::delete('planos/{plano}', [AdminPlanoController::class, 'destroy'])->name('admin.planos.destroy');

            // Assinantes
            Route::get('assinantes', [AdminAssinanteController::class, 'index'])->name('admin.assinantes.index');
            Route::get('assinantes/{tenant}', [AdminAssinanteController::class, 'show'])->name('admin.assinantes.show');
            Route::patch('assinantes/{tenant}/plano', [AdminAssinanteController::class, 'alterarPlano'])->name('admin.assinantes.alterar-plano');
            Route::patch('assinantes/{tenant}/cortesia', [AdminAssinanteController::class, 'toggleCortesia'])->name('admin.assinantes.toggle-cortesia');
            Route::patch('assinantes/{tenant}/suspender', [AdminAssinanteController::class, 'suspender'])->name('admin.assinantes.suspender');
            Route::patch('assinantes/{tenant}/reativar', [AdminAssinanteController::class, 'reativar'])->name('admin.assinantes.reativar');
            Route::patch('assinantes/{tenant}/cancelar', [AdminAssinanteController::class, 'cancelar'])->name('admin.assinantes.cancelar');
            Route::patch('assinantes/{tenant}/desbloquear', [AdminAssinanteController::class, 'desbloquear'])->name('admin.assinantes.desbloquear');

            // Usuários da plataforma
            Route::get('usuarios', [AdminUsuarioController::class, 'index'])->name('admin.usuarios.index');
            Route::get('usuarios/{user}', [AdminUsuarioController::class, 'show'])->name('admin.usuarios.show');

            // Faturamento
            Route::get('faturamento', [AdminFaturamentoController::class, 'index'])->name('admin.faturamento.index');
            Route::get('faturamento/preview', [AdminFaturamentoController::class, 'preview'])->name('admin.faturamento.preview');
            Route::post('faturamento/gerar', [AdminFaturamentoController::class, 'gerar'])->name('admin.faturamento.gerar');
            Route::patch('faturamento/{fatura}/pagamento', [AdminFaturamentoController::class, 'registrarPagamento'])->name('admin.faturamento.pagamento');
            Route::patch('faturamento/{fatura}/cancelar', [AdminFaturamentoController::class, 'cancelar'])->name('admin.faturamento.cancelar');

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
