<?php
if (!defined('ABSPATH')) { exit; }
$org_name = stageart_theme_org('name', get_bloginfo('name'));
?>
<!doctype html>
<html <?php language_attributes(); ?>><head>
<meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head><body <?php body_class(); ?>><?php wp_body_open(); ?>
<header class="stageart-site-header"><div class="stageart-header-inner">
<a class="stageart-brand" href="<?php echo esc_url(home_url('/')); ?>">
<?php if (has_custom_logo()) { the_custom_logo(); } else { echo '<span class="stageart-brand-name">' . esc_html($org_name) . '</span>'; } ?>
</a>
<?php if (has_nav_menu('primary')) : ?>
<?php wp_nav_menu(['theme_location'=>'primary','container'=>'nav','container_class'=>'stageart-nav','fallback_cb'=>false]); ?>
<?php else : stageart_theme_menu_fallback(); endif; ?>
</div></header><div class="stageart-main">
