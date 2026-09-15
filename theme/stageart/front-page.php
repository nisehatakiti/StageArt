<?php
get_header();
$org_name = stageart_theme_org('name', get_bloginfo('name'));
$org_description = stageart_theme_org('description');
$productions = new WP_Query([
    'post_type' => 'stageart_production',
    'post_status' => 'publish',
    'posts_per_page' => 6,
    'orderby' => 'date',
    'order' => 'DESC',
]);
?>
<main>
  <section class="stageart-hero">
    <div class="stageart-container">
      <p class="stageart-kicker">StageArt</p>
      <h1><?php echo esc_html($org_name); ?></h1>
      <?php if ($org_description) : ?><p class="stageart-hero-description"><?php echo esc_html($org_description); ?></p><?php endif; ?>
    </div>
  </section>

  <section id="productions" class="stageart-container stageart-section">
    <div class="stageart-section-header">
      <div><p class="stageart-kicker">Productions</p><h2>公演</h2></div>
      <p>舞台から生まれた作品</p>
    </div>
    <?php if ($productions->have_posts()) : ?>
      <div class="stageart-production-grid">
        <?php while ($productions->have_posts()) : $productions->the_post(); stageart_theme_render_production_card($post); endwhile; wp_reset_postdata(); ?>
      </div>
    <?php else : ?>
      <p class="stageart-placeholder">現在公開されている公演はありません。</p>
    <?php endif; ?>
  </section>

  <?php if ($org_description) : ?>
  <section class="stageart-container stageart-section">
    <div class="stageart-section-header"><div><p class="stageart-kicker">About</p><h2>私たちについて</h2></div></div>
    <div class="stageart-content"><p><?php echo esc_html($org_description); ?></p></div>
  </section>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
