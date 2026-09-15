<?php
get_header();
$org_name = stageart_theme_org('name', get_bloginfo('name'));
$org_description = stageart_theme_org('description');
$productions = new WP_Query([
    'post_type' => 'stageart_production', 'post_status' => 'publish',
    'posts_per_page' => 6, 'orderby' => 'date', 'order' => 'DESC',
]);
$news = new WP_Query([
    'post_type' => 'post', 'post_status' => 'publish',
    'posts_per_page' => 4, 'orderby' => 'date', 'order' => 'DESC',
]);
?>
<main>
<section class="stageart-hero">
  <div class="stageart-container stageart-hero-copy">
    <p class="stageart-kicker">Theatre / Stage Art</p>
    <h1><?php echo esc_html($org_name); ?></h1>
    <?php if ($org_description) : ?><p class="stageart-hero-description"><?php echo esc_html($org_description); ?></p><?php endif; ?>
    <div class="stageart-hero-actions">
      <a class="stageart-button stageart-button--light" href="#productions">公演を見る</a>
      <a class="stageart-button stageart-button--accent" href="#about">私たちについて</a>
    </div>
  </div>
  <div class="stageart-hero-mark" aria-hidden="true">幕</div>
</section>

<section id="productions" class="stageart-container stageart-section">
  <div class="stageart-section-header"><div><p class="stageart-kicker">Productions</p><h2 class="stageart-section-title">公演</h2><div class="stageart-section-rule"></div></div><p class="stageart-section-lead">劇場でしか出会えない時間。StageArtに記録された作品と、これから上演される舞台をご紹介します。</p></div>
  <?php if ($productions->have_posts()) : ?><div class="stageart-production-grid"><?php while ($productions->have_posts()) : $productions->the_post(); stageart_theme_render_production_card($post); endwhile; wp_reset_postdata(); ?></div><?php else : ?><p class="stageart-placeholder">現在公開されている公演はありません。</p><?php endif; ?>
</section>

<?php if ($org_description) : ?>
<section id="about" class="stageart-container stageart-section">
  <div class="stageart-feature">
    <div class="stageart-feature-copy"><p class="stageart-kicker">About Us</p><h2>舞台をつくる。</h2><p><?php echo esc_html($org_description); ?></p><a class="stageart-button" href="<?php echo esc_url(home_url('/contact/')); ?>">お問い合わせ</a></div>
    <div class="stageart-feature-image" aria-hidden="true"></div>
  </div>
</section>
<?php endif; ?>

<?php if ($news->have_posts()) : ?>
<section class="stageart-container stageart-section">
  <div class="stageart-section-header"><div><p class="stageart-kicker">News</p><h2 class="stageart-section-title">お知らせ</h2><div class="stageart-section-rule"></div></div><p class="stageart-section-lead">劇団からのお知らせや最新情報</p></div>
  <div class="stageart-news-list">
  <?php while ($news->have_posts()) : $news->the_post(); ?>
    <article class="stageart-news-item"><time class="stageart-news-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3></article>
  <?php endwhile; wp_reset_postdata(); ?>
  </div>
</section>
<?php endif; ?>
</main>
<?php get_footer(); ?>
