<?php
/**
 * Plugin Name:       HOCO Israel — GEO-SEO Optimizer
 * Plugin URI:        https://github.com/lirish1973/Ofnoacomps-CRM-System
 * Description:       מוסיף Organization Schema, Product Schema, תיקון Canonical, Security Headers ו-llms.txt לשיפור נראות ב-AI Search (ChatGPT, Perplexity, Google AIO).
 * Version:     1.0.3
 * Author:            Ofnoacomps
 * Author URI:        https://github.com/lirish1973
 * License:           MIT
 * Text Domain:       hoco-geo-seo
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'HOCO_GEO_SEO_VERSION',     '1.0.3' );
define( 'HOCO_GEO_SEO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HOCO_GEO_SEO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HOCO_GEO_SEO_PLUGIN_FILE', __FILE__ );

/* ────────────────────────────────────────────────────────────
 * Auto-Updater — same GitHub manifest as ofnoacomps-crm
 * ──────────────────────────────────────────────────────────── */
require_once HOCO_GEO_SEO_PLUGIN_DIR . 'includes/class-github-updater.php';

add_action( 'init', function () {
    new Hoco_GEO_GitHub_Updater(
        HOCO_GEO_SEO_PLUGIN_FILE,
        'hoco-geo-seo',
        HOCO_GEO_SEO_VERSION
    );
} );

/* ════════════════════════════════════════════════════════════
 * 1. ORGANIZATION SCHEMA — כל עמוד
 * ══════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'hoco_geo_organization_schema', 1 );
function hoco_geo_organization_schema() {
    $schema = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Organization',
        '@id'           => 'https://www.hoco-israel.co.il/#organization',
        'name'          => 'HOCO Israel',
        'alternateName' => 'הוקו ישראל',
        'description'   => 'HOCO Israel הינה היבואן הרשמי של מוצרי HOCO בישראל. אנו מספקים פתרונות איכותיים ללקוחות עסקיים ופרטיים: מטענים, רמקולים, טאבלטים, שעוני ילדים ואביזרי סלולר מובילים.',
        'url'           => 'https://www.hoco-israel.co.il/',
        'logo'          => [
            '@type' => 'ImageObject',
            'url'   => 'https://www.hoco-israel.co.il/wp-content/uploads/hoco-logo.png',
        ],
        'image'         => 'https://www.hoco-israel.co.il/wp-content/uploads/hoco-logo.png',
        'email'         => 'info@hoco-israel.co.il',
        'address'       => [
            '@type'          => 'PostalAddress',
            'addressCountry' => 'IL',
            'addressLocality' => 'ישראל',
        ],
        'sameAs' => [
            'https://www.hoco.com',
            'https://www.facebook.com/hocoil',
            'https://www.instagram.com/hoco_israel',
        ],
        'foundingDate' => '2015',
        'knowsAbout'   => [
            'מטענים לסלולר',
            'רמקולים אלחוטיים',
            'אביזרי סמארטפון',
            'שעוני ילדים חכמים',
            'טאבלטים',
        ],
    ];
    echo "\n<!-- GEO-SEO v" . HOCO_GEO_SEO_VERSION . ": Organization Schema -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}

/* ════════════════════════════════════════════════════════════
 * 2. WEBSITE SCHEMA + SearchAction
 * ══════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'hoco_geo_website_schema', 2 );
function hoco_geo_website_schema() {
    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        '@id'             => 'https://www.hoco-israel.co.il/#website',
        'url'             => 'https://www.hoco-israel.co.il/',
        'name'            => 'HOCO Israel',
        'description'     => 'יבואן רשמי מוצרי HOCO בישראל',
        'inLanguage'      => 'he-IL',
        'publisher'       => [ '@id' => 'https://www.hoco-israel.co.il/#organization' ],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => 'https://www.hoco-israel.co.il/?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    echo "\n<!-- GEO-SEO: WebSite Schema -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}

/* ════════════════════════════════════════════════════════════
 * 3. PRODUCT SCHEMA — עמוד מוצר בודד (WooCommerce)
 * ══════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'hoco_geo_product_schema', 3 );
function hoco_geo_product_schema() {
    if ( ! is_product() || ! function_exists( 'wc_get_product' ) ) {
        return;
    }
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) {
        return;
    }

    $image_id  = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
    $price     = $product->get_price();
    $sku       = $product->get_sku();

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $product->get_name(),
        'description' => wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() ),
        'sku'         => $sku ?: 'HOCO-' . $product->get_id(),
        'brand'       => [ '@type' => 'Brand', 'name' => 'HOCO' ],
        'manufacturer' => [
            '@type' => 'Organization',
            'name'  => 'HOCO',
            'url'   => 'https://www.hoco.com',
        ],
        'seller' => [ '@id' => 'https://www.hoco-israel.co.il/#organization' ],
        'url'    => get_permalink( $post->ID ),
    ];

    if ( $image_url ) {
        $schema['image'] = $image_url;
    }

    if ( $price ) {
        $schema['offers'] = [
            '@type'          => 'Offer',
            'priceCurrency'  => 'ILS',
            'price'          => $price,
            'availability'   => $product->is_in_stock()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'seller'         => [ '@id' => 'https://www.hoco-israel.co.il/#organization' ],
            'url'            => get_permalink( $post->ID ),
            'priceValidUntil' => date( 'Y-12-31', strtotime( '+1 year' ) ),
        ];
    }

    $avg    = $product->get_average_rating();
    $count  = $product->get_review_count();
    if ( $avg && $count ) {
        $schema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => $avg,
            'reviewCount' => $count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }

    $terms = get_the_terms( $post->ID, 'product_cat' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        $schema['category'] = implode( ', ', wp_list_pluck( $terms, 'name' ) );
    }

    echo "\n<!-- GEO-SEO: Product Schema -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}

/* ════════════════════════════════════════════════════════════
 * 4. ITEMLIST SCHEMA — עמוד הבית (מוצרים אחרונים)
 * ══════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'hoco_geo_homepage_products_schema', 4 );
function hoco_geo_homepage_products_schema() {
    if ( ! is_front_page() || ! function_exists( 'wc_get_products' ) ) {
        return;
    }

    $products = wc_get_products( [
        'limit'   => 8,
        'status'  => 'publish',
        'orderby' => 'date',
        'order'   => 'DESC',
    ] );

    if ( empty( $products ) ) {
        return;
    }

    $items = [];
    foreach ( $products as $product ) {
        $item = [
            '@type'  => 'Product',
            'name'   => $product->get_name(),
            'url'    => get_permalink( $product->get_id() ),
            'brand'  => [ '@type' => 'Brand', 'name' => 'HOCO' ],
            'seller' => [ '@id' => 'https://www.hoco-israel.co.il/#organization' ],
        ];
        $img_id = $product->get_image_id();
        if ( $img_id ) {
            $item['image'] = wp_get_attachment_image_url( $img_id, 'full' );
        }
        $price = $product->get_price();
        if ( $price ) {
            $item['offers'] = [
                '@type'         => 'Offer',
                'priceCurrency' => 'ILS',
                'price'         => $price,
                'availability'  => $product->is_in_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }
        $items[] = $item;
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'מוצרי HOCO Israel',
        'description'     => 'מוצרי HOCO הנמכרים ביותר בישראל — מטענים, רמקולים, שעוני ילדים ואביזרי סלולר',
        'numberOfItems'   => count( $items ),
        'itemListElement' => array_map( function ( $item, $i ) {
            return [ '@type' => 'ListItem', 'position' => $i + 1, 'item' => $item ];
        }, $items, array_keys( $items ) ),
    ];

    echo "\n<!-- GEO-SEO: Homepage ItemList Schema -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}

/* ════════════════════════════════════════════════════════════
 * 5. CANONICAL — www בלבד
 * ══════════════════════════════════════════════════════════ */
add_action( 'wp_head', 'hoco_geo_fix_canonical', 0 );
function hoco_geo_fix_canonical() {
    remove_action( 'wp_head', 'rel_canonical' );

    $canonical = '';
    if ( is_front_page() ) {
        $canonical = 'https://www.hoco-israel.co.il/';
    } elseif ( is_singular() ) {
        $canonical = str_replace( 'https://hoco-israel.co.il', 'https://www.hoco-israel.co.il', get_permalink() );
    } elseif ( is_tax() || is_category() || is_tag() ) {
        $link = get_term_link( get_queried_object() );
        if ( ! is_wp_error( $link ) ) {
            $canonical = str_replace( 'https://hoco-israel.co.il', 'https://www.hoco-israel.co.il', $link );
        }
    } elseif ( is_post_type_archive() ) {
        $link = get_post_type_archive_link( get_post_type() );
        if ( $link ) {
            $canonical = str_replace( 'https://hoco-israel.co.il', 'https://www.hoco-israel.co.il', $link );
        }
    }

    if ( $canonical ) {
        echo "\n<!-- GEO-SEO: Canonical (www-enforced) -->\n";
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
    }
}

/* ════════════════════════════════════════════════════════════
 * 6. SECURITY HEADERS
 * ══════════════════════════════════════════════════════════ */
add_action( 'send_headers', 'hoco_geo_security_headers' );
function hoco_geo_security_headers() {
    if ( headers_sent() ) {
        return;
    }
    header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );

    // CSP — permissive for WordPress + Elementor + WooCommerce
    // unsafe-inline/eval required by Elementor & WooCommerce JS/CSS
    $csp  = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:; ";
    $csp .= "style-src 'self' 'unsafe-inline' https: data:; ";
    $csp .= "img-src 'self' data: https: blob:; ";
    $csp .= "font-src 'self' data: https:; ";
    $csp .= "connect-src 'self' https: wss:; ";
    $csp .= "frame-src 'self' https:; ";
    $csp .= "media-src 'self' https:; ";
    $csp .= "object-src 'none'; ";
    $csp .= "base-uri 'self'; ";
    $csp .= "form-action 'self' https:; ";
    $csp .= "upgrade-insecure-requests;";
    header( 'Content-Security-Policy: ' . $csp );
}

/* ════════════════════════════════════════════════════════════
 * 7. www REDIRECT — הוסר (השרת מטפל בזה ברמת .htaccess/vhost)
 * ══════════════════════════════════════════════════════════ */
// Redirect removed in v1.0.2 — caused ERR_TOO_MANY_REDIRECTS on some hosts.
// Handle non-www → www at the server level (.htaccess or hosting panel).

/* ════════════════════════════════════════════════════════════
 * 8. llms.txt — endpoint דינמי
 * ══════════════════════════════════════════════════════════ */
add_action( 'init', 'hoco_geo_register_llmstxt_rewrite' );
function hoco_geo_register_llmstxt_rewrite() {
    add_rewrite_rule( '^llms\.txt$', 'index.php?hoco_llmstxt=1', 'top' );
}

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'hoco_llmstxt';
    return $vars;
} );

add_action( 'template_redirect', 'hoco_geo_serve_llmstxt' );
function hoco_geo_serve_llmstxt() {
    if ( ! get_query_var( 'hoco_llmstxt' ) ) {
        return;
    }
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'Cache-Control: public, max-age=86400' );
    echo hoco_geo_build_llmstxt();
    exit;
}

function hoco_geo_build_llmstxt() {
    $categories = [];
    $terms      = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true ] );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $url = get_term_link( $term );
            if ( ! is_wp_error( $url ) ) {
                $categories[] = '- [' . $term->name . '](' . $url . ')';
            }
        }
    }

    $products = [];
    if ( function_exists( 'wc_get_products' ) ) {
        $wc_products = wc_get_products( [ 'limit' => 15, 'status' => 'publish', 'orderby' => 'popularity' ] );
        foreach ( $wc_products as $p ) {
            $products[] = '- [' . $p->get_name() . '](' . get_permalink( $p->get_id() ) . ')';
        }
    }

    $out  = "# HOCO Israel\n\n";
    $out .= "> HOCO Israel הינה היבואן הרשמי של מוצרי HOCO בישראל. פתרונות איכותיים לאביזרי סלולר, מטענים, רמקולים, שעוני ילדים וטאבלטים.\n\n";
    $out .= "## מידע על הארגון\n\n";
    $out .= "- אתר: https://www.hoco-israel.co.il/\n";
    $out .= "- מייל: info@hoco-israel.co.il\n";
    $out .= "- שפה: עברית\n";
    $out .= "- יבואן רשמי: כן — HOCO Global (https://www.hoco.com)\n\n";

    if ( ! empty( $categories ) ) {
        $out .= "## קטגוריות מוצרים\n\n" . implode( "\n", $categories ) . "\n\n";
    }
    if ( ! empty( $products ) ) {
        $out .= "## מוצרים נבחרים\n\n" . implode( "\n", $products ) . "\n\n";
    }

    $out .= "## דפים חשובים\n\n";
    $out .= "- [עמוד הבית](https://www.hoco-israel.co.il/)\n";
    $out .= "- [חנות](https://www.hoco-israel.co.il/shop/)\n";
    $out .= "- [צור קשר](https://www.hoco-israel.co.il/contact/)\n\n";
    $out .= "## AI Crawler Policy\n\n";
    $out .= "All major AI crawlers are welcome: GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Amazonbot.\n\n";
    $out .= "---\n";
    $out .= "Plugin version: " . HOCO_GEO_SEO_VERSION . " | Updated: " . date( 'Y-m-d' ) . "\n";

    return $out;
}

/* ════════════════════════════════════════════════════════════
 * 9. ACTIVATION / DEACTIVATION
 * ══════════════════════════════════════════════════════════ */
register_activation_hook( __FILE__, function () {
    hoco_geo_register_llmstxt_rewrite();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ════════════════════════════════════════════════════════════
 * 10. ADMIN NOTICE — מצב תוסף
 * ══════════════════════════════════════════════════════════ */
add_action( 'admin_notices', 'hoco_geo_admin_notice' );
function hoco_geo_admin_notice() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'plugins' ) {
        return;
    }
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>HOCO GEO-SEO v' . esc_html( HOCO_GEO_SEO_VERSION ) . '</strong> פעיל ✓ — ';
    echo 'Organization Schema, Product Schema, Canonical (www), Security Headers ו-llms.txt פעילים. ';
    echo 'עדכונים יגיעו אוטומטית מ-GitHub.</p>';
    echo '</div>';
}
