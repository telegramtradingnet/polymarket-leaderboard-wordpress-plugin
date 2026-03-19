<?php
/**
 * Uninstall — Polymarket Top Wallets Leaderboard
 *
 * Runs automatically when the plugin is deleted via the WordPress admin.
 * Removes all transients and options created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove all cached leaderboard data
$categories = [ 'overall', 'politics', 'sports', 'crypto', 'culture',
                'mentions', 'weather', 'economics', 'tech', 'finance' ];
foreach ( $categories as $cat ) {
    delete_transient( 'ttg_lb3_' . $cat );
}

// Remove plugin options
delete_option( 'ttg_options' );
