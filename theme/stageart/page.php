<?php get_header(); ?>
<main class="stageart-container stageart-page">
  <header class="stageart-page-header">
    <p class="stageart-kicker">StageArt</p>
    <h1><?php echo esc_html(get_the_title()); ?></h1>
  </header>
  <div class="stageart-content">
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </div>
</main>
<?php get_footer(); ?>
