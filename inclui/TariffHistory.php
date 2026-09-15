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
    add_action('update_option_bvgn_dynamic_tariffs', [__CLASS__, 'dynamic_updated'], 10, 3);
    add_action('add_option_bvgn_dynamic_tariffs', [__CLASS__, 'dynamic_added'], 10, 2);
    add_action('delete_option', [__CLASS__, 'before_delete_option']);
    add_action('deleted_option', [__CLASS__, 'after_delete_option']);
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
    self::$pending[$id] = ['before' => $before, 'base' => self::base_rules($id), 'user' => get_current_user_id()];
  }

  public static function flush() {
    $pending = self::$pending;
    self::$pending = [];
    foreach ($pending as $id => $entry) {
      BVGN_TariffHistoryRepository::record($id, $entry['before'], $entry['user']);
      $base = self::base_rules($id);
      if ($base !== null && $base !== $entry['base'] && class_exists('BVGN_DynamicTariffs')) {
        $rules = get_option(BVGN_DynamicTariffs::OPTION_KEY, []);
        self::dynamic_events($rules, $rules, [$id], $entry['base'], $entry['user']);
      }
    }
  }

  public static function snapshot($id) {
    $rules = self::base_rules($id);
    return $rules === null ? null : self::totals($rules);
  }

  public static function base_rules($id) {
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
    return $rules;
  }

  private static $deleted_rules = null;

  public static function dynamic_updated($old, $new, $option = '') {
    self::dynamic_events($old, $new);
  }

  public static function dynamic_added($option, $new) {
    self::dynamic_events([], $new);
  }

  public static function before_delete_option($option) {
    if ($option === 'bvgn_dynamic_tariffs') self::$deleted_rules = get_option($option, []);
  }

  public static function after_delete_option($option) {
    if ($option !== 'bvgn_dynamic_tariffs' || self::$deleted_rules === null) return;
    $old = self::$deleted_rules;
    self::$deleted_rules = null;
    self::dynamic_events($old, []);
  }

  public static function rule_map($rules) {
    $map = [];
    foreach ((array) $rules as $rule) {
      if (!is_array($rule) || empty($rule['id'])) continue;
      // Compare the existing canonical representation without generating IDs or reading options.
      $canonical = BVGN_DynamicTariffs::rules_for_js([$rule])[0];
      // Preserve stored state so automatic expiry is also an auditable change.
      $canonical['active'] = !empty($rule['active']);
      sort($canonical['groups']);
      $map[(int) $rule['id']] = $canonical;
    }
    ksort($map);
    return $map;
  }

  public static function dynamic_events($old, $new, $product_ids = null, $old_base = null, $user_id = null) {
    if (!class_exists('BVGN_DynamicTariffs') || !function_exists('wc_get_product')) return;
    $old = is_array($old) ? $old : [];
    $new = is_array($new) ? $new : [];
    $before = self::rule_map($old);
    $after = self::rule_map($new);
    $changed = [];
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $id) {
      if ($old_base !== null || ($before[$id] ?? null) !== ($after[$id] ?? null)) $changed[] = $id;
    }
    if (!$changed) return;
    if ($product_ids === null) {
      $product_ids = get_posts([
        'post_type' => 'product', 'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
        'numberposts' => -1, 'fields' => 'ids',
        'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'aluguel-de-carros-diaria']],
      ]);
    }
    // Freeze the full ordered ruleset, including competitors. Display uses the public JS engine.
    $old_js = BVGN_DynamicTariffs::rules_for_js(BVGN_DynamicTariffs::sort_rules($old));
    $new_js = BVGN_DynamicTariffs::rules_for_js(BVGN_DynamicTariffs::sort_rules($new));
    $user_id = $user_id === null ? get_current_user_id() : $user_id;
    foreach ($product_ids as $product_id) {
      $base = self::base_rules($product_id);
      if ($base === null) continue;
      $group = strtoupper((string) get_post_meta($product_id, '_bvgn_grupo', true));
      if ($group === '' && preg_match('/Grupo\s+([A-Z])/i', get_the_title($product_id), $m)) $group = strtoupper($m[1]);
      foreach ($changed as $id) {
        $prior = $before[$id] ?? null;
        $next = $after[$id] ?? null;
        if (!in_array($group, $prior['groups'] ?? [], true) && !in_array($group, $next['groups'] ?? [], true)) continue;
        $event = !$prior ? 'criada' : (!$next ? 'excluida' : (
          $prior['active'] && !$next['active'] ? 'desativada' : (
            !$prior['active'] && $next['active'] ? 'ativada' : ($old_base !== null ? 'base_alterada' : 'alterada')
          )
        ));
        $context = [
          'version' => 1, 'group' => $group, 'beforeRule' => $prior, 'afterRule' => $next,
          'before' => ['base' => $old_base ?? $base, 'rules' => $old_js],
          'after' => ['base' => $base, 'rules' => $new_js],
          'recordedDate' => current_time('Y-m-d'),
        ];
        BVGN_TariffHistoryRepository::record_dynamic($product_id, $id, $event, $context, $user_id);
      }
    }
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
