<?php
/**
 * StageArt theme bootstrap.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function stageart_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 80, 'width' => 320, 'flex-height' => true, 'flex-width' => true]);
    register_nav_menus(['primary' => 'メインメニュー']);
}
add_action('after_setup_theme', 'stageart_theme_setup');

function stageart_theme_assets(): void
{
    wp_enqueue_style('stageart-style', get_stylesheet_uri(), [], '1.0.0');
}
add_action('wp_enqueue_scripts', 'stageart_theme_assets');

function stageart_theme_released($value): bool
{
    if (!$value) {
        return true;
    }
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $now >= new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return false;
    }
}

function stageart_theme_org(string $key, string $default = ''): string
{
    return (string) get_option('stageart_org_' . $key, $default);
}

function stageart_theme_body_classes(array $classes): array
{
    if (function_exists('get_query_var') && get_query_var('stageart_production_slug')) {
        $classes[] = 'stageart-production-view';
    }
    if (function_exists('get_query_var') && get_query_var('stageart_member_slug')) {
        $classes[] = 'stageart-member-view';
    }
    return $classes;
}
add_filter('body_class', 'stageart_theme_body_classes');

function stageart_theme_menu_fallback(): void
{
    echo '<nav class="stageart-nav" aria-label="メインメニュー"><ul>';
    echo '<li><a href="' . esc_url(home_url('/')) . '">ホーム</a></li>';
    echo '<li><a href="' . esc_url(home_url('/production/')) . '">公演</a></li>';
    echo '<li><a href="' . esc_url(home_url('/members/')) . '">メンバー</a></li>';
    echo '</ul></nav>';
}

function stageart_theme_production_url(WP_Post $post): string
{
    return home_url('/production/' . rawurlencode($post->post_name) . '/');
}

function stageart_theme_render_production_card(WP_Post $post): void
{
    $meta = get_post_meta($post->ID);
    $summary = (string) ($meta['summary'][0] ?? '');
    $summary_release = (string) ($meta['summary_release'][0] ?? '');
    $image_id = (int) ($meta['main_image_id'][0] ?? 0);
    $image_release = (string) ($meta['main_image_release'][0] ?? '');
    $schedule_start = (string) ($meta['schedule_start'][0] ?? '');
    $schedule_end = (string) ($meta['schedule_end'][0] ?? '');

    echo '<article class="stageart-card">';
    echo '<a class="stageart-card-link" href="' . esc_url(stageart_theme_production_url($post)) . '">';
    if ($image_id && stageart_theme_released($image_release)) {
        echo '<div class="stageart-card-image">' . wp_get_attachment_image($image_id, 'large') . '</div>';
    } else {
        echo '<div class="stageart-card-image" aria-hidden="true"></div>';
    }
    echo '<div class="stageart-card-body">';
    echo '<h3>' . esc_html($post->post_title) . '</h3>';
    if ($schedule_start || $schedule_end) {
        echo '<p class="stageart-card-meta">' . esc_html($schedule_start) . ($schedule_end ? ' ～ ' . esc_html($schedule_end) : '') . '</p>';
    }
    if (stageart_theme_released($summary_release) && $summary) {
        echo '<p class="stageart-card-summary">' . esc_html($summary) . '</p>';
    }
    echo '</div></a></article>';
}
