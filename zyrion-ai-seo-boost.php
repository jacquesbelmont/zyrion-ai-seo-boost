<?php
/**
 * Plugin Name: Zyrion AI & SEO Boost
 * Description: Complementa o Yoast SEO (versão gratuita) sem duplicar o que ele já faz nativamente: (1) libera crawlers de IA no robots.txt, (2) enriquece o schema Organization que o Yoast já gera (sem criar um segundo objeto), (3) gera sitemap de notícias no padrão Google News, (4) avisa o IndexNow (Bing/Yandex) a cada publicação — alternativas gratuitas aos recursos "News SEO" e "Indexar agora" do Yoast Premium.
 * Version: 2.0
 * Author: Zyrion
 * Text Domain: zyrion-ai-seo-boost
 *
 * IMPORTANTE: o llms.txt NÃO está mais aqui — o Yoast SEO já gera isso nativamente
 * (Yoast SEO > Configurações > Recursos do site > Ferramentas de IA > llms.txt).
 * Use o botão "Personalizar arquivo llms.txt" de lá em vez de código.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZYRION_SITE_NAME', 'Zyrion');
define('ZYRION_SITE_URL', 'https://zyrionbrazil.com/');
define('ZYRION_LOGO_URL', 'https://zyrionbrazil.com/wp-content/uploads/2025/12/cropped-ZYRION-LOGO-480-x-200-px-2-e1764911168292.png');
define('ZYRION_EDITORIAL_POLICY_URL', 'https://zyrionbrazil.com/politica-editorial/');
define('ZYRION_INDEXNOW_KEY', '127be953bddee758018fff1d62038e83');

// 1. robots.txt: liberar crawlers de IA + apontar para sitemaps
add_filter('robots_txt', function ($output, $public) {
    if ('1' != $public) {
        return $output;
    }

    $bots = [
        'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai',
        'PerplexityBot', 'Google-Extended', 'CCBot',
    ];

    $extra = "\n# Crawlers de IA — liberados explicitamente\n";
    foreach ($bots as $bot) {
        $extra .= "User-agent: {$bot}\nAllow: /\n\n";
    }

    $extra .= "Sitemap: " . home_url('/sitemap_index.xml') . "\n";
    $extra .= "Sitemap: " . home_url('/news-sitemap.xml') . "\n";
    $extra .= "# Resumo do site para IA: " . home_url('/llms.txt') . "\n";

    return $output . $extra;
}, 10, 2);

// 2. Enriquecer o schema Organization que o Yoast já gera (sem duplicar)
add_filter('wpseo_schema_organization', function ($data) {
    $existing_same_as = $data['sameAs'] ?? [];
    $extra_same_as = [
        'https://www.instagram.com/zyrionbrazil/',
        'https://x.com/zyrionbrazil',
        'https://www.facebook.com/zyrionbrazil/',
        'https://www.threads.com/@zyrionbrazil',
        'https://www.tiktok.com/@zyrionbrazil',
        'https://www.youtube.com/@zyrionbrazil',
    ];
    $data['sameAs'] = array_values(array_unique(array_merge($existing_same_as, $extra_same_as)));
    $data['@type'] = 'NewsMediaOrganization';
    $data['knowsAbout'] = ['Notícias do Brasil', 'Atualidades', 'Jornalismo digital'];
    $data['publishingPrinciples'] = ZYRION_EDITORIAL_POLICY_URL;

    return $data;
});

// 3. News Sitemap gratuito em /news-sitemap.xml (só últimas 48h)
add_action('init', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path !== 'news-sitemap.xml') {
        return;
    }

    header('Content-Type: application/xml; charset=utf-8');

    $posts = get_posts([
        'numberposts' => 1000,
        'post_status' => 'publish',
        'date_query'  => [['after' => '48 hours ago']],
    ]);

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

    foreach ($posts as $post) {
        $loc   = esc_url(get_permalink($post));
        $title = esc_html(get_the_title($post));
        $date  = get_the_date('c', $post);

        echo "  <url>\n";
        echo "    <loc>{$loc}</loc>\n";
        echo "    <news:news>\n";
        echo "      <news:publication>\n";
        echo "        <news:name>" . esc_html(ZYRION_SITE_NAME) . "</news:name>\n";
        echo "        <news:language>pt</news:language>\n";
        echo "      </news:publication>\n";
        echo "      <news:publication_date>{$date}</news:publication_date>\n";
        echo "      <news:title>{$title}</news:title>\n";
        echo "    </news:news>\n";
        echo "  </url>\n";
    }

    echo '</urlset>';
    exit;
});

// 4. IndexNow gratuito: arquivo de verificação
add_action('init', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path !== ZYRION_INDEXNOW_KEY . '.txt') {
        return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo ZYRION_INDEXNOW_KEY;
    exit;
});

// 4b. IndexNow gratuito: ping a cada publicação
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if ($post->post_type !== 'post') {
        return;
    }
    if ($new_status !== 'publish') {
        return;
    }

    wp_remote_post('https://api.indexnow.org/indexnow', [
        'headers'  => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'     => wp_json_encode([
            'host'        => parse_url(home_url(), PHP_URL_HOST),
            'key'         => ZYRION_INDEXNOW_KEY,
            'keyLocation' => home_url('/' . ZYRION_INDEXNOW_KEY . '.txt'),
            'urlList'     => [get_permalink($post)],
        ]),
        'timeout'  => 5,
        'blocking' => false,
    ]);
}, 10, 3);
