<?php
if (!defined('ABSPATH')) exit;

$images = isset($card['images']) && is_array($card['images']) ? $card['images'] : [];
$has_gallery = count($images) > 1;
$card_title = trim((string) ($card['models'] ?? ''));
$fallback_title = trim((string) ($card['fallback_title'] ?? ''));
$price_rules_json = !empty($card['price_rules']) ? wp_json_encode($card['price_rules']) : '[]';
$plan_type = !empty($card['plan_type']) ? sanitize_key($card['plan_type']) : 'diario';
$static_price = isset($card['static_price']) ? (float) $card['static_price'] : 0;
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
    data-interval="5600"
    data-card-index="<?php echo esc_attr(intval($card['index'] ?? 0)); ?>"
    data-group-letter="<?php echo esc_attr($card['group_letter'] ?? ''); ?>"
    data-plan-type="<?php echo esc_attr($plan_type); ?>"
    data-static-price="<?php echo esc_attr($static_price); ?>"
    data-price-rules="<?php echo esc_attr($price_rules_json); ?>"
  >
    <a class="bvgn-hml-card__image-link" href="<?php echo esc_url($card['permalink']); ?>" aria-label="<?php echo esc_attr($fallback_title !== '' ? $fallback_title : ($card_title !== '' ? $card_title : 'Abrir produto')); ?>">
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
    </a>

    <div class="bvgn-hml-card__dots<?php echo $has_gallery ? '' : ' is-placeholder'; ?>" aria-label="Galeria do veículo">
      <?php if ($has_gallery): ?>
        <?php foreach ($images as $image_index => $image): ?>
          <button
            type="button"
            class="bvgn-hml-card__dot<?php echo $image_index === 0 ? ' is-active' : ''; ?>"
            data-bvgn-dot="<?php echo esc_attr($image_index); ?>"
            aria-label="<?php echo esc_attr(sprintf('Exibir imagem %d', $image_index + 1)); ?>"
          ></button>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="bvgn-hml-card__body">
    <?php if ($card_title !== ''): ?>
      <h3 class="bvgn-hml-card__title" title="<?php echo esc_attr($card_title); ?>"><?php echo esc_html($card_title); ?></h3>
      <?php if (!empty($card['complement'])): ?>
        <p class="bvgn-hml-card__subtitle"><?php echo esc_html($card['complement']); ?></p>
      <?php endif; ?>
    <?php else: ?>
      <h3 class="bvgn-hml-card__title" title="<?php echo esc_attr($fallback_title); ?>"><?php echo esc_html($fallback_title); ?></h3>
    <?php endif; ?>

    <div class="bvgn-hml-card__price" data-bvgn-price hidden>
      <span class="bvgn-hml-card__price-value" data-bvgn-price-value></span>
      <small class="bvgn-hml-card__price-note">+ taxas</small>
    </div>

    <a class="bvgn-hml-card__button" href="<?php echo esc_url($card['permalink']); ?>">
      Selecionar datas
    </a>
  </div>
</article>
