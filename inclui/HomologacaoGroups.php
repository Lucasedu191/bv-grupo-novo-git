<?php
if (!defined('ABSPATH')) exit;

class BVGN_HomologacaoGroups {
  const SHORTCODE = 'bvgn_grupos_homologacao';
  const SHORTCODE_ALIAS = 'grupo_novo_hml';

  public static function init() {
    add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
    add_shortcode(self::SHORTCODE_ALIAS, [__CLASS__, 'render_shortcode']);
  }

  public static function render_shortcode($atts = []) {
    if (!class_exists('WooCommerce') || !function_exists('wc_get_products')) {
      return '';
    }

    $atts = shortcode_atts([
      'title' => 'Explore os grupos de carros por Diária',
      'category' => 'aluguel-de-carros-diaria',
      'limit' => -1,
      'note' => 'Imagens ilustrativas. O modelo disponibilizado poderá variar dentro da categoria.',
      'empty_message' => 'Nenhum grupo diário disponível no momento.',
    ], $atts, self::SHORTCODE);

    $products = self::get_daily_products($atts);
    $cards = [];
    foreach ($products as $index => $product) {
      $card = self::build_card_data($product, $index);
      if ($card) $cards[] = $card;
    }

    $view_data = [
      'section_title' => sanitize_text_field($atts['title']),
      'cards' => $cards,
      'note' => sanitize_text_field($atts['note']),
      'empty_message' => sanitize_text_field($atts['empty_message']),
    ];

    ob_start();
    include BVGN_CAMINHO . 'modelos/partes/grupos-homologacao.php';
    return ob_get_clean();
  }

  private static function get_daily_products($atts) {
    $limit = intval($atts['limit']);
    if ($limit === 0) $limit = -1;

    $products = wc_get_products([
      'status' => 'publish',
      'limit' => $limit,
      'category' => [sanitize_title($atts['category'])],
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'return' => 'objects',
    ]);

    if (!is_array($products)) return [];

    usort($products, function($a, $b) {
      $group_a = self::get_group_letter($a);
      $group_b = self::get_group_letter($b);
      if ($group_a !== $group_b) {
        return strcmp($group_a, $group_b);
      }
      return strcasecmp($a->get_name(), $b->get_name());
    });

    return $products;
  }

  private static function build_card_data($product, $index) {
    if (!$product || !is_object($product)) return null;

    $parsed = self::parse_product_content($product);
    $images = self::get_product_images($product);
    $permalink = get_permalink($product->get_id());

    return [
      'id' => $product->get_id(),
      'index' => intval($index),
      'group_letter' => self::get_group_letter($product),
      'group_label' => $parsed['group_label'],
      'transmission' => $parsed['transmission'],
      'models' => $parsed['models'],
      'complement' => $parsed['complement'],
      'fallback_title' => $parsed['fallback_title'],
      'images' => $images,
      'permalink' => $permalink ? esc_url($permalink) : '#',
      'price_rules' => self::get_daily_price_rules($product),
    ];
  }

  private static function parse_product_content($product) {
    $title = trim(wp_strip_all_tags($product->get_name()));
    $group_letter = self::get_group_letter($product);
    $group_label = $group_letter !== '' ? 'Grupo ' . $group_letter : '';

    $transmission = self::resolve_transmission($product, $title);

    $working = $title;
    $working = preg_replace('/^Grupo\s+[A-Z]\s*[-–]\s*/iu', '', $working);
    $working = preg_replace('/\((Manual|Autom[aá]tico)\)\s*$/iu', '', $working);
    $working = trim(preg_replace('/\s+/', ' ', $working));

    $models = '';
    $complement = '';
    if (preg_match('/^(.*?)(\s+ou\s+similares?)$/iu', $working, $matches)) {
      $models = trim($matches[1], " \t\n\r\0\x0B,");
      $complement = 'ou similares';
    } else {
      $models = $working;
    }

    $fallback_title = $title;
    if ($models === '') {
      $fallback_title = $title;
    }

    return [
      'group_label' => $group_label !== '' ? $group_label : $title,
      'transmission' => $transmission,
      'models' => $models,
      'complement' => $complement,
      'fallback_title' => $fallback_title,
    ];
  }

  private static function resolve_transmission($product, $title) {
    $attribute_transmission = self::find_transmission_in_attributes($product);
    if ($attribute_transmission !== '') {
      return $attribute_transmission;
    }

    if (preg_match('/\((Manual|Autom[aá]tico)\)/iu', $title, $matches)) {
      return self::normalize_transmission_label($matches[1]);
    }

    return '';
  }

  private static function find_transmission_in_attributes($product) {
    $attributes = $product->get_attributes();
    if (!is_array($attributes)) return '';

    foreach ($attributes as $attribute_key => $attribute) {
      $label = wc_attribute_label($attribute_key);
      $label = strtolower(remove_accents(is_string($label) ? $label : ''));

      if (
        strpos($label, 'cambio') === false &&
        strpos($label, 'transmiss') === false
      ) {
        continue;
      }

      $value = '';
      if (is_object($attribute) && method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy()) {
        $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
        $value = is_array($terms) ? implode(', ', $terms) : '';
      } elseif (is_object($attribute) && method_exists($attribute, 'get_options')) {
        $value = implode(', ', array_map('sanitize_text_field', $attribute->get_options()));
      } elseif (is_string($attribute)) {
        $value = $attribute;
      }

      if (preg_match('/manual/iu', $value)) return 'Manual';
      if (preg_match('/autom[aá]tico/iu', $value)) return 'Automático';
    }

    return '';
  }

  private static function normalize_transmission_label($value) {
    $value = sanitize_text_field($value);
    if (preg_match('/manual/iu', $value)) return 'Manual';
    if (preg_match('/autom[aá]tico/iu', $value)) return 'Automático';
    return $value;
  }

  private static function get_group_letter($product) {
    $product_id = $product->get_id();
    $meta_group = get_post_meta($product_id, '_bvgn_grupo', true);
    $meta_group = strtoupper(sanitize_text_field($meta_group));
    if (preg_match('/^[A-Z]$/', $meta_group)) {
      return $meta_group;
    }

    $title = $product->get_name();
    if (preg_match('/Grupo\s+([A-Z])/i', $title, $matches)) {
      return strtoupper($matches[1]);
    }

    return '';
  }

  private static function get_product_images($product) {
    $image_ids = [];

    $main_image_id = absint($product->get_image_id());
    if ($main_image_id > 0) {
      $image_ids[] = $main_image_id;
    }

    $gallery_ids = method_exists($product, 'get_gallery_image_ids') ? $product->get_gallery_image_ids() : [];
    if (is_array($gallery_ids)) {
      foreach ($gallery_ids as $gallery_id) {
        $gallery_id = absint($gallery_id);
        if ($gallery_id > 0) $image_ids[] = $gallery_id;
      }
    }

    $image_ids = array_values(array_unique($image_ids));
    $images = [];
    foreach ($image_ids as $position => $image_id) {
      $url = wp_get_attachment_image_url($image_id, 'large');
      if (!$url) continue;

      $images[] = [
        'id' => $image_id,
        'url' => esc_url($url),
        'alt' => sanitize_text_field(get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $product->get_name()),
        'loading' => $position === 0 ? 'eager' : 'lazy',
      ];
    }

    return $images;
  }

  private static function get_daily_price_rules($product) {
    if (!$product || !$product->is_type('variable')) {
      return [];
    }

    $rules = [];
    $available = $product->get_available_variations();
    foreach ($available as $raw) {
      $variation_id = isset($raw['variation_id']) ? absint($raw['variation_id']) : 0;
      if (!$variation_id) continue;

      $variation = wc_get_product($variation_id);
      if (!$variation) continue;

      $attrs = isset($raw['attributes']) && is_array($raw['attributes']) ? $raw['attributes'] : [];
      $label = wc_get_formatted_variation($attrs, true, false, false);
      $label = wp_strip_all_tags($label);
      $min_max = self::min_max_by_label($label);
      $price = (float) $variation->get_price();
      if ($price <= 0) continue;

      $rules[] = [
        'id' => $variation_id,
        'label' => sanitize_text_field($label),
        'price' => $price,
        'min_days' => (int) $min_max[0],
        'max_days' => (int) $min_max[1],
      ];
    }

    usort($rules, function($a, $b) {
      if ((int) $a['min_days'] !== (int) $b['min_days']) {
        return (int) $a['min_days'] <=> (int) $b['min_days'];
      }
      return (int) $a['max_days'] <=> (int) $b['max_days'];
    });

    return array_values($rules);
  }

  private static function min_max_by_label($label) {
    $label = (string) $label;

    if (preg_match('~(\d{1,2})[^\d]+(\d{1,2})~', $label, $matches)) {
      return [intval($matches[1]), intval($matches[2])];
    }

    if (preg_match('~(\d{1,2})\s*dias?~i', $label, $matches)) {
      $days = max(1, intval($matches[1]));
      if ($days === 1) return [1, 2];
      return [$days, $days];
    }

    return [1, 30];
  }
}

BVGN_HomologacaoGroups::init();
