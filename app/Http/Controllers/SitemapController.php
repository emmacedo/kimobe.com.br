<?php

namespace App\Http\Controllers;

use App\Models\PaginaInstitucional;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim(config('app.url'), '/');
        $now = now()->toAtomString();

        $urls = [
            ['loc' => $base.'/', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $base.'/planos', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => $base.'/faq', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $base.'/contato', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $base.'/registro', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
        ];

        $paginas = PaginaInstitucional::query()
            ->whereIn('slug', ['termos-de-uso', 'politica-de-privacidade'])
            ->get(['slug', 'updated_at']);

        foreach ($paginas as $pagina) {
            $path = $pagina->slug === 'termos-de-uso' ? '/termos-de-uso' : '/politica-de-privacidade';
            $urls[] = [
                'loc' => $base.$path,
                'lastmod' => optional($pagina->updated_at)->toAtomString() ?? $now,
                'changefreq' => 'yearly',
                'priority' => '0.4',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1)."</loc>\n";
            $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
