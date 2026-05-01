<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script src="/js/app-init.js?v={{ filemtime(public_path('js/app-init.js')) }}"></script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ filemtime(public_path('apple-touch-icon.png')) }}">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#0A4F5C">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        {{-- JSON-LD: Organization (sitewide) --}}
        @php
            $__appUrl = rtrim(config('app.url'), '/');
            $__organizationLd = json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Kimobe',
                'url' => $__appUrl,
                'logo' => $__appUrl.'/logo-kimobe.png',
                'description' => 'Plataforma SaaS de gestão de aluguéis para imobiliárias e proprietários. Administre imóveis, contratos, cobranças e repasses em um só lugar.',
                'inLanguage' => 'pt-BR',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'url' => $__appUrl.'/contato',
                    'availableLanguage' => ['Portuguese'],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @endphp
        <script type="application/ld+json">{!! $__organizationLd !!}</script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
