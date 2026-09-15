<?php
get_header();
$members = [];
if (class_exists('StageArtCore\\Domain\\Member\\MemberRepository')) {
    $members = (new StageArtCore\\Domain\\Member\\MemberRepository())->all(true);
}
?>
<main class="stageart-container stageart-page">
  <header class="stageart-page-header"><p class="stageart-kicker">Members</p><h1><?php the_title(); ?></h1></header>
  <?php if ($members) : ?>
    <div class="stageart-members">
    <?php foreach ($members as $member) : ?>
      <article class="stageart-card">
        <a class="stageart-card-link" href="<?php echo esc_url(home_url('/member/' . rawurlencode((string) $member['slug']) . '/')); ?>">
          <?php if (!empty($member['photo_id'])) : ?><div class="stageart-member-card-image"><?php echo wp_get_attachment_image((int) $member['photo_id'], 'medium'); ?></div><?php else : ?><div class="stageart-member-card-image" aria-hidden="true"></div><?php endif; ?>
          <div class="stageart-member-card-body"><h3><?php echo esc_html((string) $member['name']); ?></h3>
          <?php if (!empty($member['roles'])) : ?><p class="stageart-member-card-roles"><?php echo esc_html(implode(' / ', array_map(static fn($r) => StageArtCore\\Domain\\Member\\MemberRepository::ROLES[$r] ?? $r, $member['roles']))); ?></p><?php endif; ?></div>
        </a>
      </article>
    <?php endforeach; ?>
    </div>
  <?php else : ?><p class="stageart-placeholder">現在公開されているメンバーはいません。</p><?php endif; ?>
</main>
<?php get_footer(); ?>
