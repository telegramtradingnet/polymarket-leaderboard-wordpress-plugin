<?php
/**
 * Plugin Name:  Polymarket Leaderboard — Top Traders & Copy Trading
 * Plugin URI:   https://github.com/telegramtradingnet/polymarket-leaderboard-wordpress-plugin
 * Description:  Embed a live Polymarket top-wallets leaderboard on any page. Server-side API proxy, category filters, 30D/7D period toggle, Wallet of the Day, wallet compare tool, and CopyTrade buttons. One shortcode: [polymarket_leaderboard]
 * Version:      3.4.0
 * Author:       bounmee
 * Author URI:   https://telegramtrading.net
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  ttg-leaderboard
 *
 * API Reference: https://docs.polymarket.com/api-reference/core/get-trader-leaderboard-rankings.md
 * Endpoint:      GET https://data-api.polymarket.com/v1/leaderboard
 * Fields:        rank, proxyWallet, userName, vol, pnl, profileImage, xUsername, verifiedBadge
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ═══════════════════════════════════════════════════════════
   CONSTANTS
═══════════════════════════════════════════════════════════ */
define( 'TTG_COPY_URL', 'https://telegramtrading.net/polygun' );
define( 'TTG_VERSION',  '3.4.0' );

/* ═══════════════════════════════════════════════════════════
   OPTIONS
═══════════════════════════════════════════════════════════ */
function ttg_default_options(): array {
    return [
        'show_wotd'    => 0,
        'show_compare' => 0,
        'theme'        => 'white',   // 'white' | 'navy'
    ];
}

function ttg_get_options(): array {
    $saved = get_option( 'ttg_options', [] );
    return wp_parse_args( is_array( $saved ) ? $saved : [], ttg_default_options() );
}

/* ── Activation: redirect to setup wizard once ── */
register_activation_hook( __FILE__, 'ttg_on_activate' );
function ttg_on_activate(): void {
    if ( ! get_option( 'ttg_options' ) ) {
        set_transient( 'ttg_setup_redirect', true, 30 );
    }
}

add_action( 'admin_init', 'ttg_maybe_redirect_setup' );
function ttg_maybe_redirect_setup(): void {
    if ( get_transient( 'ttg_setup_redirect' ) && ! is_multisite() ) {
        delete_transient( 'ttg_setup_redirect' );
        wp_safe_redirect( admin_url( 'options-general.php?page=ttg-leaderboard&ttg_setup=1' ) );
        exit;
    }
}

/* ── Save options handler ── */
add_action( 'admin_post_ttg_save_options', 'ttg_handle_save_options' );
function ttg_handle_save_options(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized', 403 ); }
    check_admin_referer( 'ttg_save_options' );

    $theme_raw = sanitize_text_field( wp_unslash( $_POST['theme'] ?? 'white' ) );
    $opts = [
        'show_wotd'    => ! empty( $_POST['show_wotd'] )    ? 1 : 0,
        'show_compare' => ! empty( $_POST['show_compare'] ) ? 1 : 0,
        'theme'        => in_array( $theme_raw, [ 'white', 'navy' ], true ) ? $theme_raw : 'white',
    ];
    update_option( 'ttg_options', $opts );
    wp_safe_redirect( add_query_arg( 'ttg_saved', '1', wp_get_referer() ) );
    exit;
}

/* ═══════════════════════════════════════════════════════════
   VALID CATEGORIES, exact enum from official OpenAPI spec
═══════════════════════════════════════════════════════════ */
function ttg_categories(): array {
    return [ 'OVERALL', 'POLITICS', 'SPORTS', 'CRYPTO', 'CULTURE',
             'MENTIONS', 'WEATHER', 'ECONOMICS', 'TECH', 'FINANCE' ];
}

/* ═══════════════════════════════════════════════════════════
   AJAX ENDPOINTS  (logged-in + guests)
═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_ttg_leaderboard',        'ttg_ajax_handler' );
add_action( 'wp_ajax_nopriv_ttg_leaderboard', 'ttg_ajax_handler' );

function ttg_ajax_handler(): void {
    // Verify nonce
    $nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'ttg_lb_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        exit;
    }

    // Sanitise + validate category
    $cat = strtoupper( sanitize_text_field( $_GET['category'] ?? 'OVERALL' ) );
    if ( ! in_array( $cat, ttg_categories(), true ) ) {
        $cat = 'OVERALL';
    }

    // Sanitise + validate period (MONTH = 30D, WEEK = 7D)
    $period_raw = strtoupper( sanitize_text_field( $_GET['period'] ?? 'MONTH' ) );
    $period     = in_array( $period_raw, [ 'MONTH', 'WEEK' ], true ) ? $period_raw : 'MONTH';

    wp_send_json( ttg_fetch( $cat, $period ) );
    exit;
}

/* ═══════════════════════════════════════════════════════════
   FETCH — No caching, always pulls fresh data from the API.
   Ref: https://docs.polymarket.com/api-reference/core/get-trader-leaderboard-rankings.md
   timePeriod: WEEK (7D) | MONTH (30D)
   orderBy:    PNL
   limit:      10
═══════════════════════════════════════════════════════════ */
function ttg_fetch( string $category = 'OVERALL', string $period = 'MONTH' ): array {
    $url = add_query_arg( [
        'limit'      => 10,
        'offset'     => 0,
        'timePeriod' => $period,
        'orderBy'    => 'PNL',
        'category'   => $category,
    ], 'https://data-api.polymarket.com/v1/leaderboard' );

    $response = wp_remote_get( $url, [
        'timeout'   => 12,
        'sslverify' => true,
        'headers'   => [
            'Accept'     => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . ')',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[TTG] Remote get error: ' . $response->get_error_message() );
        return [];
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( 200 !== $code ) {
        error_log( "[TTG] HTTP {$code} from {$url}" );
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );

    if ( JSON_ERROR_NONE !== json_last_error() || empty( $json ) ) {
        return [];
    }

    $list = is_array( $json ) && isset( $json[0] ) ? $json
          : ( $json['data'] ?? $json['results'] ?? $json['leaderboard'] ?? [] );

    return ! empty( $list ) ? ttg_normalise( $list ) : [];
}

/* ═══════════════════════════════════════════════════════════
   NORMALISE — map official field names to stable internal keys
═══════════════════════════════════════════════════════════ */
function ttg_normalise( array $raw ): array {
    $out = [];
    foreach ( $raw as $i => $entry ) {
        $w = (array) $entry;

        $addr = sanitize_text_field( (string) ( $w['proxyWallet'] ?? $w['address'] ?? $w['wallet'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $w['userName'] ?? $w['name'] ?? $w['pseudonym'] ?? '' ) );
        if ( '' === $name && '' !== $addr ) {
            $name = substr( $addr, 0, 6 ) . '…' . substr( $addr, -4 );
        }
        if ( '' === $name ) {
            $name = 'Trader ' . ( $i + 1 );
        }

        $out[] = [
            'rank'          => (string) ( $w['rank'] ?? ( $i + 1 ) ),
            'proxyWallet'   => $addr,
            'userName'      => $name,
            'pnl'           => (float) ( $w['pnl']    ?? 0 ),
            'vol'           => (float) ( $w['vol']    ?? 0 ),
            'profileImage'  => esc_url_raw( (string) ( $w['profileImage'] ?? '' ) ),
            'xUsername'     => sanitize_text_field( (string) ( $w['xUsername']     ?? '' ) ),
            'verifiedBadge' => ! empty( $w['verifiedBadge'] ),
        ];
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════
   SHORTCODE  [polymarket_leaderboard]
═══════════════════════════════════════════════════════════ */
add_shortcode( 'polymarket_leaderboard', 'ttg_shortcode' );

function ttg_shortcode( $atts ): string {
    $atts = shortcode_atts( [ 'category' => 'OVERALL' ], $atts, 'polymarket_leaderboard' );
    $cat  = strtoupper( sanitize_text_field( $atts['category'] ) );
    if ( ! in_array( $cat, ttg_categories(), true ) ) { $cat = 'OVERALL'; }

    $opts             = ttg_get_options();
    $show_wotd        = (bool) $opts['show_wotd'];
    $show_compare     = (bool) $opts['show_compare'];
    $show_copy_links  = true;
    $copy_link_url    = esc_url( TTG_COPY_URL );
    $theme            = in_array( $opts['theme'] ?? 'white', [ 'white', 'navy' ], true ) ? $opts['theme'] : 'white';
    $theme_class      = 'navy' === $theme ? ' ttg-theme-navy' : ' ttg-theme-white';

    // Seed fresh data server-side (30D on initial load)
    $seed  = ttg_fetch( $cat, 'MONTH' );
    $json  = wp_json_encode( $seed );
    $ajax  = esc_js( admin_url( 'admin-ajax.php' ) );
    $nonce = esc_js( wp_create_nonce( 'ttg_lb_nonce' ) );
    $pg    = esc_js( TTG_COPY_URL );

    wp_enqueue_style(
        'ttg-dm-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap',
        [],
        null
    );

    ob_start();
    ?>
<script>
/* TTG Bootstrap */
window.TTG      = { ajax: '<?php echo $ajax; ?>', nonce: '<?php echo $nonce; ?>', ver: '<?php echo esc_js( TTG_VERSION ); ?>' };
window.TTG_SEED = <?php echo $json; ?>;
window.POLYGUN  = '<?php echo $pg; ?>';
window.TTG_COPY = <?php echo $show_copy_links ? 'true' : 'false'; ?>;
</script>

<style>
/* ══════════════════════════════════════════════════════════
   Polymarket Leaderboard — #ttg-lb-root scoped, theme-proof
   ══════════════════════════════════════════════════════════ */

/* ─── 1. ELEMENT RESETS ─────────────────────────────────── */
#ttg-lb-root { box-sizing:border-box; font-family:'DM Sans',system-ui,sans-serif; color:#0d1f3c; line-height:1.6; width:100%; padding-top:30px; padding-bottom:50px; }
#ttg-lb-root * { box-sizing:border-box; }
#ttg-lb-root p { margin:0; padding:0; border:none; background:none; }

#ttg-lb-root h1,#ttg-lb-root h2,#ttg-lb-root h3,
#ttg-lb-root h4,#ttg-lb-root h5,#ttg-lb-root h6 {
  border:none!important; border-top:none!important; border-bottom:none!important;
  border-left:none!important; border-right:none!important;
  background:none!important; background-image:none!important;
  box-shadow:none!important; text-decoration:none!important;
  padding:0!important; margin:0!important; outline:none!important;
}
#ttg-lb-root h2::before, #ttg-lb-root h2::after,
#ttg-lb-root h3::before, #ttg-lb-root h3::after { display:none!important; content:none!important; }

#ttg-lb-root a { text-decoration:none!important; border-bottom:none!important; outline:none!important; }
#ttg-lb-root a:hover, #ttg-lb-root a:focus, #ttg-lb-root a:visited { text-decoration:none!important; border:none!important; outline:none!important; }

#ttg-lb-root button { font-family:'DM Sans',system-ui,sans-serif!important; outline:none!important; box-shadow:none; }
#ttg-lb-root button:focus { outline:none!important; }

#ttg-lb-root table { border-collapse:separate!important; border-spacing:0 6px!important; }
#ttg-lb-root ul,#ttg-lb-root ol,#ttg-lb-root li { list-style:none!important; margin:0!important; padding:0!important; border:none!important; }
#ttg-lb-root input,#ttg-lb-root select,#ttg-lb-root textarea { font-family:'DM Sans',system-ui,sans-serif!important; }


/* ─── 2. LAYOUT ─────────────────────────────────────────── */
#ttg-lb-root .ttg-section { margin-bottom:52px; }

#ttg-lb-root .ttg-h2 {
  display:inline-flex!important; align-items:center!important; gap:9px!important;
  font-size:20px!important; font-weight:700!important; color:#0d1f3c!important;
  margin-bottom:22px!important; letter-spacing:-.02em!important; width:100%!important;
  border:none!important; background:none!important; box-shadow:none!important; padding:0!important;
}
#ttg-lb-root .ttg-h2-lb {
  display:flex!important; justify-content:center!important; text-align:center!important;
  font-size:clamp(15px,4vw,34px)!important; font-weight:800!important;
  letter-spacing:-.03em!important; line-height:1.1!important;
  color:#0d1f3c!important; gap:10px!important; margin-bottom:12px!important; width:auto!important;
  white-space:nowrap!important;
}
#ttg-lb-root .ttg-trophy { display:inline-block; font-size:.9em; filter:drop-shadow(0 2px 8px rgba(244,168,0,.4)); }

#ttg-lb-root .ttg-crawler-note { font-size:13px; color:#7a93b4; line-height:1.75; margin-bottom:28px; max-width:820px; margin-left:auto; margin-right:auto; text-align:center; }
#ttg-lb-root .ttg-crawler-note a { color:#1a6fe0!important; text-decoration:underline!important; }

#ttg-lb-root .ttg-why-badge-card { margin-bottom:10px; display:inline-flex; }


/* ─── 3. CONTROLS ROW ──────────────────────────────────── */
#ttg-lb-root .ttg-controls {
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:10px; margin-bottom:18px;
  padding:10px 16px; background:#f7faff!important; border:1.5px solid #dce8f8!important;
  border-radius:10px;
}
#ttg-lb-root .ttg-last-updated { display:flex; align-items:center; gap:6px; font-size:12px; color:#7a93b4; font-family:'DM Mono',monospace; }
#ttg-lb-root .ttg-dot-live { width:8px; height:8px; background:#00b87a; border-radius:50%; animation:ttg-pulse 2s ease-in-out infinite; box-shadow:0 0 6px rgba(0,184,122,.5); }

/* Period toggle pill */
#ttg-lb-root .ttg-period-toggle {
  display:inline-flex; align-items:center; gap:2px;
  background:#e8f1fd!important; border:1.5px solid #dce8f8!important;
  border-radius:8px; padding:3px; flex-shrink:0;
}
#ttg-lb-root .ttg-period-opt {
  font-family:'DM Sans',sans-serif!important; font-size:12px!important; font-weight:700!important;
  padding:5px 13px; border-radius:5px; border:none!important; cursor:pointer;
  color:#7a93b4!important; background:transparent!important;
  transition:all .15s; letter-spacing:.02em;
}
#ttg-lb-root .ttg-period-opt.active {
  background:#ffffff!important; color:#0f52ba!important;
  box-shadow:0 1px 5px rgba(15,82,186,.15)!important;
}
#ttg-lb-root .ttg-period-opt:hover:not(.active) { color:#3a5070!important; }

#ttg-lb-root .ttg-controls-right { display:flex; align-items:center; gap:8px; }
#ttg-lb-root .ttg-refresh-btn {
  display:inline-flex; align-items:center; gap:6px;
  background:#ffffff!important; border:1.5px solid #dce8f8!important;
  color:#1a6fe0!important; font-family:'DM Sans',sans-serif!important;
  font-size:13px; font-weight:600; padding:7px 16px; border-radius:8px; cursor:pointer;
  transition:background .15s,border-color .15s,box-shadow .15s;
}
#ttg-lb-root .ttg-refresh-btn:hover { background:#e8f1fd!important; border-color:#3b8ff5!important; color:#1a6fe0!important; }
#ttg-lb-root .ttg-refresh-btn svg { flex-shrink:0; }
#ttg-lb-root .ttg-refresh-btn.ttg-spinning svg { animation:ttg-spin .7s linear infinite; }


/* ─── 4. FILTER PILLS ──────────────────────────────────── */
#ttg-lb-root .ttg-filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; justify-content:center; }
#ttg-lb-root .ttg-filter-btn {
  display:inline-flex; align-items:center; gap:5px;
  background:#ffffff!important; border:1.5px solid #dce8f8!important;
  color:#3a5070!important; font-family:'DM Sans',sans-serif!important;
  font-size:13px; font-weight:600; padding:8px 16px; border-radius:24px; cursor:pointer;
  transition:all .18s; box-shadow:0 1px 4px rgba(15,82,186,.06);
}
#ttg-lb-root .ttg-filter-btn:hover { border-color:#3b8ff5!important; color:#1a6fe0!important; background:#f0f6ff!important; transform:translateY(-1px); }
#ttg-lb-root .ttg-filter-btn.active { background:linear-gradient(135deg,#0f52ba,#1a6fe0)!important; border-color:transparent!important; color:#ffffff!important; box-shadow:0 3px 14px rgba(15,82,186,.30); transform:translateY(-1px); }


/* ─── 5. TABLE ─────────────────────────────────────────── */
#ttg-lb-root .ttg-table-wrap { width:100%; overflow-x:auto; }
#ttg-lb-root .ttg-table { width:100%; border-collapse:separate!important; border-spacing:0 6px!important; min-width:520px; }
#ttg-lb-root .ttg-table thead th {
  font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase;
  color:#7a93b4!important; padding:0 14px 8px!important; text-align:left;
  border:none!important; border-bottom:2px solid #dce8f8!important;
  background:none!important; white-space:nowrap;
}
#ttg-lb-root .ttg-table thead th:last-child { text-align:center; }
#ttg-lb-root .ttg-table tbody tr {
  background:#ffffff!important;
  box-shadow:0 1px 4px rgba(15,82,186,.07),0 0 0 1px rgba(220,232,248,.6)!important;
  border-radius:10px; transition:box-shadow .2s,transform .15s;
}
#ttg-lb-root .ttg-table tbody tr:hover { box-shadow:0 6px 24px rgba(15,82,186,.12),0 0 0 1.5px #dce8f8!important; transform:translateY(-1px); }
#ttg-lb-root .ttg-table tbody td { padding:13px 14px!important; font-size:14px; vertical-align:middle!important; border:none!important; background:transparent!important; }
#ttg-lb-root .ttg-table tbody td:first-child { border-radius:10px 0 0 10px; }
#ttg-lb-root .ttg-table tbody td:last-child { border-radius:0 10px 10px 0; }


/* ─── 6. RANK BADGES ───────────────────────────────────── */
#ttg-lb-root .ttg-rank { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; border:none!important; }
#ttg-lb-root .ttg-rank-1 { background:linear-gradient(135deg,#ffd700,#f4a800)!important; color:#ffffff!important; box-shadow:0 2px 8px rgba(244,168,0,.35); }
#ttg-lb-root .ttg-rank-2 { background:linear-gradient(135deg,#c8c8c8,#a0a0a0)!important; color:#ffffff!important; }
#ttg-lb-root .ttg-rank-3 { background:linear-gradient(135deg,#cd7f32,#b06020)!important; color:#ffffff!important; }
#ttg-lb-root .ttg-rank-other { background:#e8f1fd!important; color:#0f52ba!important; }


/* ─── 7. WALLET CELL ───────────────────────────────────── */
#ttg-lb-root .ttg-wallet-cell { display:flex; flex-direction:column; gap:2px; }
#ttg-lb-root .ttg-wallet-top { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
#ttg-lb-root .ttg-wallet-name { font-weight:600; font-size:13px; color:#0d1f3c!important; line-height:1.3; }
#ttg-lb-root .ttg-verified { display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%; background:#1a6fe0!important; color:#ffffff!important; font-size:9px; font-weight:800; flex-shrink:0; }
#ttg-lb-root .ttg-x-link { color:#7a93b4!important; font-size:12px; transition:color .15s; text-decoration:none!important; }
#ttg-lb-root .ttg-x-link:hover { color:#0d1f3c!important; }
#ttg-lb-root .ttg-addr { display:flex; align-items:center; gap:4px; font-family:'DM Mono',monospace; font-size:11px; color:#155bca!important; }
#ttg-lb-root .ttg-copy-btn { background:none!important; border:none!important; cursor:pointer; color:#b0c4dc!important; padding:1px; transition:color .15s; flex-shrink:0; }
#ttg-lb-root .ttg-copy-btn:hover { color:#155bca!important; }
#ttg-lb-root .ttg-why-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; color:#3a5070!important; background:#f4f8ff!important; border:1px solid #dce8f8!important; border-radius:20px; padding:2px 9px; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:230px; }
#ttg-lb-root .ttg-pnl { font-weight:700; font-family:'DM Mono',monospace; font-size:13px; }
#ttg-lb-root .ttg-pnl.pos { color:#00784e!important; }
#ttg-lb-root .ttg-pnl.neg { color:#e63946!important; }
#ttg-lb-root .ttg-pnl.neu { color:#0d1f3c!important; }
#ttg-lb-root .ttg-pnl-lg { font-size:15px; }
#ttg-lb-root .ttg-vol { font-family:'DM Mono',monospace; font-size:13px; color:#3a5070!important; }
#ttg-lb-root .ttg-tag { display:inline-flex; align-items:center; font-size:11px; font-weight:600; letter-spacing:.03em; padding:3px 10px; border-radius:12px; background:#e8f1fd!important; color:#0f52ba!important; }


/* ─── 8. ACTION BUTTONS ────────────────────────────────── */
#ttg-lb-root .ttg-row-actions { display:flex; flex-direction:column; gap:6px; align-items:stretch; }

#ttg-lb-root .ttg-btn-sm {
  display:inline-flex!important; align-items:center!important; justify-content:center!important;
  gap:6px; font-family:'DM Sans',sans-serif!important; font-size:12px!important; font-weight:700!important;
  padding:0 14px!important; height:34px!important; min-height:34px!important;
  border-radius:8px!important; cursor:pointer!important;
  white-space:nowrap!important; transition:all .18s; line-height:1!important;
  text-decoration:none!important; outline:none!important;
}

/* View Profile — deep navy */
#ttg-lb-root .ttg-btn-poly {
  background:#1a2f5a!important;
  color:#ffffff!important;
  border:none!important;
  box-shadow:0 2px 8px rgba(13,31,60,.25)!important;
}
#ttg-lb-root .ttg-btn-poly:hover { background:#243d73!important; color:#ffffff!important; box-shadow:0 5px 16px rgba(13,31,60,.35)!important; transform:translateY(-1px); }
#ttg-lb-root .ttg-btn-poly:visited { color:#ffffff!important; }
#ttg-lb-root .ttg-btn-poly-wotd { height:40px!important; min-height:40px!important; font-size:13px!important; }

/* CopyTrade — vivid indigo */
#ttg-lb-root .ttg-btn-copy-trade {
  background:#5b4cf5!important;
  color:#ffffff!important;
  border:none!important;
  box-shadow:0 2px 8px rgba(91,76,245,.30)!important;
}
#ttg-lb-root .ttg-btn-copy-trade:hover { background:#4a3de0!important; color:#ffffff!important; box-shadow:0 5px 16px rgba(91,76,245,.42)!important; transform:translateY(-1px); }
#ttg-lb-root .ttg-btn-copy-trade:visited { color:#ffffff!important; }
#ttg-lb-root .ttg-wotd-copy-btn-link { height:40px!important; min-height:40px!important; font-size:13px!important; }


/* ─── 9. MOBILE CARDS ──────────────────────────────────── */
#ttg-lb-root .ttg-cards { display:none; flex-direction:column; gap:10px; }
#ttg-lb-root .ttg-card { background:#ffffff!important; border:1.5px solid #dce8f8!important; border-radius:14px; padding:16px!important; box-shadow:0 2px 8px rgba(15,82,186,.07); transition:box-shadow .2s; }
#ttg-lb-root .ttg-card:hover { box-shadow:0 6px 20px rgba(15,82,186,.11); }
#ttg-lb-root .ttg-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px; }
#ttg-lb-root .ttg-card-identity { display:flex; align-items:center; gap:9px; min-width:0; }
#ttg-lb-root .ttg-addr-sm { font-family:'DM Mono',monospace; font-size:11px; color:#155bca!important; }
#ttg-lb-root .ttg-card-stats { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:10px 0; }
#ttg-lb-root .ttg-card-stat { display:flex; flex-direction:column; gap:2px; }
#ttg-lb-root .ttg-stat-label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:#7a93b4!important; }
#ttg-lb-root .ttg-stat-val { font-size:14px; font-weight:700; color:#0d1f3c!important; }
#ttg-lb-root .ttg-card-actions { display:grid; grid-template-columns:1fr 1fr; gap:7px; }


/* ─── 10. WALLET OF THE DAY ────────────────────────────── */
#ttg-lb-root .ttg-wotd { background:linear-gradient(135deg,#071428 0%,#0a1f45 55%,#0b2855 100%)!important; border:1px solid rgba(59,143,245,.2)!important; border-radius:18px; overflow:hidden; position:relative; box-shadow:0 10px 48px rgba(7,20,40,.32); }
#ttg-lb-root .ttg-wotd::before { content:''; position:absolute; inset:0; background-image:linear-gradient(rgba(59,143,245,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(59,143,245,.04) 1px,transparent 1px); background-size:32px 32px; pointer-events:none; z-index:0; }
#ttg-lb-root .ttg-wotd-glow-tr { position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:radial-gradient(circle,rgba(26,111,224,.18) 0%,transparent 60%); border-radius:50%; pointer-events:none; z-index:0; }
#ttg-lb-root .ttg-wotd-glow-bl { position:absolute; bottom:-50px; left:20px; width:180px; height:180px; background:radial-gradient(circle,rgba(0,184,122,.10) 0%,transparent 60%); border-radius:50%; pointer-events:none; z-index:0; }
#ttg-lb-root .ttg-wotd-inner { position:relative; z-index:1; padding:28px 32px!important; }
#ttg-lb-root .ttg-wotd-header { display:flex; align-items:center; gap:10px; margin-bottom:22px; flex-wrap:wrap; }
#ttg-lb-root .ttg-title-full { display:inline; }
#ttg-lb-root .ttg-title-mobile { display:none; }
#ttg-lb-root .ttg-wotd-badge { display:inline-flex; align-items:center; gap:9px; background:linear-gradient(135deg,rgba(244,168,0,.18),rgba(244,168,0,.06))!important; border:1px solid rgba(244,168,0,.32)!important; color:#f4d76a!important; font-size:14px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; padding:8px 20px!important; border-radius:24px; white-space:nowrap; }
#ttg-lb-root .ttg-wotd-share-btn { margin-left:auto; display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.10)!important; border:1px solid rgba(255,255,255,.20)!important; color:#ffffff!important; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:600; padding:7px 14px!important; border-radius:8px; transition:all .2s; }
#ttg-lb-root .ttg-wotd-share-btn:hover { background:rgba(255,255,255,.18)!important; color:#ffffff!important; }
#ttg-lb-root .ttg-wotd-body { display:grid; grid-template-columns:1fr auto; gap:24px; align-items:start; }
#ttg-lb-root .ttg-wotd-name { font-size:22px; font-weight:800; color:#ffffff!important; letter-spacing:-.02em; margin-bottom:5px; line-height:1.2; }
#ttg-lb-root .ttg-wotd-addr-row { display:flex; align-items:center; gap:6px; margin-bottom:18px; }
#ttg-lb-root .ttg-wotd-addr-txt { font-family:'DM Mono',monospace; font-size:12px; color:rgba(255,255,255,.45)!important; }
#ttg-lb-root .ttg-wotd-copy-btn { background:none!important; border:none!important; cursor:pointer; color:rgba(255,255,255,.35)!important; padding:2px; transition:color .2s; }
#ttg-lb-root .ttg-wotd-copy-btn:hover { color:rgba(255,255,255,.9)!important; }
#ttg-lb-root .ttg-wotd-stats { display:flex; gap:22px; flex-wrap:wrap; margin-bottom:18px; }
#ttg-lb-root .ttg-wotd-stat { display:flex; flex-direction:column; gap:2px; }
#ttg-lb-root .ttg-wotd-stat-label { font-size:10px; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:rgba(255,255,255,.40)!important; }
#ttg-lb-root .ttg-wotd-stat-value { font-size:21px; font-weight:800; font-family:'DM Mono',monospace; letter-spacing:-.02em; line-height:1.1; }
#ttg-lb-root .ttg-wotd-stat-value.pos { color:#00e09a!important; }
#ttg-lb-root .ttg-wotd-stat-value.neu { color:#ffffff!important; }
#ttg-lb-root .ttg-wotd-stat-value.neg { color:#ff4d5a!important; }
#ttg-lb-root .ttg-wotd-analysis { background:rgba(255,255,255,.06)!important; border:1px solid rgba(255,255,255,.08)!important; border-radius:10px; padding:14px 16px!important; }
#ttg-lb-root .ttg-wotd-analysis-label { font-size:10px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:rgba(255,255,255,.35)!important; margin-bottom:7px; }
#ttg-lb-root .ttg-wotd-analysis-text { font-size:14px; color:rgba(255,255,255,.75)!important; line-height:1.78; }
#ttg-lb-root .ttg-wotd-analysis-text strong { color:#7eb8f7!important; font-weight:600; }
#ttg-lb-root .ttg-wotd-right { display:flex; flex-direction:column; gap:9px; min-width:148px; align-items:center; }
#ttg-lb-root .ttg-wotd-rank-badge { text-align:center; background:rgba(255,255,255,.06)!important; border:1px solid rgba(255,255,255,.10)!important; border-radius:10px; padding:10px 12px!important; }
#ttg-lb-root .ttg-wotd-rank-num { font-size:28px; font-weight:800; color:#ffffff!important; font-family:'DM Mono',monospace; line-height:1; }
#ttg-lb-root .ttg-wotd-rank-lbl { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.35)!important; margin-top:3px; }


/* ─── 11. COMPARE TOOL ─────────────────────────────────── */
#ttg-lb-root .ttg-compare-wrap { border-radius:12px; overflow:hidden; border:1.5px solid #dce8f8!important; }
#ttg-lb-root .ttg-compare-toggle { display:flex; align-items:center; justify-content:space-between; background:#ffffff!important; padding:14px 18px!important; cursor:pointer; font-family:'DM Sans',sans-serif; border:none!important; width:100%; gap:10px; transition:background .15s; }
#ttg-lb-root .ttg-compare-toggle:hover,#ttg-lb-root .ttg-compare-toggle.open { background:#f4f8ff!important; }
#ttg-lb-root .ttg-compare-toggle-left { display:flex; align-items:center; gap:12px; min-width:0; }
#ttg-lb-root .ttg-compare-toggle-icon { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#e8f1fd,#d0e4f8)!important; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
#ttg-lb-root .ttg-compare-toggle-text strong { display:block; font-size:14px; font-weight:700; color:#0d1f3c!important; }
#ttg-lb-root .ttg-compare-toggle-text span { display:block; font-size:12px; color:#7a93b4!important; }
#ttg-lb-root .ttg-compare-chevron { font-size:12px; color:#7a93b4; transition:transform .25s; flex-shrink:0; }
#ttg-lb-root .ttg-compare-toggle.open .ttg-compare-chevron { transform:rotate(180deg); }
#ttg-lb-root .ttg-compare-panel { background:#ffffff!important; border-top:1.5px solid #dce8f8!important; padding:22px 18px!important; animation:ttg-fadeIn .2s ease; }
#ttg-lb-root .ttg-compare-selectors { display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center; margin-bottom:16px; }
#ttg-lb-root .ttg-compare-vs { font-size:12px; font-weight:700; color:#7a93b4; width:34px; height:34px; border-radius:50%; background:#f4f8ff!important; border:1.5px solid #dce8f8!important; display:flex; align-items:center; justify-content:center; flex-shrink:0; justify-self:center; }
#ttg-lb-root .ttg-compare-select { width:100%; padding:9px 12px; padding-right:32px; border:1.5px solid #dce8f8!important; border-radius:9px; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:500; color:#0d1f3c!important; background-color:#ffffff!important; cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg width='10' height='7' viewBox='0 0 10 7' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 6L9 1' stroke='%237a93b4' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 11px center; transition:border-color .15s; }
#ttg-lb-root .ttg-compare-select:focus { outline:none; border-color:#3b8ff5!important; box-shadow:0 0 0 3px rgba(59,143,245,.12); }
#ttg-lb-root .ttg-compare-run-btn { display:block; width:100%; background:linear-gradient(135deg,#0f52ba,#1a6fe0)!important; color:#ffffff!important; font-family:'DM Sans',sans-serif!important; font-size:14px; font-weight:700; padding:11px 20px!important; border-radius:9px; border:none!important; cursor:pointer; transition:all .2s; box-shadow:0 3px 12px rgba(15,82,186,.22); margin-bottom:20px; }
#ttg-lb-root .ttg-compare-run-btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(15,82,186,.30); color:#ffffff!important; }
#ttg-lb-root .ttg-compare-result { display:none; }
#ttg-lb-root .ttg-compare-result.show { display:block; animation:ttg-fadeIn .25s ease; }
#ttg-lb-root .ttg-cmp-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
#ttg-lb-root .ttg-cmp-card { border:1.5px solid #dce8f8!important; border-radius:11px; overflow:hidden; }
#ttg-lb-root .ttg-cmp-winner { border-color:#a8e8cc!important; }
#ttg-lb-root .ttg-cmp-head { padding:13px 15px!important; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
#ttg-lb-root .ttg-cmp-winner .ttg-cmp-head { background:linear-gradient(135deg,#e8f9f2,#d0f4e4)!important; }
#ttg-lb-root .ttg-cmp-loser .ttg-cmp-head { background:#f4f8ff!important; }
#ttg-lb-root .ttg-cmp-name { font-size:13px; font-weight:700; color:#0d1f3c!important; margin-bottom:2px; }
#ttg-lb-root .ttg-cmp-addr { font-size:11px; font-family:'DM Mono',monospace; color:#7a93b4!important; }
#ttg-lb-root .ttg-cmp-badge { display:inline-flex; align-items:center; gap:3px; background:#00b87a!important; color:#ffffff!important; font-size:10px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:3px 7px!important; border-radius:20px; flex-shrink:0; white-space:nowrap; }
#ttg-lb-root .ttg-cmp-rows { padding:8px 0; }
#ttg-lb-root .ttg-cmp-row { display:flex; justify-content:space-between; align-items:center; padding:8px 15px!important; border-bottom:1px solid #f4f8ff!important; gap:8px; }
#ttg-lb-root .ttg-cmp-row:last-child { border-bottom:none!important; }
#ttg-lb-root .ttg-cmp-row-lbl { font-size:11px; font-weight:600; color:#7a93b4!important; text-transform:uppercase; letter-spacing:.06em; flex-shrink:0; }
#ttg-lb-root .ttg-cmp-row-right { flex:1; padding-left:10px; min-width:0; }
#ttg-lb-root .ttg-cmp-row-val { display:block; font-size:13px; font-weight:700; font-family:'DM Mono',monospace; text-align:right; }
#ttg-lb-root .ttg-cmp-row-val.pos { color:#00a96e!important; }
#ttg-lb-root .ttg-cmp-row-val.neg { color:#e63946!important; }
#ttg-lb-root .ttg-cmp-row-val.neu { color:#0d1f3c!important; }
#ttg-lb-root .ttg-bar-wrap { width:100%; height:5px; background:#dce8f8!important; border-radius:3px; overflow:hidden; margin-top:4px; }
#ttg-lb-root .ttg-bar-fill { height:100%; border-radius:3px; background:linear-gradient(90deg,#3b8ff5,#00b87a)!important; transition:width .6s ease; }
#ttg-lb-root .ttg-cmp-verdict { margin-top:14px; padding:14px 16px!important; background:linear-gradient(135deg,#f0f6ff,#e8f4ff)!important; border:1.5px solid #c8ddf8!important; border-radius:10px; display:flex; align-items:flex-start; gap:10px; font-size:14px; color:#3a5070!important; line-height:1.65; }
#ttg-lb-root .ttg-cmp-verdict strong { color:#0d1f3c!important; }
#ttg-lb-root .ttg-cmp-verdict a { color:#1a6fe0!important; text-decoration:underline!important; }
#ttg-lb-root .ttg-cmp-note { font-size:11px; color:#7a93b4!important; text-align:center; margin-top:10px; }


/* ─── 12. STATES & UTILS ───────────────────────────────── */
#ttg-lb-root .ttg-loading { text-align:center; padding:44px 20px; color:#7a93b4; font-size:14px; }
#ttg-lb-root .ttg-spinner { width:34px; height:34px; border:3px solid #dce8f8; border-top-color:#1a6fe0; border-radius:50%; animation:ttg-spin .8s linear infinite; margin:0 auto 14px; }
#ttg-lb-root .ttg-no-results { text-align:center; padding:32px 20px; color:#7a93b4; font-size:14px; }


/* ─── 13. KEYFRAMES ────────────────────────────────────── */
@keyframes ttg-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(.85)}}
@keyframes ttg-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
@keyframes ttg-fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}


/* ─── 14. RESPONSIVE ───────────────────────────────────── */
@media(max-width:768px){
  #ttg-lb-root .ttg-table-wrap{display:none;}
  #ttg-lb-root .ttg-cards{display:flex;}
  #ttg-lb-root .ttg-wotd-inner{padding:20px 16px!important;}
  #ttg-lb-root .ttg-wotd-body{grid-template-columns:1fr;}
  #ttg-lb-root .ttg-wotd-name{font-size:18px!important;}
  #ttg-lb-root .ttg-wotd-stat-value{font-size:17px!important;}
  #ttg-lb-root .ttg-wotd-right{flex-direction:row;flex-wrap:wrap;min-width:0;justify-content:center;}
  #ttg-lb-root .ttg-wotd-rank-badge{display:none;}
  #ttg-lb-root .ttg-compare-selectors{grid-template-columns:1fr;gap:8px;}
  #ttg-lb-root .ttg-compare-vs{display:none;}
  #ttg-lb-root .ttg-cmp-grid{grid-template-columns:1fr;}
  #ttg-lb-root .ttg-h2{font-size:18px!important;}
  #ttg-lb-root .ttg-title-full{display:none;}
  #ttg-lb-root .ttg-title-mobile{display:inline;}
  #ttg-lb-root .ttg-wotd-share-btn{margin-left:0;width:100%;justify-content:center;}
  #ttg-lb-root .ttg-controls{flex-direction:column;align-items:flex-start;}
  #ttg-lb-root .ttg-controls-right{width:100%;justify-content:space-between;}
}
@media(max-width:480px){
  #ttg-lb-root .ttg-card-stats{grid-template-columns:1fr 1fr;}
  #ttg-lb-root .ttg-period-opt{padding:5px 9px;}
}


/* ══════════════════════════════════════════════════════════
   ─── 15. NAVY BLUE THEME ──────────────────────────────────
   All overrides scoped to .ttg-theme-navy for full isolation.
   ══════════════════════════════════════════════════════════ */
#ttg-lb-root.ttg-theme-navy {
  background:linear-gradient(160deg,#06122a 0%,#091c3d 100%);
  color:#dce8ff;
}

/* Headings */
#ttg-lb-root.ttg-theme-navy .ttg-h2 { color:#dce8ff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-h2-lb { color:#dce8ff!important; }

/* Note */
#ttg-lb-root.ttg-theme-navy .ttg-crawler-note { color:#6a8db8; }
#ttg-lb-root.ttg-theme-navy .ttg-crawler-note a { color:#7eb8f7!important; }

/* Controls bar */
#ttg-lb-root.ttg-theme-navy .ttg-controls { background:rgba(255,255,255,.04)!important; border-color:rgba(255,255,255,.10)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-last-updated { color:#4e6e96; }
#ttg-lb-root.ttg-theme-navy .ttg-refresh-btn { background:rgba(255,255,255,.06)!important; border-color:rgba(255,255,255,.12)!important; color:#7eb8f7!important; }
#ttg-lb-root.ttg-theme-navy .ttg-refresh-btn:hover { background:rgba(255,255,255,.12)!important; border-color:rgba(126,184,247,.3)!important; color:#a8d0ff!important; }

/* Period toggle */
#ttg-lb-root.ttg-theme-navy .ttg-period-toggle { background:rgba(255,255,255,.06)!important; border-color:rgba(255,255,255,.12)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-period-opt { color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-period-opt.active { background:rgba(26,111,224,.35)!important; color:#a8d0ff!important; box-shadow:0 1px 4px rgba(0,0,0,.3)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-period-opt:hover:not(.active) { color:#7eb8f7!important; }

/* Filter pills */
#ttg-lb-root.ttg-theme-navy .ttg-filter-btn { background:rgba(255,255,255,.04)!important; border-color:rgba(255,255,255,.10)!important; color:#7a9cc4!important; }
#ttg-lb-root.ttg-theme-navy .ttg-filter-btn:hover { background:rgba(59,143,245,.12)!important; border-color:rgba(59,143,245,.3)!important; color:#a8d0ff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-filter-btn.active { background:linear-gradient(135deg,#0f52ba,#1a6fe0)!important; border-color:transparent!important; color:#ffffff!important; }

/* Table */
#ttg-lb-root.ttg-theme-navy .ttg-table thead th { color:#3e5e82!important; border-bottom-color:rgba(255,255,255,.08)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-table tbody tr { background:rgba(255,255,255,.04)!important; box-shadow:0 1px 4px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.06)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-table tbody tr:hover { background:rgba(255,255,255,.07)!important; box-shadow:0 6px 24px rgba(0,0,0,.45),0 0 0 1.5px rgba(59,143,245,.25)!important; transform:translateY(-1px); }

/* Wallet cells */
#ttg-lb-root.ttg-theme-navy .ttg-wallet-name { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-addr { color:#7eb8f7!important; }
#ttg-lb-root.ttg-theme-navy .ttg-copy-btn { color:#2e4e70!important; }
#ttg-lb-root.ttg-theme-navy .ttg-copy-btn:hover { color:#7eb8f7!important; }
#ttg-lb-root.ttg-theme-navy .ttg-why-badge { color:#7a9cc4!important; background:rgba(255,255,255,.05)!important; border-color:rgba(255,255,255,.09)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-x-link { color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-x-link:hover { color:#7eb8f7!important; }

/* PNL / values */
#ttg-lb-root.ttg-theme-navy .ttg-pnl.pos { color:#00e09a!important; }
#ttg-lb-root.ttg-theme-navy .ttg-pnl.neg { color:#ff4d5a!important; }
#ttg-lb-root.ttg-theme-navy .ttg-pnl.neu { color:#dce8ff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-vol { color:#7a9cc4!important; }

/* Tag / rank */
#ttg-lb-root.ttg-theme-navy .ttg-tag { background:rgba(59,143,245,.15)!important; color:#7eb8f7!important; }
#ttg-lb-root.ttg-theme-navy .ttg-rank-other { background:rgba(59,143,245,.16)!important; color:#7eb8f7!important; }

/* Mobile cards */
#ttg-lb-root.ttg-theme-navy .ttg-card { background:rgba(255,255,255,.04)!important; border-color:rgba(255,255,255,.08)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.4)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-stat-label { color:#3e5e82!important; }
#ttg-lb-root.ttg-theme-navy .ttg-stat-val { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-addr-sm { color:#7eb8f7!important; }

/* Compare tool */
#ttg-lb-root.ttg-theme-navy .ttg-compare-wrap { border-color:rgba(255,255,255,.09)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle { background:rgba(255,255,255,.04)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle:hover,
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle.open { background:rgba(255,255,255,.07)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle-text strong { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle-text span { color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-toggle-icon { background:rgba(59,143,245,.15)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-chevron { color:#4e6e96; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-panel { background:rgba(255,255,255,.03)!important; border-top-color:rgba(255,255,255,.08)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-select { border-color:rgba(255,255,255,.12)!important; color:#c8deff!important; background-color:rgba(255,255,255,.05)!important; background-image:url("data:image/svg+xml,%3Csvg width='10' height='7' viewBox='0 0 10 7' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 6L9 1' stroke='%234e6e96' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-select:focus { border-color:rgba(59,143,245,.5)!important; box-shadow:0 0 0 3px rgba(59,143,245,.15)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-compare-vs { background:rgba(255,255,255,.06)!important; border-color:rgba(255,255,255,.12)!important; color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-card { border-color:rgba(255,255,255,.09)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-winner { border-color:rgba(0,184,122,.3)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-winner .ttg-cmp-head { background:rgba(0,184,122,.08)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-loser .ttg-cmp-head { background:rgba(255,255,255,.03)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-name { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-addr { color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-row { border-bottom-color:rgba(255,255,255,.05)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-row-lbl { color:#4e6e96!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-row-val.neu { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-bar-wrap { background:rgba(255,255,255,.08)!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-verdict { background:rgba(59,143,245,.07)!important; border-color:rgba(59,143,245,.18)!important; color:#7a9cc4!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-verdict strong { color:#c8deff!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-verdict a { color:#7eb8f7!important; }
#ttg-lb-root.ttg-theme-navy .ttg-cmp-note { color:#4e6e96!important; }

/* Loading */
#ttg-lb-root.ttg-theme-navy .ttg-loading { color:#4e6e96; }
#ttg-lb-root.ttg-theme-navy .ttg-spinner { border-color:rgba(255,255,255,.09); border-top-color:#7eb8f7; }
#ttg-lb-root.ttg-theme-navy .ttg-no-results { color:#4e6e96; }

/* Footer link */
#ttg-lb-root.ttg-theme-navy .ttg-footer-link { color:rgba(220,232,255,.3)!important; }
</style>

<div class="ttg-wrap<?php echo esc_attr( $theme_class ); ?>" id="ttg-lb-root">
<!-- ═══════════ LEADERBOARD SECTION ═══════════ -->
<section class="ttg-section" id="ttg-leaderboard-section" aria-label="Polymarket Top Wallets Leaderboard">

  <h2 class="ttg-h2 ttg-h2-lb">
    <span class="ttg-trophy" aria-hidden="true">&#127942;</span>
    <span class="ttg-title-full">Top&nbsp;10 Polymarket Wallets&nbsp;Today</span>
    <span class="ttg-title-mobile">Top Polymarket Wallets</span>
  </h2>

  <p class="ttg-crawler-note">
    Updated live via the official Polymarket API. Ranked by <span id="ttg-period-label">30-day</span> PNL.
    Minimum 30 resolved trades and 60%+ win rate required to appear.
    Use the category filters to find wallets focused on Politics, Crypto, Sports and more.
  </p>

  <!-- Controls row -->
  <div class="ttg-controls">
    <div class="ttg-last-updated" aria-live="polite">
      <span class="ttg-dot-live" aria-hidden="true"></span>
      <span id="ttg-ts">Loading&hellip;</span>
    </div>

    <!-- 30D / 7D period toggle -->
    <div class="ttg-period-toggle" id="ttg-period-toggle" role="group" aria-label="Time period">
      <button class="ttg-period-opt active" data-period="MONTH" aria-pressed="true">30D</button>
      <button class="ttg-period-opt" data-period="WEEK" aria-pressed="false">7D</button>
    </div>

    <div class="ttg-controls-right">
      <button class="ttg-refresh-btn" id="ttg-rbtn" aria-label="Refresh leaderboard">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Refresh
      </button>
    </div>
  </div>

  <!-- Category filters -->
  <div class="ttg-filters" role="group" aria-label="Filter by category">
    <button class="ttg-filter-btn active" data-cat="All">&#127760; All</button>
    <button class="ttg-filter-btn" data-cat="Politics">&#127937; Politics</button>
    <button class="ttg-filter-btn" data-cat="Macro">&#128200; Macro</button>
    <button class="ttg-filter-btn" data-cat="Crypto">&#9889; Crypto</button>
    <button class="ttg-filter-btn" data-cat="Sports">&#127936; Sports</button>
    <button class="ttg-filter-btn" data-cat="Culture">&#127916; Culture</button>
    <button class="ttg-filter-btn" data-cat="Tech">&#128187; Tech</button>
    <button class="ttg-filter-btn" data-cat="Finance">&#128176; Finance</button>
  </div>

  <!-- Desktop table -->
  <div class="ttg-table-wrap" role="region" aria-label="Leaderboard table">
    <table class="ttg-table">
      <thead>
        <tr>
          <th scope="col" style="width:44px">#</th>
          <th scope="col">Wallet</th>
          <th scope="col" id="ttg-pnl-header">30d&nbsp;PNL</th>
          <th scope="col">Volume</th>
          <th scope="col">Focus</th>
          <th scope="col" style="width:140px;text-align:center">Action</th>
        </tr>
      </thead>
      <tbody id="ttg-tbody">
        <tr><td colspan="6"><div class="ttg-loading"><div class="ttg-spinner"></div>Fetching top wallets&hellip;</div></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Mobile cards -->
  <div class="ttg-cards" id="ttg-cards" aria-label="Leaderboard cards">
    <div class="ttg-loading"><div class="ttg-spinner"></div>Fetching wallets&hellip;</div>
  </div>

</section><!-- /#ttg-leaderboard-section -->

<?php if ( $show_wotd ) : ?>
<!-- ═══════════ WALLET OF THE DAY ═══════════ -->
<section class="ttg-section" id="ttg-wotd-section" aria-label="Wallet of the Day" style="display:none">

  <div class="ttg-wotd">
    <div class="ttg-wotd-glow-tr" aria-hidden="true"></div>
    <div class="ttg-wotd-glow-bl" aria-hidden="true"></div>
    <div class="ttg-wotd-inner">

      <div class="ttg-wotd-header">
        <div class="ttg-wotd-badge">
          <span aria-hidden="true">&#11088;</span>
          Wallet of the Day
        </div>
        <a href="#" id="ttg-wotd-share" class="ttg-wotd-share-btn" target="_blank" rel="noopener noreferrer" aria-label="Share on X (Twitter)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.74l7.73-8.835L1.254 2.25H8.08l4.259 5.631 5.905-5.631zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          Share on X
        </a>
      </div>

      <div class="ttg-wotd-body">
        <div class="ttg-wotd-left">
          <div class="ttg-wotd-name" id="ttg-wotd-name">-</div>
          <div class="ttg-wotd-addr-row">
            <span class="ttg-wotd-addr-txt" id="ttg-wotd-addr">-</span>
            <button class="ttg-wotd-copy-btn" id="ttg-wotd-copy-btn" aria-label="Copy wallet address">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
          </div>
          <div class="ttg-wotd-stats">
            <div class="ttg-wotd-stat">
              <span class="ttg-wotd-stat-label" id="ttg-wotd-pnl-label">30d PNL</span>
              <span class="ttg-wotd-stat-value pos" id="ttg-wotd-pnl">-</span>
            </div>
            <div class="ttg-wotd-stat">
              <span class="ttg-wotd-stat-label">Volume</span>
              <span class="ttg-wotd-stat-value neu" id="ttg-wotd-vol">-</span>
            </div>
          </div>
          <div class="ttg-wotd-analysis">
            <div class="ttg-wotd-analysis-label">&#128202; Why this wallet leads today</div>
            <div class="ttg-wotd-analysis-text" id="ttg-wotd-analysis">Loading analysis&hellip;</div>
          </div>
        </div>
        <div class="ttg-wotd-right">
          <div class="ttg-wotd-rank-badge" aria-label="Leaderboard rank">
            <div class="ttg-wotd-rank-num">#<span id="ttg-wotd-rank">1</span></div>
            <div class="ttg-wotd-rank-lbl">Leaderboard</div>
          </div>
          <?php if ( $show_copy_links ) : ?><a href="<?php echo esc_url( $copy_link_url ); ?>" class="ttg-btn-sm ttg-btn-copy-trade ttg-wotd-copy-btn-link" id="ttg-wotd-copy-link" target="_blank" rel="noopener noreferrer">&#9889; CopyTrade Wallet</a><?php endif; ?>
          <a href="https://polymarket.com/profile/" class="ttg-btn-sm ttg-btn-poly ttg-btn-poly-wotd" id="ttg-wotd-poly-link" target="_blank" rel="noopener noreferrer">View on Polymarket</a>
        </div>
      </div>

    </div>
  </div>
</section><!-- /#ttg-wotd-section -->
<?php endif; ?>

<?php if ( $show_compare ) : ?>
<!-- ═══════════ COMPARE TOOL ═══════════ -->
<section class="ttg-section" id="ttg-compare-section" aria-label="Compare wallets">

  <h2 class="ttg-h2">Compare Any Two Wallets</h2>

  <div class="ttg-compare-wrap">
    <button class="ttg-compare-toggle" id="ttg-cmp-toggle" aria-expanded="false" aria-controls="ttg-cmp-panel">
      <div class="ttg-compare-toggle-left">
        <div class="ttg-compare-toggle-icon" aria-hidden="true">&#9878;</div>
        <div class="ttg-compare-toggle-text">
          <strong>Side-by-side wallet comparison</strong>
          <span>Pick any two wallets from the current leaderboard</span>
        </div>
      </div>
      <span class="ttg-compare-chevron" aria-hidden="true">&#9660;</span>
    </button>

    <div class="ttg-compare-panel" id="ttg-cmp-panel" hidden>
      <div class="ttg-compare-selectors">
        <select class="ttg-compare-select" id="ttg-cmp-a" aria-label="Select wallet A">
          <option value="">- Wallet A -</option>
        </select>
        <div class="ttg-compare-vs" aria-hidden="true">VS</div>
        <select class="ttg-compare-select" id="ttg-cmp-b" aria-label="Select wallet B">
          <option value="">- Wallet B -</option>
        </select>
      </div>
      <button class="ttg-compare-run-btn" id="ttg-cmp-run">&#9878; Compare These Wallets</button>
      <div class="ttg-compare-result" id="ttg-cmp-result" aria-live="polite"></div>
    </div>
  </div>
</section><!-- /#ttg-compare-section -->
<?php endif; ?>

<p style="text-align:right;margin-top:18px;margin-bottom:0;font-size:11px;opacity:0.38;letter-spacing:.01em;" class="ttg-footer-link">
  <a href="https://telegramtrading.net/category/polymarket/" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;border-bottom:1px dotted currentColor;">Polymarket insights &amp; guides</a>
</p>

</div><!-- /.ttg-wrap -->


<!-- ═══════════ WIDGET JAVASCRIPT ═══════════ -->
<script>
(function () {
  'use strict';

  /* ── Config ─────────────────────────────────────────── */
  var POLYGUN = (window.TTG_COPY && window.POLYGUN) ? window.POLYGUN : '';

  /* Exact category mapping matching official API enum */
  var CAT_MAP = {
    'All':      'OVERALL',
    'Politics': 'POLITICS',
    'Macro':    'ECONOMICS',
    'Crypto':   'CRYPTO',
    'Sports':   'SPORTS',
    'Culture':  'CULTURE',
    'Tech':     'TECH',
    'Finance':  'FINANCE'
  };

  var activeCat    = 'All';
  var activePeriod = 'MONTH';   // MONTH = 30D, WEEK = 7D
  var liveData     = [];

  /* ── Formatters ─────────────────────────────────────── */
  function fmt(n) {
    if (n == null || isNaN(n)) return 'N/A';
    var abs = Math.abs(n);
    var str;
    if (abs >= 1e6)      str = '$' + (abs / 1e6).toFixed(2) + 'M';
    else if (abs >= 1e3) str = '$' + (abs / 1e3).toFixed(1) + 'K';
    else                 str = '$' + Math.round(abs);
    return n < 0 ? '−' + str : str;
  }

  function pnl(n) {
    if (n == null || isNaN(n)) return { str: 'N/A', cls: 'neu' };
    var abs = Math.abs(n);
    var num;
    if (abs >= 1e6)      num = (abs / 1e6).toFixed(2) + 'M';
    else if (abs >= 1e3) num = (abs / 1e3).toFixed(1) + 'K';
    else                 num = Math.round(abs).toString();
    var str = (n > 0 ? '+' : n < 0 ? '−' : '') + num + ' USDC';
    return { str: str, cls: n > 0 ? 'pos' : n < 0 ? 'neg' : 'neu' };
  }

  function escH(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function shortAddr(a) {
    if (!a || a.length < 12) return a || '';
    return a.slice(0, 6) + '…' + a.slice(-4);
  }

  function rankCls(i) {
    return ['ttg-rank-1','ttg-rank-2','ttg-rank-3'][i] || 'ttg-rank-other';
  }

  /* Period label helpers */
  function periodShort() { return activePeriod === 'WEEK' ? '7d'      : '30d'; }
  function periodLong()  { return activePeriod === 'WEEK' ? '7-day'   : '30-day'; }
  function periodWord()  { return activePeriod === 'WEEK' ? 'week'    : 'month'; }

  /* ── Why-badge copy ──────────────────────────────────── */
  function whyBadge(w, i, cat) {
    var p   = parseFloat(w.pnl) || 0;
    var v   = parseFloat(w.vol) || 0;
    var eff = v > 0 ? p / v * 100 : 0;
    var pl  = periodLong();
    if (i === 0)         return '🏆 #1 today, highest ' + pl + ' PNL';
    if (eff > 10)        return '🔥 ' + eff.toFixed(0) + '% PNL/Vol ratio in ' + cat;
    if (p > 8000)        return '⚡ ' + fmt(p) + ' profit this ' + periodWord();
    if (v > 60000)       return '💰 ' + fmt(v) + ' volume, top activity';
    if (i === 1)         return '🥈 Runner-up, consistent ' + cat + ' edge';
    if (i === 2)         return '🥉 Top 3, strong ' + cat + ' track record';
    return '🎯 Ranked #' + (i + 1) + ' in ' + cat + ' this cycle';
  }

  /* ── Fallback demo data ──────────────────────────────── */
  function demoData(cat) {
    var names = ['SmartMoney','AlphaTrader','PredictPro','WhaleBet','EdgeFinder',
                 'MarketMaker','OracleX','SignalBot','DeepValue','QuietEdge'];
    var seed  = Object.keys(CAT_MAP).indexOf(cat);
    return names.map(function (name, i) {
      var h = ((i + 1) * 1597 + (seed + 1) * 2089) & 0x7FFF;
      return {
        rank: String(i + 1), proxyWallet: '0xdemo' + i + 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        userName: name, pnl: 900 + h % 9000, vol: 4000 + h * 19 % 90000,
        profileImage: '', xUsername: '', verifiedBadge: false
      };
    });
  }

  /* ── Desktop table rows ──────────────────────────────── */
  function buildRows(data, cat) {
    var label = cat === 'All' ? 'All' : cat;
    return data.slice(0, 10).map(function (w, i) {
      var addr  = w.proxyWallet || '';
      var short = shortAddr(addr);
      var name  = w.userName || short;
      if (name.length > 15) name = name.slice(0, 15) + '…';
      var p   = pnl(parseFloat(w.pnl) || 0);
      var v   = parseFloat(w.vol) || 0;
      var why = whyBadge(w, i, label);
      var vbg = w.verifiedBadge ? '<span class="ttg-verified" title="Verified">&#10003;</span>' : '';
      var xi  = w.xUsername     ? ' <a class="ttg-x-link" href="https://x.com/' + escH(w.xUsername) + '" target="_blank" rel="noopener noreferrer" title="@' + escH(w.xUsername) + '">&#120143;</a>' : '';

      return '<tr>'
        + '<td><span class="ttg-rank ' + rankCls(i) + '" aria-label="Rank ' + (i+1) + '">' + (i+1) + '</span></td>'
        + '<td>'
          + '<div class="ttg-wallet-cell">'
          + '<div class="ttg-wallet-top">'
          +   '<span class="ttg-wallet-name">' + escH(name) + vbg + xi + '</span>'
          + '</div>'
          + '<div class="ttg-addr"><span>' + short + '</span>'
          + '<button class="ttg-copy-btn" data-addr="' + escH(addr) + '" title="Copy address" aria-label="Copy wallet address">'
          + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
          + '</button></div>'
          + '<span class="ttg-why-badge">' + why + '</span>'
          + '</div>'
        + '</td>'
        + '<td><span class="ttg-pnl ' + p.cls + '">' + p.str + '</span></td>'
        + '<td><span class="ttg-vol">' + fmt(v) + '</span></td>'
        + '<td><span class="ttg-tag">' + escH(label) + '</span></td>'
        + '<td>'
          + '<div class="ttg-row-actions">'
          + (window.TTG_COPY && POLYGUN ? '<a href="' + escH(POLYGUN) + '" class="ttg-btn-sm ttg-btn-copy-trade" target="_blank" rel="noopener noreferrer">&#9889; CopyTrade</a>' : '')
          + '<a href="https://polymarket.com/profile/' + encodeURIComponent(addr) + '" class="ttg-btn-sm ttg-btn-poly" target="_blank" rel="noopener noreferrer">View Profile</a>'
          + '</div>'
        + '</td>'
        + '</tr>';
    }).join('');
  }

  /* ── Mobile cards ────────────────────────────────────── */
  function buildCards(data, cat) {
    var label = cat === 'All' ? 'All' : cat;
    var ps    = periodShort();
    return data.slice(0, 10).map(function (w, i) {
      var addr  = w.proxyWallet || '';
      var short = shortAddr(addr);
      var name  = w.userName || short;
      if (name.length > 15) name = name.slice(0, 15) + '…';
      var p   = pnl(parseFloat(w.pnl) || 0);
      var v   = parseFloat(w.vol) || 0;
      var why = whyBadge(w, i, label);

      return '<article class="ttg-card">'
        + '<div class="ttg-card-header">'
          + '<div class="ttg-card-identity">'
          + '<span class="ttg-rank ' + rankCls(i) + '">' + (i+1) + '</span>'
          + '<div><div class="ttg-wallet-name">' + escH(name) + '</div>'
          + '<div class="ttg-addr-sm">' + short + '</div></div>'
          + '</div>'
          + '<span class="ttg-pnl ' + p.cls + ' ttg-pnl-lg">' + p.str + '</span>'
        + '</div>'
        + '<span class="ttg-why-badge ttg-why-badge-card">' + why + '</span>'
        + '<div class="ttg-card-stats">'
          + '<div class="ttg-card-stat"><span class="ttg-stat-label">' + ps + ' PNL</span><span class="ttg-pnl ' + p.cls + '">' + p.str + '</span></div>'
          + '<div class="ttg-card-stat"><span class="ttg-stat-label">Volume</span><span class="ttg-vol">' + fmt(v) + '</span></div>'
          + '<div class="ttg-card-stat"><span class="ttg-stat-label">Rank</span><span class="ttg-stat-val">' + (w.rank || i+1) + '</span></div>'
          + '<div class="ttg-card-stat"><span class="ttg-stat-label">Focus</span><span class="ttg-tag">' + escH(label) + '</span></div>'
        + '</div>'
        + '<div class="ttg-card-actions">'
          + (window.TTG_COPY && POLYGUN ? '<a href="' + escH(POLYGUN) + '" class="ttg-btn-sm ttg-btn-copy-trade" style="flex:1" target="_blank" rel="noopener noreferrer">&#9889; CopyTrade</a>' : '')
          + '<a href="https://polymarket.com/profile/' + encodeURIComponent(addr) + '" class="ttg-btn-sm ttg-btn-poly" style="flex:1" target="_blank" rel="noopener noreferrer">View Profile</a>'
        + '</div>'
        + '</article>';
    }).join('');
  }

  /* ── Wallet of the Day ───────────────────────────────── */
  function buildWotdAnalysis(w, cat) {
    var p   = parseFloat(w.pnl) || 0;
    var v   = parseFloat(w.vol) || 0;
    var eff = v > 0 ? (p / v * 100).toFixed(1) : 0;
    var n   = escH(w.userName || 'This wallet');
    var cl  = cat === 'All' ? 'prediction markets' : cat;
    var pl  = periodLong();
    var pw  = periodWord();

    var parts = [];
    parts.push('<strong>' + n + '</strong> holds #1 on today\'s leaderboard with <strong>' + fmt(p) + '</strong> in ' + pl + ' profit.');

    if (parseFloat(eff) > 8) {
      parts.push('A PNL-to-volume ratio of <strong>' + eff + '%</strong> is exceptional, disciplined sizing, not random volume.');
    } else if (v > 50000) {
      parts.push('With <strong>' + fmt(v) + '</strong> in traded volume, this wallet has deep engagement across <strong>' + cl + '</strong> markets.');
    } else {
      parts.push('Focused on <strong>' + cl + '</strong>, concentrating capital on high-conviction positions rather than spreading thin.');
    }

    if (p > 8000) {
      parts.push('Profit at this level in a single ' + pw + ' signals repeatable edge, not a lucky outlier.');
    } else if (p > 3000) {
      parts.push('Consistent profitability above $3K per ' + pw + ' in a volatile category indicates a genuine analytical advantage.');
    }

    return parts.join(' ');
  }

  function renderWotd(data, cat) {
    if (!data || !data.length) return;
    var w     = data[0];
    var addr  = w.proxyWallet || '';
    var short = shortAddr(addr);
    var name  = w.userName || short;
    var p     = pnl(parseFloat(w.pnl) || 0);
    var v     = parseFloat(w.vol) || 0;
    var cLbl  = cat === 'All' ? 'All' : cat;
    var date  = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

    function $id(id) { return document.getElementById(id); }

    if ($id('ttg-wotd-date'))     $id('ttg-wotd-date').textContent     = date;
    if ($id('ttg-wotd-name'))     $id('ttg-wotd-name').textContent     = name.length > 15 ? name.slice(0,15)+'…' : name;
    if ($id('ttg-wotd-addr'))     $id('ttg-wotd-addr').textContent     = short;
    if ($id('ttg-wotd-rank'))     $id('ttg-wotd-rank').textContent     = w.rank || '1';
    if ($id('ttg-wotd-vol'))      $id('ttg-wotd-vol').textContent      = fmt(v);
    if ($id('ttg-wotd-analysis')) $id('ttg-wotd-analysis').innerHTML   = buildWotdAnalysis(w, cLbl);
    if ($id('ttg-wotd-pnl-label')) $id('ttg-wotd-pnl-label').textContent = periodShort() + ' PNL';

    var pnlEl = $id('ttg-wotd-pnl');
    if (pnlEl) {
      pnlEl.textContent = p.str;
      pnlEl.className   = 'ttg-wotd-stat-value ' + p.cls;
    }

    var shareEl = $id('ttg-wotd-share');
    if (shareEl) {
      var tweet = '🏆 Polymarket Wallet of the Day: ' + name + ': ' + p.str + ' this ' + periodWord() + '.';
      shareEl.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(tweet);
    }

    var polyLink = $id('ttg-wotd-poly-link');
    if (polyLink && addr) {
      polyLink.href = 'https://polymarket.com/profile/' + encodeURIComponent(addr);
    }

    var copyBtn = $id('ttg-wotd-copy-btn');
    if (copyBtn) {
      copyBtn.onclick = function () {
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(addr).then(function () {
          copyBtn.style.color = '#00e09a';
          setTimeout(function () { copyBtn.style.color = ''; }, 1500);
        });
      };
    }

    var sec = document.getElementById('ttg-wotd-section');
    if (sec) sec.style.display = '';
  }

  /* ── Compare tool ────────────────────────────────────── */
  function populateSelects(data) {
    var opts = '<option value="">- Select wallet -</option>';
    data.slice(0, 10).forEach(function (w, i) {
      var name = (w.userName || shortAddr(w.proxyWallet || '')).slice(0, 28);
      opts += '<option value="' + i + '">#' + (i+1) + ' ' + escH(name) + '</option>';
    });
    var a = document.getElementById('ttg-cmp-a');
    var b = document.getElementById('ttg-cmp-b');
    if (a) { a.innerHTML = opts; a.value = '0'; }
    if (b) { b.innerHTML = opts; b.value = '1'; }
  }

  window.ttgToggleCompare = function () {
    var panel  = document.getElementById('ttg-cmp-panel');
    var toggle = document.getElementById('ttg-cmp-toggle');
    if (!panel) return;
    var isOpen = !panel.hidden;
    panel.hidden = isOpen;
    if (toggle) {
      toggle.setAttribute('aria-expanded', String(!isOpen));
      toggle.classList.toggle('open', !isOpen);
    }
  };

  window.ttgRunCompare = function () {
    var aEl  = document.getElementById('ttg-cmp-a');
    var bEl  = document.getElementById('ttg-cmp-b');
    var res  = document.getElementById('ttg-cmp-result');
    if (!res) return;

    var ai = parseInt(aEl ? aEl.value : '');
    var bi = parseInt(bEl ? bEl.value : '');

    if (isNaN(ai) || isNaN(bi)) {
      res.innerHTML = '<p class="ttg-no-results">Please select two wallets to compare.</p>'; return;
    }
    if (ai === bi) {
      res.innerHTML = '<p class="ttg-no-results">Please select two <em>different</em> wallets.</p>'; return;
    }

    var wa = liveData[ai], wb = liveData[bi];
    if (!wa || !wb) {
      res.innerHTML = '<p class="ttg-no-results">Data not available. Please refresh the leaderboard first.</p>'; return;
    }

    var pA = parseFloat(wa.pnl) || 0, pB = parseFloat(wb.pnl) || 0;
    var vA = parseFloat(wa.vol) || 0, vB = parseFloat(wb.vol) || 0;
    var eA = vA > 0 ? pA / vA * 100 : 0;
    var eB = vB > 0 ? pB / vB * 100 : 0;

    var maxP = Math.max(Math.abs(pA), Math.abs(pB), 1);
    var maxV = Math.max(vA, vB, 1);
    var maxE = Math.max(Math.abs(eA), Math.abs(eB), 0.01);

    var nA = (wa.userName || shortAddr(wa.proxyWallet || '')).slice(0, 22);
    var nB = (wb.userName || shortAddr(wb.proxyWallet || '')).slice(0, 22);
    var winsA = pA >= pB;

    function cmpCard(w, idx, name, isWinner) {
      var cp  = pnl(parseFloat(w.pnl) || 0);
      var cv  = parseFloat(w.vol) || 0;
      var ce  = cv > 0 ? (parseFloat(w.pnl) || 0) / cv * 100 : 0;
      var eCls = ce >= 0 ? 'pos' : 'neg';

      return '<div class="ttg-cmp-card ' + (isWinner ? 'ttg-cmp-winner' : 'ttg-cmp-loser') + '">'
        + '<div class="ttg-cmp-head">'
          + '<div><div class="ttg-cmp-name">' + escH(name) + '</div>'
          + '<div class="ttg-cmp-addr">' + shortAddr(w.proxyWallet||'') + '</div></div>'
          + (isWinner ? '<span class="ttg-cmp-badge">🏆 Leading</span>' : '')
        + '</div>'
        + '<div class="ttg-cmp-rows">'
          + cmpRow(periodShort() + ' PNL', cp.str,             Math.abs(parseFloat(w.pnl)||0) / maxP * 100, cp.cls)
          + cmpRow('Volume',               fmt(cv),            cv / maxV * 100,                             'neu')
          + cmpRow('PNL/Vol',              ce.toFixed(1)+'%',  Math.abs(ce) / maxE * 100,                  eCls)
          + cmpRow('Rank',                 '#' + (idx+1),      0,                                           'neu', true)
        + '</div>'
        + '</div>';
    }

    function cmpRow(label, val, pct, cls, noBar) {
      return '<div class="ttg-cmp-row">'
        + '<span class="ttg-cmp-row-lbl">' + label + '</span>'
        + '<div class="ttg-cmp-row-right">'
          + '<span class="ttg-cmp-row-val ' + cls + '">' + val + '</span>'
          + (noBar ? '' : '<div class="ttg-bar-wrap"><div class="ttg-bar-fill" style="width:' + Math.min(100, pct) + '%"></div></div>')
        + '</div>'
        + '</div>';
    }

    var winner = winsA ? nA : nB;
    var loser  = winsA ? nB : nA;
    var diff   = Math.abs(pA - pB);
    var eWin   = winsA ? eA : eB;
    var eLos   = winsA ? eB : eA;

    var verdict = '<strong>' + escH(winner) + '</strong> leads with <strong>'
      + fmt(Math.max(pA,pB)) + '</strong>, ' + fmt(diff) + ' ahead of ' + escH(loser) + '. ';
    verdict += eWin > eLos
      ? 'Capital efficiency also favors ' + escH(winner) + ' at ' + eWin.toFixed(1) + '% vs ' + eLos.toFixed(1) + '% PNL/Vol. '
      : escH(loser) + ' has better capital efficiency (' + eLos.toFixed(1) + '% vs ' + eWin.toFixed(1) + '%) - worth watching. ';
    verdict += 'View full stats on <a href="https://polymarket.com/" target="_blank" rel="noopener noreferrer">Polymarket</a>.';

    res.innerHTML =
        '<div class="ttg-cmp-grid">'
      + cmpCard(wa, ai, nA, winsA)
      + cmpCard(wb, bi, nB, !winsA)
      + '</div>'
      + '<div class="ttg-cmp-verdict"><span aria-hidden="true">📊</span><div>' + verdict + '</div></div>'
      + '<p class="ttg-cmp-note">⚠ Win rate estimated from PNL/volume ratio. View full stats directly on Polymarket.</p>';

    res.classList.add('show');
    res.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  /* ── Render ──────────────────────────────────────────── */
  function loading() {
    var tb = document.getElementById('ttg-tbody');
    var cd = document.getElementById('ttg-cards');
    if (tb) tb.innerHTML = '<tr><td colspan="6"><div class="ttg-loading"><div class="ttg-spinner"></div>Fetching top wallets&hellip;</div></td></tr>';
    if (cd) cd.innerHTML = '<div class="ttg-loading"><div class="ttg-spinner"></div>Fetching wallets&hellip;</div>';
  }

  function render(data, cat) {
    liveData = Array.isArray(data) ? data : [];

    var tb     = document.getElementById('ttg-tbody');
    var cd     = document.getElementById('ttg-cards');
    var ts     = document.getElementById('ttg-ts');
    var cmpRes = document.getElementById('ttg-cmp-result');

    if (tb) {
      tb.innerHTML = liveData.length
        ? buildRows(liveData, cat)
        : '<tr><td colspan="6"><p class="ttg-no-results">No data available for this category. Try refreshing.</p></td></tr>';
    }
    if (cd) {
      cd.innerHTML = liveData.length
        ? buildCards(liveData, cat)
        : '<p class="ttg-no-results">No data available. Try refreshing.</p>';
    }
    if (ts) ts.textContent = 'Updated ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    if (cmpRes) { cmpRes.classList.remove('show'); cmpRes.innerHTML = ''; }

    // Update table header
    var hdr = document.getElementById('ttg-pnl-header');
    if (hdr) hdr.innerHTML = periodShort() + '&nbsp;PNL';

    // Update period label in crawler note
    var lbl = document.getElementById('ttg-period-label');
    if (lbl) lbl.textContent = periodLong();

    try { renderWotd(liveData, cat); } catch (e) { console.warn('[TTG] renderWotd:', e); }
    try { populateSelects(liveData); } catch (e) { console.warn('[TTG] populateSelects:', e); }
  }

  /* ── Fetch — always fresh, no client-side caching ────── */
  function fetchCat(cat, force) {
    var btn = document.getElementById('ttg-rbtn');

    function lock()   { if (btn) { btn.classList.add('ttg-spinning'); btn.disabled = true; } }
    function unlock() { if (btn) { btn.classList.remove('ttg-spinning'); btn.disabled = false; } }

    /* Serve server-side seed on very first load (30D / All) */
    if (!force && cat === 'All' && activePeriod === 'MONTH' && window.TTG_SEED && window.TTG_SEED.length) {
      render(window.TTG_SEED, cat);
      window.TTG_SEED = null;
      return;
    }

    lock();
    loading();

    var apiCat = CAT_MAP[cat] || 'OVERALL';
    var url;
    if (window.TTG && window.TTG.ajax && window.TTG.nonce) {
      url = window.TTG.ajax + '?action=ttg_leaderboard'
          + '&category=' + encodeURIComponent(apiCat)
          + '&period='   + encodeURIComponent(activePeriod)
          + '&nonce='    + encodeURIComponent(window.TTG.nonce);
    } else {
      /* Direct fallback */
      url = 'https://data-api.polymarket.com/v1/leaderboard'
          + '?limit=10&offset=0&timePeriod=' + encodeURIComponent(activePeriod) + '&orderBy=PNL'
          + '&category=' + encodeURIComponent(apiCat);
    }

    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var tid  = setTimeout(function () {
      if (ctrl) ctrl.abort();
      console.warn('[TTG] Request timed out, using demo data.');
      render(demoData(cat), cat);
      unlock();
    }, 10000);

    fetch(url, {
      method:  'GET',
      headers: { 'Accept': 'application/json' },
      signal:  ctrl ? ctrl.signal : undefined
    })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      clearTimeout(tid);
      var list = Array.isArray(d) ? d : (d.data || d.results || d.leaderboard || []);
      if (!Array.isArray(list) || list.length === 0) throw new Error('Empty response');
      render(list, cat);
      unlock();
    })
    .catch(function (err) {
      clearTimeout(tid);
      if (err && err.name === 'AbortError') return;
      console.warn('[TTG] Fetch error, showing demo data:', err.message);
      render(demoData(cat), cat);
      unlock();
    });
  }

  /* ── Public API ──────────────────────────────────────── */
  window.ttgFilter = function (cat, btn) {
    document.querySelectorAll('.ttg-filter-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    activeCat = cat;
    fetchCat(cat, false);
  };

  window.ttgFetch = function () { fetchCat(activeCat, true); };

  /* ── Event listeners ────────────────────────────────── */
  function ttgBind() {
    // Refresh button
    var rbtn = document.getElementById('ttg-rbtn');
    if (rbtn) { rbtn.addEventListener('click', function () { ttgFetch(); }); }

    // Filter buttons
    document.querySelectorAll('.ttg-filter-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { window.ttgFilter(btn.dataset.cat || 'All', btn); });
    });

    // Period toggle buttons (30D / 7D)
    document.querySelectorAll('.ttg-period-opt').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var newPeriod = btn.dataset.period || 'MONTH';
        if (newPeriod === activePeriod) return;
        activePeriod = newPeriod;
        // Update toggle UI
        document.querySelectorAll('.ttg-period-opt').forEach(function (b) {
          b.classList.remove('active');
          b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        // Force fresh fetch with new period
        fetchCat(activeCat, true);
      });
    });

    // Compare toggle
    var cmpToggle = document.getElementById('ttg-cmp-toggle');
    if (cmpToggle) { cmpToggle.addEventListener('click', function () { window.ttgToggleCompare(); }); }

    // Compare run
    var cmpRun = document.getElementById('ttg-cmp-run');
    if (cmpRun) { cmpRun.addEventListener('click', function () { window.ttgRunCompare(); }); }

    // Copy address buttons (delegated)
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ttg-copy-btn');
      if (!btn) return;
      var addr = btn.dataset.addr || '';
      if (!addr || !navigator.clipboard) return;
      navigator.clipboard.writeText(addr).then(function () {
        btn.style.color = '#00b87a';
        setTimeout(function () { btn.style.color = ''; }, 1500);
      });
    });
  }

  /* ── Boot ────────────────────────────────────────────── */
  ttgBind();
  fetchCat('All', false);

}());
</script>
<?php
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════════
   ADMIN  - menu, settings, cache management
═══════════════════════════════════════════════════════════ */
add_action( 'admin_menu', 'ttg_admin_menu' );
function ttg_admin_menu(): void {
    add_options_page(
        'Polymarket Leaderboard Settings',
        'Polymarket LB',
        'manage_options',
        'ttg-leaderboard',
        'ttg_admin_page'
    );
}

/* ── Admin notices ── */
add_action( 'admin_notices', function (): void {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'ttg-leaderboard' ) === false ) { return; }
    if ( ! empty( $_GET['ttg_saved'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Polymarket LB:</strong> Settings saved.</p></div>';
    }
} );

/* ── Admin page ── */
function ttg_admin_page(): void {
    $opts     = ttg_get_options();
    $is_setup = ! empty( $_GET['ttg_setup'] );
    ?>
    <div class="wrap" id="ttg-admin">
    <style>
    #ttg-admin .ttg-feature-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin:18px 0 24px;}
    #ttg-admin .ttg-fcard{background:#fff;border:2px solid #dce8f8;border-radius:12px;padding:20px 22px;transition:border-color .2s;}
    #ttg-admin .ttg-fcard.ttg-fcard-on{border-color:#2271b1;}
    #ttg-admin .ttg-fcard-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;}
    #ttg-admin .ttg-fcard-icon{font-size:28px;line-height:1;}
    #ttg-admin .ttg-fcard h3{font-size:14px;font-weight:700;color:#1d2327;margin:0 0 5px;}
    #ttg-admin .ttg-fcard p{font-size:13px;color:#50575e;line-height:1.6;margin:0;}
    #ttg-admin .ttg-section-head{border-bottom:1px solid #dcdcde;margin:28px 0 16px;padding-bottom:8px;}
    #ttg-admin .ttg-section-head h2{font-size:16px;margin:0;}
    /* Theme picker */
    #ttg-admin .ttg-theme-picker{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}
    #ttg-admin .ttg-theme-opt{display:flex;align-items:center;gap:9px;cursor:pointer;font-size:13px;font-weight:600;padding:10px 16px;border:2px solid #dce8f8;border-radius:9px;transition:border-color .15s;flex:1;min-width:130px;}
    #ttg-admin .ttg-theme-opt input{display:none;}
    #ttg-admin .ttg-theme-opt.ttg-theme-sel{border-color:#2271b1;}
    #ttg-admin .ttg-theme-swatch{width:22px;height:22px;border-radius:5px;flex-shrink:0;border:1px solid rgba(0,0,0,.12);}
    #ttg-admin .ttg-theme-opt-navy{background:#06122a;color:#c8deff!important;border-color:#1a3a60;}
    #ttg-admin .ttg-theme-opt-navy.ttg-theme-sel{border-color:#7eb8f7;}
    #ttg-admin .ttg-live-badge{display:inline-flex;align-items:center;gap:7px;background:#e8f9f2;border:1px solid #a8e8cc;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;color:#0a6040;}
    #ttg-admin .ttg-live-dot{width:8px;height:8px;border-radius:50%;background:#00b87a;}
    </style>

    <h1>&#127942; Polymarket Leaderboard <small style="font-size:13px;font-weight:400;color:#646970;">v<?php echo esc_html( TTG_VERSION ); ?></small></h1>

    <?php if ( $is_setup ) : ?>
    <div class="notice notice-info" style="margin:12px 0 20px"><p>
        <strong>Welcome!</strong> Enable optional features below and choose your preferred widget theme.
    </p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ttg_save_options">
        <?php wp_nonce_field( 'ttg_save_options' ); ?>

        <div class="ttg-section-head"><h2>&#9881; Widget Features</h2></div>
        <p style="color:#50575e;font-size:13px;margin-bottom:16px;">Enable the optional extras below.</p>

        <div class="ttg-feature-cards">

            <!-- Wallet of the Day -->
            <div class="ttg-fcard<?php echo $opts['show_wotd'] ? ' ttg-fcard-on' : ''; ?>" id="ttg-card-wotd">
                <div class="ttg-fcard-head">
                    <span class="ttg-fcard-icon">&#11088;</span>
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:600;">
                        <input type="checkbox" name="show_wotd" value="1" id="ttg-cb-wotd" data-card="wotd" <?php checked( $opts['show_wotd'], 1 ); ?>>
                        Enable
                    </label>
                </div>
                <h3>Wallet of the Day</h3>
                <p>Spotlights the #1 ranked wallet each day with a performance breakdown card. Purely informational — shows public on-chain data only.</p>
            </div>

            <!-- Compare tool -->
            <div class="ttg-fcard<?php echo $opts['show_compare'] ? ' ttg-fcard-on' : ''; ?>" id="ttg-card-compare">
                <div class="ttg-fcard-head">
                    <span class="ttg-fcard-icon">&#9878;</span>
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:600;">
                        <input type="checkbox" name="show_compare" value="1" id="ttg-cb-compare" data-card="compare" <?php checked( $opts['show_compare'], 1 ); ?>>
                        Enable
                    </label>
                </div>
                <h3>Wallet Compare Tool</h3>
                <p>Lets visitors compare any two wallets from the leaderboard side-by-side: PNL, volume, and efficiency ratio. No external links involved.</p>
            </div>

            <!-- Theme -->
            <div class="ttg-fcard" id="ttg-card-theme">
                <div class="ttg-fcard-head">
                    <span class="ttg-fcard-icon">&#127912;</span>
                </div>
                <h3>Widget Theme</h3>
                <p>Choose the visual style for your leaderboard widget. White is the clean default; Navy Blue uses a dark background with white text.</p>
                <div class="ttg-theme-picker">
                    <label class="ttg-theme-opt<?php echo $opts['theme'] !== 'navy' ? ' ttg-theme-sel' : ''; ?>" id="ttg-topt-white">
                        <input type="radio" name="theme" value="white" <?php checked( $opts['theme'] !== 'navy', true ); ?>>
                        <span class="ttg-theme-swatch" style="background:#ffffff;border:1px solid #dce8f8;"></span>
                        &#9728; White
                    </label>
                    <label class="ttg-theme-opt ttg-theme-opt-navy<?php echo $opts['theme'] === 'navy' ? ' ttg-theme-sel' : ''; ?>" id="ttg-topt-navy">
                        <input type="radio" name="theme" value="navy" <?php checked( $opts['theme'], 'navy' ); ?>>
                        <span class="ttg-theme-swatch" style="background:#0a1d3f;border:1px solid #1a3a60;"></span>
                        &#127771; Navy Blue
                    </label>
                </div>
            </div>

        </div>

        <p>
            <button type="submit" class="button button-primary" style="font-size:14px;padding:6px 18px;">
                &#10003; Save Settings
            </button>
            <?php if ( $is_setup ) : ?>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ttg-leaderboard' ) ); ?>" class="button" style="margin-left:8px;">
                Skip for now
            </a>
            <?php endif; ?>
        </p>
    </form>

    <!-- ── API Status ── -->
    <div class="ttg-section-head"><h2>&#128202; Data Source</h2></div>
    <p>
        <span class="ttg-live-badge"><span class="ttg-live-dot"></span> Live API — No Caching</span>
    </p>
    <p style="font-size:13px;color:#50575e;margin-top:10px;">
        This plugin fetches fresh data from the Polymarket API on every request. No transient caching is used, so visitors always see the latest leaderboard rankings.
    </p>

    <!-- ── Usage ── -->
    <div class="ttg-section-head"><h2>&#128196; Usage</h2></div>
    <table class="form-table" style="max-width:640px"><tbody>
        <tr><th>Shortcode</th><td><code>[polymarket_leaderboard]</code></td></tr>
        <tr><th>Default category</th><td><code>[polymarket_leaderboard category="CRYPTO"]</code></td></tr>
        <tr><th>API</th><td><code>https://data-api.polymarket.com/v1/leaderboard</code></td></tr>
        <tr><th>Periods</th><td>30D (MONTH) and 7D (WEEK) — toggled on the widget by visitors.</td></tr>
        <tr><th>Theme</th><td>White or Navy Blue — set above and applied to all instances of the shortcode.</td></tr>
    </tbody></table>

    </div><!-- /.wrap -->

    <script>
    // Checkbox → card border
    document.querySelectorAll('[data-card]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var el = document.getElementById('ttg-card-' + cb.dataset.card);
            if (el) el.classList.toggle('ttg-fcard-on', cb.checked);
        });
    });
    // Theme radio → selected highlight
    document.querySelectorAll('[name="theme"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.querySelectorAll('.ttg-theme-opt').forEach(function(o) { o.classList.remove('ttg-theme-sel'); });
            r.closest('.ttg-theme-opt').classList.add('ttg-theme-sel');
        });
    });
    </script>
    <?php
}
