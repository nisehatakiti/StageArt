<?php
if (!defined('ABSPATH')) { exit; }
$org_name = stageart_theme_org('name', get_bloginfo('name'));
?>
</div>
<footer class="stageart-site-footer">
  <div class="stageart-container stageart-footer-inner">
    <div><strong><?php echo esc_html($org_name); ?></strong><br><span>&copy; <?php echo esc_html((string) wp_date('Y')); ?></span></div>
    <?php
    $social = ['x' => 'X', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'facebook' => 'Facebook'];
    $links = [];
    foreach ($social as $key => $label) {
        $url = stageart_theme_org($key);
        if ($url) { $links[] = '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a></li>'; }
    }
    if ($links) { echo '<ul class="stageart-footer-social">' . implode('', $links) . '</ul>'; }
    ?>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
