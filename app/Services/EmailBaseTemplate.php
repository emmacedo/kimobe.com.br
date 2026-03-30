<?php

namespace App\Services;

/**
 * Template HTML base para todos os emails do Kimobe.
 * Fornece header, footer e estilo inline com a paleta do sistema.
 */
class EmailBaseTemplate
{
    public static function render(string $conteudo): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kimobe</title>
</head>
<body style="margin:0;padding:0;background-color:#EEF0EF;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EEF0EF;">
<tr><td align="center" style="padding:24px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

<!-- Header -->
<tr><td style="background-color:#0A4F5C;padding:20px 30px;border-radius:10px 10px 0 0;text-align:center;">
<span style="font-size:20px;font-weight:500;color:#E4CC82;letter-spacing:-0.3px;">kimobe</span>
</td></tr>

<!-- Body -->
<tr><td style="background-color:#ffffff;padding:30px;border-left:1px solid #D8DCDA;border-right:1px solid #D8DCDA;">
{$conteudo}
</td></tr>

<!-- Footer -->
<tr><td style="background-color:#F7F8F7;padding:20px 30px;border-radius:0 0 10px 10px;border:1px solid #D8DCDA;border-top:0;text-align:center;">
<p style="margin:0;font-size:12px;color:#8A918E;line-height:1.5;">
Este email foi enviado pelo Kimobe.<br>
© 2026 Kimobe. Todos os direitos reservados.
</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
