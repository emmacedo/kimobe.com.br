<?php

use App\Http\Controllers\Settings\SettingsPlanoController;
use App\Http\Controllers\Settings\SettingsProfileController;
use App\Http\Controllers\Settings\SettingsSegurancaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])->prefix('settings')->group(function () {
    Route::redirect('/', '/settings/perfil');

    // Perfil — todos os papéis
    Route::get('perfil', [SettingsProfileController::class, 'perfil'])->name('settings.perfil');
    Route::put('perfil', [SettingsProfileController::class, 'atualizarPerfil'])->name('settings.perfil.update');

    // Empresa — apenas admin
    Route::middleware(['role:admin'])->group(function () {
        Route::get('empresa', [SettingsProfileController::class, 'empresa'])->name('settings.empresa');
        Route::put('empresa', [SettingsProfileController::class, 'atualizarEmpresa'])->name('settings.empresa.update');
        Route::put('empresa/endereco', [SettingsProfileController::class, 'atualizarEnderecoEmpresa'])->name('settings.empresa.endereco.update');
        Route::put('empresa/contato', [SettingsProfileController::class, 'atualizarContatoEmpresa'])->name('settings.empresa.contato.update');
    });

    // Plano — apenas admin
    Route::middleware(['role:admin'])->group(function () {
        Route::get('plano', [SettingsPlanoController::class, 'index'])->name('settings.plano');
        Route::post('plano', [SettingsPlanoController::class, 'alterarPlano'])->name('settings.plano.update');
    });

    // Segurança — todos os papéis
    Route::get('seguranca', [SettingsSegurancaController::class, 'index'])->name('settings.seguranca');
    Route::put('seguranca/senha', [SettingsSegurancaController::class, 'alterarSenha'])
        ->middleware('throttle:6,1')
        ->name('settings.seguranca.senha');
});
