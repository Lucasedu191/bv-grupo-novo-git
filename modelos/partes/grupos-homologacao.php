<?php
if (!defined('ABSPATH')) exit;

$section_title = isset($view_data['section_title']) ? $view_data['section_title'] : '';
$cards = isset($view_data['cards']) && is_array($view_data['cards']) ? $view_data['cards'] : [];
$note = isset($view_data['note']) ? $view_data['note'] : '';
$empty_message = isset($view_data['empty_message']) ? $view_data['empty_message'] : '';
?>
<section class="bvgn-hml">
  <?php if ($section_title !== ''): ?>
    <header class="bvgn-hml__header">
      <h2 class="bvgn-hml__title"><?php echo esc_html($section_title); ?></h2>
    </header>
  <?php endif; ?>

  <?php if (!empty($cards)): ?>
    <div class="bvgn-hml__grid">
      <?php foreach ($cards as $card): ?>
        <?php include BVGN_CAMINHO . 'modelos/partes/grupo-card-homologacao.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($note !== ''): ?>
      <p class="bvgn-hml__note"><?php echo esc_html($note); ?></p>
    <?php endif; ?>
  <?php else: ?>
    <p class="bvgn-hml__empty"><?php echo esc_html($empty_message); ?></p>
  <?php endif; ?>
</section>
