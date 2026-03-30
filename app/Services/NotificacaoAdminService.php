<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Repasse;

/**
 * Encapsula o envio de notificações do módulo admin (admin → proprietário/inquilino).
 * Todos os envios passam pelo EmailNotificationService.
 */
class NotificacaoAdminService
{
    public function __construct(
        protected EmailNotificationService $emailService,
    ) {}

    // ======================== INQUILINO ========================

    public function notificarCobrancaGerada(Cobranca $cobranca): void
    {
        $contrato = $cobranca->contrato;
        $contrato->loadMissing(['inquilino.user', 'imovel']);

        $email = $contrato->getEmailInquilino();
        if (! $email) return;

        $this->emailService->enviar('admin.cobranca_gerada', $email, $contrato->getNomeInquilino() ?? '', [
            'nome' => $contrato->getNomeInquilino(),
            'imovel_endereco' => $contrato->getEnderecoResumido(),
            'referencia' => $cobranca->referencia,
            'valor_total' => number_format($cobranca->valor_total, 2, ',', '.'),
            'data_vencimento' => $cobranca->data_vencimento->format('d/m/Y'),
        ], $cobranca->tenant_id);
    }

    public function notificarPagamentoInquilino(Cobranca $cobranca): void
    {
        $contrato = $cobranca->contrato;
        $contrato->loadMissing(['inquilino.user', 'imovel']);

        $email = $contrato->getEmailInquilino();
        if (! $email) return;

        $this->emailService->enviar('admin.confirmacao_pagamento_inquilino', $email, $contrato->getNomeInquilino() ?? '', [
            'nome' => $contrato->getNomeInquilino(),
            'imovel_endereco' => $contrato->getEnderecoResumido(),
            'referencia' => $cobranca->referencia,
            'valor_pago' => number_format($cobranca->valor_pago, 2, ',', '.'),
            'data_pagamento' => $cobranca->data_pagamento?->format('d/m/Y'),
        ], $cobranca->tenant_id);
    }

    // ======================== PROPRIETÁRIO ========================

    public function notificarRepassePendente(Repasse $repasse): void
    {
        $repasse->loadMissing(['titularidade.vinculo.user', 'cobranca.contrato.imovel']);

        $user = $repasse->titularidade->vinculo->user;
        $contrato = $repasse->cobranca->contrato;

        $this->emailService->enviar('admin.repasse_pendente', $user->email, $user->name, [
            'nome' => $user->name,
            'imovel_endereco' => $contrato->getEnderecoResumido(),
            'referencia' => $repasse->cobranca->referencia,
            'valor_liquido' => number_format($repasse->valor_liquido, 2, ',', '.'),
            'data_prevista' => $repasse->data_prevista->format('d/m/Y'),
        ], $repasse->tenant_id);
    }

    public function notificarRepasseRealizado(Repasse $repasse): void
    {
        $repasse->loadMissing(['titularidade.vinculo.user', 'titularidade.dadosBancarios', 'cobranca.contrato.imovel']);

        $user = $repasse->titularidade->vinculo->user;
        $contrato = $repasse->cobranca->contrato;
        $banco = $repasse->titularidade->dadosBancarios;

        $this->emailService->enviar('admin.repasse_realizado', $user->email, $user->name, [
            'nome' => $user->name,
            'imovel_endereco' => $contrato->getEnderecoResumido(),
            'referencia' => $repasse->cobranca->referencia,
            'valor_liquido' => number_format($repasse->valor_liquido, 2, ',', '.'),
            'data_realizada' => $repasse->data_realizada?->format('d/m/Y'),
            'banco_nome' => $banco?->banco_nome ?? '—',
            'conta' => $banco?->conta ?? '—',
        ], $repasse->tenant_id);
    }

    public function notificarNovoContratoProprietarios(Contrato $contrato): void
    {
        $contrato->loadMissing(['imovel', 'inquilino.user']);
        $titulares = $contrato->imovel->getTitularesParaNotificacao();

        foreach ($titulares as $t) {
            $this->emailService->enviar('admin.novo_contrato_proprietario', $t['email'], $t['nome'], [
                'nome' => $t['nome'],
                'imovel_endereco' => $contrato->getEnderecoResumido(),
                'inquilino_nome' => $contrato->getNomeInquilino(),
                'valor_aluguel' => number_format($contrato->valor_aluguel, 2, ',', '.'),
                'data_inicio' => $contrato->data_inicio->format('d/m/Y'),
                'data_fim' => $contrato->data_fim->format('d/m/Y'),
            ], $contrato->tenant_id);
        }
    }

    public function notificarContratoEncerradoProprietarios(Contrato $contrato): void
    {
        $contrato->loadMissing(['imovel', 'inquilino.user']);
        $titulares = $contrato->imovel->getTitularesParaNotificacao();

        foreach ($titulares as $t) {
            $this->emailService->enviar('admin.contrato_encerrado_proprietario', $t['email'], $t['nome'], [
                'nome' => $t['nome'],
                'imovel_endereco' => $contrato->getEnderecoResumido(),
                'inquilino_nome' => $contrato->getNomeInquilino(),
                'data_encerramento' => now()->format('d/m/Y'),
            ], $contrato->tenant_id);
        }
    }

    public function notificarComprovanteEnviado(Cobranca $cobranca, string $inquilinoNome): void
    {
        $cobranca->loadMissing(['contrato.imovel']);
        $tenant = \App\Models\Tenant::find($cobranca->tenant_id);
        if (! $tenant) return;

        foreach ($tenant->getAdminEmails() as $adminEmail) {
            $this->emailService->enviar('admin.comprovante_enviado', $adminEmail, 'Admin', [
                'inquilino_nome' => $inquilinoNome,
                'imovel_endereco' => $cobranca->contrato->getEnderecoResumido(),
                'referencia' => $cobranca->referencia,
                'data_envio' => now()->format('d/m/Y H:i'),
            ], $cobranca->tenant_id);
        }
    }
}
