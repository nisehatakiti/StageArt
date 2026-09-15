<?php get_header(); ?>
<main class="stageart-container stageart-page">
  <header class="stageart-page-header"><p class="stageart-kicker">StageArt</p><h1><?php echo esc_html(get_bloginfo('name')); ?></h1></header>
  <?php if (have_posts()) : ?>
    <div class="stageart-content">
      <?php while (have_posts()) : the_post(); ?>
        <article><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?></article>
      <?php endwhile; ?>
    </div>
  <?php else : ?>
    <p class="stageart-placeholder">コンテンツがありません。</p>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
