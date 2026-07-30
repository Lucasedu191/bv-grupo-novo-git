<?php
if (!defined('ABSPATH')) exit;

$images = isset($card['images']) && is_array($card['images']) ? $card['images'] : [];
$has_gallery = count($images) > 1;
$card_title = trim((string) ($card['models'] ?? ''));
$fallback_title = trim((string) ($card['fallback_title'] ?? ''));
?>
<article class="bvgn-hml-card">
  <div class="bvgn-hml-card__top">
    <span class="bvgn-hml-card__badge"><?php echo esc_html($card['group_label'] ?? 'Grupo'); ?></span>
    <?php if (!empty($card['transmission'])): ?>
      <span class="bvgn-hml-card__badge bvgn-hml-card__badge--ghost"><?php echo esc_html($card['transmission']); ?></span>
    <?php endif; ?>
  </div>

  <div
    class="bvgn-hml-card__carousel<?php echo $has_gallery ? ' is-carousel' : ''; ?>"
    data-bvgn-carousel
    data-interval="4800"
    data-card-index="<?php echo esc_attr(intval($card['index'] ?? 0)); ?>"
  >
    <div class="bvgn-hml-card__slides">
      <?php foreach ($images as $image_index => $image): ?>
        <figure class="bvgn-hml-card__slide<?php echo $image_index === 0 ? ' is-active' : ''; ?>" data-bvgn-slide>
          <img
            src="<?php echo esc_url($image['url']); ?>"
            alt="<?php echo esc_attr($image['alt']); ?>"
            class="bvgn-hml-card__image"
            loading="<?php echo esc_attr($image['loading']); ?>"
            decoding="async"
          >
        </figure>
      <?php endforeach; ?>
    </div>

    <?php if ($has_gallery): ?>
      <div class="bvgn-hml-card__dots" aria-label="Galeria do veículo">
        <?php foreach ($images as $image_index => $image): ?>
          <button
            type="button"
            class="bvgn-hml-card__dot<?php echo $image_index === 0 ? ' is-active' : ''; ?>"
            data-bvgn-dot="<?php echo esc_attr($image_index); ?>"
            aria-label="<?php echo esc_attr(sprintf('Exibir imagem %d', $image_index + 1)); ?>"
          ></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bvgn-hml-card__body">
    <?php if ($card_title !== ''): ?>
      <h3 class="bvgn-hml-card__title"><?php echo esc_html($card_title); ?></h3>
      <?php if (!empty($card['complement'])): ?>
        <p class="bvgn-hml-card__subtitle"><?php echo esc_html($card['complement']); ?></p>
      <?php endif; ?>
    <?php else: ?>
      <h3 class="bvgn-hml-card__title"><?php echo esc_html($fallback_title); ?></h3>
    <?php endif; ?>

    <a class="bvgn-hml-card__button" href="<?php echo esc_url($card['permalink']); ?>">
      Selecionar datas
    </a>
  </div>
</article>
