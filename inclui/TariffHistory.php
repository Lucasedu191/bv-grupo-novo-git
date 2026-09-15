<?php
if (!defined('ABSPATH')) exit;

/** Passive observer: never writes to products, prices, or quotation data. */
class BVGN_TariffHistory {
  private static $pending = [];

  public static function init() {
    add_action('admin_init', ['BVGN_TariffHistoryRepository', 'install']);
    add_action('woocommerce_before_product_object_save', [__CLASS__, 'before_save']);
    add_action('woocommerce_before_product_variation_object_save', [__CLASS__, 'before_save']);
    // Covers integrations and scheduled sale changes using the metadata API.
    foreach (['add_post_metadata', 'update_post_metadata', 'delete_post_metadata'] as $hook) {
      add_filter($hook, [__CLASS__, 'before_meta'], 10, 5);
    }
    add_action('shutdown', [__CLASS__, 'flush'], 100);
  }

  public static function before_save($product) {
    $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
    self::watch($id);
  }

  public static function before_meta($check, $object_id, $key, $value, $extra) {
    if ($check !== null) return $check;
    if (!in_array($key, ['_price', '_regular_price', '_sale_price'], true) && strpos($key, 'attribute_') !== 0) return $check;
    if (get_post_type($object_id) === 'product_variation') {
      self::watch(wp_get_post_parent_id($object_id));
    }
    return $check;
  }

  private static function watch($id) {
    $id = absint($id);
    if (!$id || array_key_exists($id, self::$pending) || !function_exists('wc_get_product')) return;
    $before = self::snapshot($id);
    if ($before === null) return;
    self::$pending[$id] = ['before' => $before, 'user' => get_current_user_id()];
  }

  public static function flush() {
    $pending = self::$pending;
    self::$pending = [];
    foreach ($pending as $id => $entry) {
      BVGN_TariffHistoryRepository::record($id, $entry['before'], $entry['user']);
    }
  }

  public static function snapshot($id) {
    if (!function_exists('wc_get_product') || get_post_type($id) !== 'product'
      || !has_term('aluguel-de-carros-diaria', 'product_cat', $id)
      || has_term('aluguel-de-carros-mensal', 'product_cat', $id)) return null;
    // Query the saved variations directly; parent transients may still be stale during a save.
    $ids = get_posts([
      'post_type' => 'product_variation', 'post_parent' => $id, 'post_status' => 'publish',
      'numberposts' => -1, 'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'fields' => 'ids',
    ]);
    $rules = [];
    foreach ($ids as $variation_id) {
      $price = get_post_meta($variation_id, '_price', true);
      if ($price === '' || !is_numeric($price)) continue;
      $variation = new WC_Product_Variation($variation_id);
      $label = wc_get_formatted_variation($variation->get_variation_attributes(), true, false, false);
      $range = self::range($label);
      $rules[] = ['min' => $range[0], 'max' => $range[1], 'price' => (float) $price];
    }
    return self::totals($rules);
  }

  // Mirrors modelos/partes/variacoes.php without loading a public template in admin.
  public static function range($label) {
    if (preg_match('~(\d{1,2})[^\d]+(\d{1,2})~', $label, $m)) return [(int) $m[1], (int) $m[2]];
    if (preg_match('~(\d{1,2})\s*dias?~i', $label, $m)) {
      $n = max(1, (int) $m[1]);
      return $n === 1 ? [1, 2] : [$n, $n];
    }
    return [1, 30];
  }

  public static function totals($rules) {
    $values = [];
    foreach ([1, 3, 7, 15] as $index => $days) {
      $selected = null;
      $fallback = null;
      foreach ($rules as $rule) {
        if ($days >= $rule['min'] && $days <= $rule['max']) {
          $selected = $rule;
          break;
        }
        if ($fallback === null || $rule['max'] > $fallback['max']) $fallback = $rule;
      }
      $selected = $selected ?? $fallback;
      $values[BVGN_TariffHistoryRepository::FIELDS[$index]] = $selected === null
        ? null : number_format($selected['price'] * $days, 2, '.', '');
    }
    return $values;
  }
}

BVGN_TariffHistory::init();
