import { Head, usePage } from '@inertiajs/react';

interface SharedProps {
    app_url?: string;
    [key: string]: unknown;
}

interface SeoHeadProps {
    title: string;
    description: string;
    image?: string;
    type?: 'website' | 'article' | 'product';
    noindex?: boolean;
    children?: React.ReactNode;
}

export function SeoHead({ title, description, image, type = 'website', noindex = false, children }: SeoHeadProps) {
    const page = usePage<SharedProps>();
    const appUrl = page.props.app_url ?? '';
    const path = page.url.split('?')[0];
    const canonical = appUrl ? `${appUrl}${path}` : path;
    const ogImage = image ?? `${appUrl}/logo-kimobe.png`;

    return (
        <Head title={title}>
            <meta name="description" content={description} />
            {noindex ? (
                <meta name="robots" content="noindex, nofollow" />
            ) : (
                <meta name="robots" content="index, follow" />
            )}
            <link rel="canonical" href={canonical} />

            <meta property="og:type" content={type} />
            <meta property="og:site_name" content="Kimobe" />
            <meta property="og:locale" content="pt_BR" />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={canonical} />
            <meta property="og:image" content={ogImage} />

            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={ogImage} />

            {children}
        </Head>
    );
}
