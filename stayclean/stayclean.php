<?php

/**
 * Plugin Name: Stayclean
 * Plugin URI:  http://github.com/zares/wp-stayclean
 * Description: Wordpress Frontend Cleaner
 * Version:     1.0
 * Author:      S.Zares
 * Author URI:  http://github.com/zares
 * Text Domain: stayclean
 * License:     MIT
 */

if (! defined('ABSPATH')) exit;

/**
 * The basic cleanup
 */
add_action('init', function () {

    // Remove WP Head actions
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'index_rel_link');
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');

    add_filter('the_generator', function () { return ''; });

    // Remove Emojis actions
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_action('wp_print_styles', 'print_emoji_styles');

    add_filter('wp_resource_hints', 'stayclean_remove_emojis_prefetch', 10, 2);
    add_filter('tiny_mce_plugins', 'stayclean_disable_emojis_tinymce');
    add_filter('emoji_svg_url', '__return_false');

    // Remove REST API actions
    remove_action('template_redirect', 'rest_output_link_header', 11, 0);
    remove_action('wp_head', 'rest_output_link_wp_head', 10);

    // Remove the REST API endpoint
    if (! is_user_logged_in()) {
        remove_action('rest_api_init', 'wp_oembed_register_route');
    }
});

// Filter for disabling tinymce in emojis
function stayclean_disable_emojis_tinymce($plugins) {
    if (is_array($plugins)) {
        return array_diff($plugins, ['wpemoji']);
    } else {
        return [];
    }
}

// Filter for removing the emojis dns prefetch
function stayclean_remove_emojis_prefetch($urls, $relation_type) {
    if ('dns-prefetch' == $relation_type) {
        $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
        $urls = array_diff($urls, array($emoji_svg_url));
    }
    return $urls;
}

// Disable self pingbacks
add_action('pre_ping', 'stayclean_disable_self_pingbacks');

function stayclean_disable_self_pingbacks(&$links) {
     $home = get_option('home');
     foreach ($links as $key => $value) {
         if (strpos($value, $home) === 0) {
             unset($links[$key]);
         }
     }
 }

/**
 * Disable XML-RPC
 */
add_filter('pre_update_option_enable_xmlrpc', '__return_false');
add_filter('wp_headers', 'stayclean_remove_x_pingback');
add_filter('pre_option_enable_xmlrpc', '__return_zero');
add_action('init', 'stayclean_intercept_xmlrpc_header');
add_filter('pings_open', '__return_false', 9999);
add_filter('xmlrpc_enabled', '__return_false');

function stayclean_remove_x_pingback($headers) {
    unset($headers['X-Pingback'], $headers['x-pingback']);
    return $headers;
}

function stayclean_intercept_xmlrpc_header() {
    if (! isset( $_SERVER['SCRIPT_FILENAME'])) {
        return;
    }

    if ('xmlrpc.php' !== basename($_SERVER['SCRIPT_FILENAME'])) {
        return;
    }

    $header = 'HTTP/1.1 403 Forbidden';
    header($header);
    echo $header;
    die();
}

/**
 * Disable WPEMBED
 */
add_action('wp_footer', function () { wp_deregister_script('wp-embed'); });
add_action('init', 'stayclean_disable_embeds_code_init', 9999);

function stayclean_disable_embeds_code_init() {
    remove_filter('pre_oembed_result', 'wp_filter_pre_oembed_result', 10);
    remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');

    add_filter('tiny_mce_plugins', 'stayclean_disable_embeds_tiny_mce_plugin');
    add_filter('rewrite_rules_array', 'stayclean_disable_embeds_rewrites');
    add_filter('embed_oembed_discover', '__return_false');
}

function stayclean_disable_embeds_tiny_mce_plugin($plugins) {
    return array_diff($plugins, array('wpembed'));
}

function stayclean_disable_embeds_rewrites($rules) {
    foreach ($rules as $rule => $rewrite) {
        if (false !== strpos($rewrite, 'embed=true')) {
            unset($rules[$rule]);
        }
    }
    return $rules;
}

/**
 * Reduce the information output in case of an unsuccessful login
 */
add_filter('login_errors', function () {
    return "<strong>ERROR</strong>: Wrong inputs data!";
});

