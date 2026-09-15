<?php
if (!defined('ABSPATH')) { exit; }
$org_name = stageart_theme_org('name', get_bloginfo('name'));
$social = ['x'=>'X','instagram'=>'Instagram','youtube'=>'YouTube','facebook'=>'Facebook'];
$links = [];
foreach ($social as $key=>$label) { $url=stageart_theme_org($key); if($url) $links[]='<li><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.esc_html($label).'</a></li>'; }
?>
</div>
<footer class="stageart-site-footer"><div class="stageart-container stageart-footer-inner">
<div><div class="stageart-footer-brand"><?php echo esc_html($org_name); ?></div><p class="stageart-footer-copy">舞台芸術を、もっと自由に。</p><p class="stageart-footer-copy">&copy; <?php echo esc_html((string)wp_date('Y')); ?> <?php echo esc_html($org_name); ?></p></div>
<?php if($links): ?><ul class="stageart-footer-social"><?php echo implode('',$links); ?></ul><?php endif; ?>
</div></footer>
<?php wp_footer(); ?></body></html>
