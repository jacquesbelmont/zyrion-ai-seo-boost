<?php
/**
 * Plugin Name: Zyrion AI & SEO Boost
 * Description: Complementa o Yoast SEO (versão gratuita) sem duplicar o que ele já faz nativamente: (1) libera crawlers de IA no robots.txt, (2) enriquece o schema Organization que o Yoast já gera (sem criar um segundo objeto), (3) gera sitemap de notícias no padrão Google News, (4) avisa o IndexNow (Bing/Yandex) a cada publicação — alternativas gratuitas aos recursos "News SEO" e "Indexar agora" do Yoast Premium. Inclui painel admin com configurações, log do IndexNow e módulo de performance.
 * Version: 3.0
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

// ---------------------------------------------------------------------------
// DEFAULTS & OPTIONS
// ---------------------------------------------------------------------------

define('ZYRION_OPTIONS_KEY', 'zyrion_seo_options');
define('ZYRION_LOG_KEY', 'zyrion_indexnow_log');

function zyrion_defaults() {
    return [
        'site_name'              => 'Zyrion',
        'indexnow_key'           => '127be953bddee758018fff1d62038e83',
        'editorial_policy_url'   => 'https://zyrionbrazil.com/politica-editorial/',
        'corrections_url'        => 'https://zyrionbrazil.com/corrigir-erros/',
        'same_as'                => [
            'https://www.instagram.com/zyrionbrazil/',
            'https://x.com/zyrionbrazil',
            'https://www.facebook.com/zyrionbrazil/',
            'https://www.threads.com/@zyrionbrazil',
            'https://www.tiktok.com/@zyrionbrazil',
            'https://www.youtube.com/@zyrionbrazil',
        ],
        'module_robots'          => true,
        'module_schema'          => true,
        'module_sitemap'         => true,
        'module_indexnow'        => true,
        'perf_remove_emojis'     => true,
        'perf_remove_embeds'     => false,
        'perf_remove_jqmigrate'  => false,
        'perf_clean_head'        => false,
        'perf_remove_versions'   => false,
        'perf_disable_xmlrpc'    => false,
    ];
}

function zyrion_get_options() {
    return wp_parse_args(get_option(ZYRION_OPTIONS_KEY, []), zyrion_defaults());
}

// Retrocompatibilidade: constantes como fallback
define('ZYRION_SITE_URL', 'https://zyrionbrazil.com/');
define('ZYRION_LOGO_URL', 'https://zyrionbrazil.com/wp-content/uploads/2025/12/cropped-ZYRION-LOGO-480-x-200-px-2-e1764911168292.png');

// ---------------------------------------------------------------------------
// AUTO-UPDATE VIA GITHUB
// ---------------------------------------------------------------------------

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_file    = 'zyrion-ai-seo-boost/zyrion-ai-seo-boost.php';
    $current_version = $transient->checked[$plugin_file] ?? null;

    if (!$current_version) {
        return $transient;
    }

    $remote = get_transient('zyrion_update_info');
    if ($remote === false) {
        $response = wp_remote_get(
            'https://raw.githubusercontent.com/jacquesbelmont/zyrion-ai-seo-boost/main/update-info.json',
            ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]
        );
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }
        $remote = json_decode(wp_remote_retrieve_body($response));
        set_transient('zyrion_update_info', $remote, 12 * HOUR_IN_SECONDS);
    }

    if ($remote && version_compare($current_version, $remote->version, '<')) {
        $transient->response[$plugin_file] = (object) [
            'slug'        => $remote->slug,
            'plugin'      => $plugin_file,
            'new_version' => $remote->version,
            'url'         => $remote->homepage,
            'package'     => $remote->download_url,
        ];
    }

    return $transient;
});

add_action('upgrader_process_complete', function ($upgrader, $options) {
    if ($options['type'] === 'plugin') {
        delete_transient('zyrion_update_info');
    }
}, 10, 2);

// ---------------------------------------------------------------------------
// ADMIN PANEL
// ---------------------------------------------------------------------------

add_action('admin_menu', function () {
    add_options_page(
        'Zyrion SEO',
        'Zyrion SEO',
        'manage_options',
        'zyrion-seo',
        'zyrion_admin_page'
    );
});

add_action('admin_init', function () {
    if (!isset($_POST['zyrion_save_settings'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    check_admin_referer('zyrion_save_settings_nonce');

    $opts = zyrion_get_options();

    $opts['site_name']            = sanitize_text_field($_POST['site_name'] ?? '');
    $opts['indexnow_key']         = sanitize_text_field($_POST['indexnow_key'] ?? '');
    $opts['editorial_policy_url'] = esc_url_raw($_POST['editorial_policy_url'] ?? '');
    $opts['corrections_url']      = esc_url_raw($_POST['corrections_url'] ?? '');

    $raw_same_as   = sanitize_textarea_field($_POST['same_as'] ?? '');
    $same_as_lines = array_filter(array_map('trim', explode("\n", $raw_same_as)));
    $opts['same_as'] = array_values(array_map('esc_url_raw', $same_as_lines));

    $toggles = [
        'module_robots', 'module_schema', 'module_sitemap', 'module_indexnow',
        'perf_remove_emojis', 'perf_remove_embeds', 'perf_remove_jqmigrate',
        'perf_clean_head', 'perf_remove_versions', 'perf_disable_xmlrpc',
    ];
    foreach ($toggles as $key) {
        $opts[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    update_option(ZYRION_OPTIONS_KEY, $opts);
    wp_safe_redirect(add_query_arg(['page' => 'zyrion-seo', 'saved' => '1', 'tab' => sanitize_key($_POST['_current_tab'] ?? 'status')], admin_url('options-general.php')));
    exit;
});

add_action('admin_post_zyrion_clear_log', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }
    check_admin_referer('zyrion_clear_log_nonce');
    delete_option(ZYRION_LOG_KEY);
    wp_safe_redirect(add_query_arg(['page' => 'zyrion-seo', 'tab' => 'log', 'cleared' => '1'], admin_url('options-general.php')));
    exit;
});

function zyrion_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $opts    = zyrion_get_options();
    $tab     = sanitize_key($_GET['tab'] ?? 'status');
    $saved   = isset($_GET['saved']);
    $cleared = isset($_GET['cleared']);

    $tabs = [
        'status'  => 'Status',
        'settings' => 'Configurações',
        'log'     => 'Log IndexNow',
    ];
    ?>
    <div class="wrap">
        <h1>Zyrion SEO</h1>

        <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div>
        <?php endif; ?>
        <?php if ($cleared): ?>
            <div class="notice notice-success is-dismissible"><p>Log limpo.</p></div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $slug => $label): ?>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'zyrion-seo', 'tab' => $slug], admin_url('options-general.php'))); ?>"
                   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tab-content" style="margin-top:20px;">
            <?php
            if ($tab === 'status') {
                zyrion_tab_status($opts);
            } elseif ($tab === 'settings') {
                zyrion_tab_settings($opts);
            } elseif ($tab === 'log') {
                zyrion_tab_log();
            }
            ?>
        </div>
    </div>
    <?php
}

function zyrion_status_row($label, $active, $link = null) {
    $icon  = $active ? '&#x2713;' : '&#x2717;';
    $color = $active ? 'green' : '#cc0000';
    echo '<tr>';
    echo '<td style="padding:6px 12px;"><span style="color:' . $color . ';font-weight:bold;font-size:16px;">' . $icon . '</span></td>';
    echo '<td style="padding:6px 12px;">' . esc_html($label) . '</td>';
    if ($link) {
        echo '<td style="padding:6px 12px;"><a href="' . esc_url($link) . '" target="_blank">Ver</a></td>';
    } else {
        echo '<td></td>';
    }
    echo '</tr>';
}

function zyrion_tab_status($opts) {
    ?>
    <h2>Status dos Módulos</h2>
    <table class="widefat" style="max-width:600px;">
        <thead><tr><th style="width:40px;"></th><th>Módulo</th><th></th></tr></thead>
        <tbody>
            <?php
            zyrion_status_row('Crawlers de IA no robots.txt', $opts['module_robots'], home_url('/robots.txt'));
            zyrion_status_row('Schema Organization enriquecido', $opts['module_schema']);
            zyrion_status_row('News Sitemap', $opts['module_sitemap'], home_url('/news-sitemap.xml'));
            zyrion_status_row('IndexNow automático', $opts['module_indexnow']);
            zyrion_status_row('Remover emoji scripts/CSS', $opts['perf_remove_emojis']);
            zyrion_status_row('Remover embed JS', $opts['perf_remove_embeds']);
            zyrion_status_row('Remover jQuery Migrate', $opts['perf_remove_jqmigrate']);
            zyrion_status_row('Limpar wp_head', $opts['perf_clean_head']);
            zyrion_status_row('Remover versão de assets', $opts['perf_remove_versions']);
            zyrion_status_row('Desabilitar XML-RPC', $opts['perf_disable_xmlrpc']);
            ?>
        </tbody>
    </table>
    <p style="margin-top:16px;">
        <a href="<?php echo esc_url(home_url('/sitemap_index.xml')); ?>" target="_blank" class="button">Ver sitemap</a>
        <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" class="button">Ver robots.txt</a>
        <a href="https://validator.schema.org/" target="_blank" class="button">Testar schema</a>
    </p>
    <?php
}

function zyrion_tab_settings($opts) {
    $same_as_text = implode("\n", (array) $opts['same_as']);
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=zyrion-seo')); ?>">
        <?php wp_nonce_field('zyrion_save_settings_nonce'); ?>
        <input type="hidden" name="zyrion_save_settings" value="1">
        <input type="hidden" name="_current_tab" value="settings">

        <h2>Dados do Site</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="site_name">Nome do site</label></th>
                <td><input type="text" id="site_name" name="site_name" value="<?php echo esc_attr($opts['site_name']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="indexnow_key">Chave IndexNow</label></th>
                <td><input type="text" id="indexnow_key" name="indexnow_key" value="<?php echo esc_attr($opts['indexnow_key']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="editorial_policy_url">URL política editorial</label></th>
                <td><input type="url" id="editorial_policy_url" name="editorial_policy_url" value="<?php echo esc_attr($opts['editorial_policy_url']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="corrections_url">URL política de correções</label></th>
                <td><input type="url" id="corrections_url" name="corrections_url" value="<?php echo esc_attr($opts['corrections_url']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="same_as">Redes sociais (sameAs)<br><small>Uma URL por linha</small></label></th>
                <td><textarea id="same_as" name="same_as" rows="8" class="large-text"><?php echo esc_textarea($same_as_text); ?></textarea></td>
            </tr>
        </table>

        <h2>Módulos SEO</h2>
        <table class="form-table" role="presentation">
            <?php
            $seo_toggles = [
                'module_robots'   => 'Crawlers de IA no robots.txt',
                'module_schema'   => 'Schema Organization enriquecido',
                'module_sitemap'  => 'News Sitemap (/news-sitemap.xml)',
                'module_indexnow' => 'IndexNow automático',
            ];
            foreach ($seo_toggles as $key => $label):
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <td>
                    <label>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($opts[$key]); ?>>
                        Ativado
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Performance</h2>
        <p><em>Ative e teste um a um. Todos seguros para Apache/Nginx + Elementor.</em></p>
        <table class="form-table" role="presentation">
            <?php
            $perf_toggles = [
                'perf_remove_emojis'    => 'Remover emoji scripts/CSS <strong>(ativo por padrão)</strong>',
                'perf_remove_embeds'    => 'Remover embed JS (oEmbed)',
                'perf_remove_jqmigrate' => 'Remover jQuery Migrate (frontend)',
                'perf_clean_head'       => 'Limpar wp_head (RSD, wlwmanifest, shortlink, X-Pingback, generator)',
                'perf_remove_versions'  => 'Remover versão de assets (?ver=x.x)',
                'perf_disable_xmlrpc'   => 'Desabilitar XML-RPC (segurança)',
            ];
            foreach ($perf_toggles as $key => $label):
            ?>
            <tr>
                <th scope="row"><?php echo wp_kses($label, ['strong' => []]); ?></th>
                <td>
                    <label>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="0">
                        <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($opts[$key]); ?>>
                        Ativado
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button('Salvar Configurações'); ?>
    </form>
    <?php
}

function zyrion_tab_log() {
    $log = get_option(ZYRION_LOG_KEY, []);
    ?>
    <h2>Log IndexNow</h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
        <input type="hidden" name="action" value="zyrion_clear_log">
        <?php wp_nonce_field('zyrion_clear_log_nonce'); ?>
        <button type="submit" class="button" onclick="return confirm('Limpar todo o log?')">Limpar log</button>
    </form>

    <?php if (empty($log)): ?>
        <p>Nenhum registro no log ainda.</p>
    <?php else: ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>URL enviada</th>
                    <th>Status HTTP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($log) as $entry): ?>
                <tr>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', $entry['time'])); ?></td>
                    <td><a href="<?php echo esc_url($entry['url']); ?>" target="_blank"><?php echo esc_html($entry['url']); ?></a></td>
                    <td><?php echo esc_html($entry['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><small>Exibindo até as últimas 50 entradas.</small></p>
    <?php endif; ?>
    <?php
}

// ---------------------------------------------------------------------------
// HELPER: log IndexNow
// ---------------------------------------------------------------------------

function zyrion_log_indexnow($url, $status) {
    $log   = get_option(ZYRION_LOG_KEY, []);
    $log[] = ['time' => time(), 'url' => $url, 'status' => $status];
    if (count($log) > 50) {
        $log = array_slice($log, -50);
    }
    update_option(ZYRION_LOG_KEY, $log);
}

// ---------------------------------------------------------------------------
// MODULE 1: robots.txt
// ---------------------------------------------------------------------------

add_filter('robots_txt', function ($output, $public) {
    $opts = zyrion_get_options();
    if (empty($opts['module_robots'])) {
        return $output;
    }
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

// ---------------------------------------------------------------------------
// MODULE 2: Schema Organization
// ---------------------------------------------------------------------------

add_filter('wpseo_schema_organization', function ($data) {
    $opts = zyrion_get_options();
    if (empty($opts['module_schema'])) {
        return $data;
    }

    $existing_same_as = $data['sameAs'] ?? [];
    $data['sameAs']   = array_values(array_unique(array_merge($existing_same_as, (array) $opts['same_as'])));
    $data['@type']    = 'NewsMediaOrganization';
    $data['knowsAbout']           = ['Notícias do Brasil', 'Atualidades', 'Jornalismo digital'];
    $data['publishingPrinciples'] = $opts['editorial_policy_url'];
    $data['correctionsPolicy']    = $opts['corrections_url'];

    return $data;
});

// ---------------------------------------------------------------------------
// MODULE 3: News Sitemap
// ---------------------------------------------------------------------------

add_action('init', function () {
    $opts = zyrion_get_options();
    if (empty($opts['module_sitemap'])) {
        return;
    }

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
        echo "        <news:name>" . esc_html($opts['site_name']) . "</news:name>\n";
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

// ---------------------------------------------------------------------------
// MODULE 4: IndexNow — arquivo de verificação
// ---------------------------------------------------------------------------

add_action('init', function () {
    $opts = zyrion_get_options();
    if (empty($opts['module_indexnow'])) {
        return;
    }

    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path !== $opts['indexnow_key'] . '.txt') {
        return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo esc_html($opts['indexnow_key']);
    exit;
});

// ---------------------------------------------------------------------------
// MODULE 4b: IndexNow — ping a cada publicação
// ---------------------------------------------------------------------------

add_action('transition_post_status', function ($new_status, $old_status, $post) {
    $opts = zyrion_get_options();
    if (empty($opts['module_indexnow'])) {
        return;
    }
    if ($post->post_type !== 'post' || $new_status !== 'publish') {
        return;
    }

    $url      = get_permalink($post);
    $response = wp_remote_post('https://api.indexnow.org/indexnow', [
        'headers'  => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'     => wp_json_encode([
            'host'        => parse_url(home_url(), PHP_URL_HOST),
            'key'         => $opts['indexnow_key'],
            'keyLocation' => home_url('/' . $opts['indexnow_key'] . '.txt'),
            'urlList'     => [$url],
        ]),
        'timeout'  => 5,
        'blocking' => true,
    ]);

    $status = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
    zyrion_log_indexnow($url, $status);
}, 10, 3);

// ---------------------------------------------------------------------------
// MODULE 5: Schema Article — isAccessibleForFree
// ---------------------------------------------------------------------------

add_filter('wpseo_schema_article', function ($data) {
    $data['isAccessibleForFree'] = true;
    return $data;
});

// ---------------------------------------------------------------------------
// PERFORMANCE MODULE
// ---------------------------------------------------------------------------

add_action('init', function () {
    $opts = zyrion_get_options();

    // 1. Remover emoji scripts/CSS
    if (!empty($opts['perf_remove_emojis'])) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    // 2. Remover embed JS
    if (!empty($opts['perf_remove_embeds'])) {
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rule, 'embed') !== false) {
                    unset($rules[$rule]);
                }
            }
            return $rules;
        });
    }

    // 3. Remover jQuery Migrate (frontend only)
    if (!empty($opts['perf_remove_jqmigrate']) && !is_admin()) {
        add_action('wp_default_scripts', function ($scripts) {
            if (!is_admin() && isset($scripts->registered['jquery'])) {
                $script = $scripts->registered['jquery'];
                if ($script->deps) {
                    $script->deps = array_diff($script->deps, ['jquery-migrate']);
                }
            }
        });
    }

    // 4. Limpar wp_head
    if (!empty($opts['perf_clean_head'])) {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
        add_action('send_headers', function () {
            header_remove('X-Pingback');
        });
    }

    // 5. Remover versão de assets
    if (!empty($opts['perf_remove_versions'])) {
        add_filter('style_loader_src', 'zyrion_remove_asset_version', 9999);
        add_filter('script_loader_src', 'zyrion_remove_asset_version', 9999);
    }

    // 6. Desabilitar XML-RPC
    if (!empty($opts['perf_disable_xmlrpc'])) {
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', function () { return []; });
        add_action('init', function () {
            if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
                wp_die('XML-RPC desabilitado.', 'XML-RPC desabilitado', ['response' => 403]);
            }
        }, 1);
    }
});

function zyrion_remove_asset_version($src) {
    if (strpos($src, '?ver=') !== false) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
