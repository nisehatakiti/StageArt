<?php
get_header();
$id = (int) get_option('stageart_plugin_contact_page_id', 0);
$address = (string) get_post_meta($id, '_stageart_contact_address', true);
$email = (string) get_post_meta($id, '_stageart_contact_email', true);
$phone = (string) get_post_meta($id, '_stageart_contact_phone', true);
?>
<main class="stageart-container stageart-page">
  <header class="stageart-page-header"><p class="stageart-kicker">Contact</p><h1><?php the_title(); ?></h1></header>
  <div class="stageart-content">
    <?php if ($address) : ?><p><strong>所在地</strong><br><?php echo nl2br(esc_html($address)); ?></p><?php endif; ?>
    <?php if ($email) : ?><p><strong>メールアドレス</strong><br><a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></p><?php endif; ?>
    <?php if ($phone) : ?><p><strong>電話番号</strong><br><a href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p><?php endif; ?>
    <?php while (have_posts()) : the_post(); if (trim((string) get_the_content())) : the_content(); endif; endwhile; ?>
  </div>
</main>
<?php get_footer(); ?>
