<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    /**
     * Retorna pixel 1x1 transparente e marca o email como aberto.
     * Sempre retorna a imagem, mesmo com token inválido (segurança).
     */
    public function pixel(Request $request, string $token): Response
    {
        // Buscar e marcar como aberto (silencioso se não encontrar)
        $log = EmailLog::where('token_rastreamento', $token)->first();
        if ($log) {
            $log->marcarAberto(
                $request->ip() ?? '',
                $request->userAgent() ?? '',
            );
        }

        // GIF transparente 1x1 pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
