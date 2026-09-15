<?php
// Standalone regression tests: php tests/tariff-history-test.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$hooks = [];
$prices = [101 => '149', 102 => '140', 103 => '130', 104 => '116.666666'];
$labels = [101 => '1 Dia', 102 => '03 a 06 Dias', 103 => '07 a 14 Dias', 104 => '15 a 30 Dias'];
$monthly = false;
$options = ['bvgn_tariff_history_db_version' => '2', 'bvgn_dynamic_tariffs' => []];
function is_admin() { return false; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_json_encode($value) { return json_encode($value); }
function get_the_title($id) { return 'Grupo A'; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { add_action($hook, $callback); }
function absint($n) { return abs((int) $n); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) {
  $old = get_option($key);
  if ($old === $value) return false;
  $GLOBALS['options'][$key] = $value;
  if (isset($GLOBALS['hooks']['update_option_' . $key])) call_user_func($GLOBALS['hooks']['update_option_' . $key], $old, $value, $key);
  return true;
}
function current_time($format, $gmt = false) { return $format === 'Y-m-d' ? '2026-09-14' : '2026-09-14 14:35:00'; }
function get_current_user_id() { return 7; }
function get_post_type($id) { return $id === 10 ? 'product' : 'product_variation'; }
function wp_get_post_parent_id($id) { return 10; }
function has_term($term, $taxonomy, $id) { return $term === 'aluguel-de-carros-diaria' || $GLOBALS['monthly']; }
function get_posts($args) { return $args['post_type'] === 'product' ? [10] : array_keys($GLOBALS['prices']); }
function get_post_meta($id, $key, $single) { return $key === '_bvgn_grupo' ? 'A' : $GLOBALS['prices'][$id]; }
function wc_get_product($id) { return new WC_Product_Variation($id); }
function wc_get_formatted_variation($attrs, $flat, $names, $skip) { return $attrs['label']; }
function wp_timezone() { return new DateTimeZone('America/Sao_Paulo'); }
class WC_Product_Variation {
  private $id;
  public function __construct($id) { $this->id = $id; }
  public function get_variation_attributes() { return ['label' => $GLOBALS['labels'][$this->id]]; }
  public function is_type($type) { return $type === 'variation'; }
  public function get_parent_id() { return 10; }
}
class HistoryTestDB {
  public $prefix = 'custom_';
  public $last_error = '';
  public $rows = [];
  public $released = 0;
  public $acquired = 0;
  public function prepare($sql, ...$args) { return [$sql, count($args) === 1 && is_array($args[0]) ? $args[0] : $args]; }
  public function get_var($query) {
    if (strpos($query[0], 'GET_LOCK') !== false) $this->acquired++;
    if (strpos($query[0], 'RELEASE_LOCK') !== false) $this->released++;
    return 1;
  }
  public function get_row($query, $format) {
    foreach (array_reverse($this->rows) as $row) {
      if (($row['tipo'] ?? 'geral') !== 'geral' && strpos($query[0], "tipo = 'geral'") !== false) continue;
      if ($row['grupo_id'] === $query[1][0] && (!isset($query[1][1]) || $row['id'] < $query[1][1])) return $row;
    }
    return null;
  }
  public function insert($table, $data, $formats) {
    $data['id'] = count($this->rows) + 1;
    $this->rows[] = $data;
    return 1;
  }
}
$wpdb = new HistoryTestDB();
require __DIR__ . '/../inclui/DynamicTariffs.php';
require __DIR__ . '/../inclui/TariffHistoryRepository.php';
require __DIR__ . '/../inclui/TariffHistory.php';
require __DIR__ . '/../inclui/TariffHistoryAdmin.php';
$checks = 0;
function check($condition, $message) {
  $GLOBALS['checks']++;
  if (!$condition) throw new RuntimeException($message);
}
function save_price($id, $value) {
  BVGN_TariffHistory::before_meta(null, $id, '_price', $value, '');
  $GLOBALS['prices'][$id] = $value;
}
$initial = BVGN_TariffHistory::snapshot(10);
check(array_values($initial) === ['149.00', '420.00', '910.00', '1750.00'], 'Totals must multiply each matching daily tier.');
check(BVGN_TariffHistoryRepository::table() === 'custom_bv_historico_tarifas', 'Respect database prefix.');
check(BVGN_TariffHistory::range('1 Dia') === [1, 2], 'Single day tier.');
check(BVGN_TariffHistory::range('07–14 Dias') === [7, 14], 'Range labels.');
check(BVGN_TariffHistory::range('Sem faixa') === [1, 30], 'Existing label fallback.');
BVGN_TariffHistory::before_save(new WC_Product_Variation(101));
BVGN_TariffHistory::flush();
check(count($wpdb->rows) === 0, 'First unchanged save must not seed history.');
save_price(101, '159');
save_price(102, '150');
BVGN_TariffHistory::flush();
check(count($wpdb->rows) === 1, 'Batch changes must create one snapshot.');
check($wpdb->rows[0]['valor_1_dia'] === '159.00' && $wpdb->rows[0]['valor_3_dias'] === '450.00', 'Snapshot must contain final values.');
check($wpdb->rows[0]['usuario_id'] === 7, 'Capture responsible user.');
save_price(101, '159.0000');
BVGN_TariffHistory::flush();
check(count($wpdb->rows) === 1, 'Equivalent numeric values must not duplicate.');
save_price(101, '149');
save_price(101, '159');
BVGN_TariffHistory::flush();
check(count($wpdb->rows) === 1, 'Reverted changes in one save must not duplicate.');
BVGN_TariffHistoryRepository::record(10, $initial, 7);
check(count($wpdb->rows) === 1, 'Latest-history comparison must suppress repeated snapshot.');
save_price(101, '149');
BVGN_TariffHistory::flush();
check(count($wpdb->rows) === 2, 'Returning to an earlier tariff is a new change.');
check(BVGN_TariffHistoryRepository::latest(10, 2)['id'] === 1, 'Previous record independent of report filters.');
$monthly = true;
check(BVGN_TariffHistory::snapshot(10) === null, 'Monthly products excluded.');
$monthly = false;
check(BVGN_TariffHistory::before_meta(true, 101, '_price', '999', '') === true, 'Respect metadata short circuit.');
check(BVGN_TariffHistoryAdmin::date('2026-02-30') === null, 'Reject invalid dates.');
check(BVGN_TariffHistoryAdmin::date('2026-09-14')->setTimezone(new DateTimeZone('UTC'))->format('H:i') === '03:00', 'Convert local filter boundary to UTC.');
check(BVGN_TariffHistoryAdmin::date('2026-09-14')->modify('+1 day')->format('Y-m-d') === '2026-09-15', 'End date includes entire local day.');
$fallback = BVGN_TariffHistory::totals([['min' => 1, 'max' => 2, 'price' => 100], ['min' => 7, 'max' => 14, 'price' => 80]]);
check($fallback['valor_3_dias'] === '240.00' && $fallback['valor_15_dias'] === '1200.00', 'Mirror largest-range fallback.');
check(BVGN_TariffHistory::totals([])['valor_1_dia'] === null, 'Missing tariff is not zero.');
check($wpdb->acquired > 0 && $wpdb->released === $wpdb->acquired, 'Release locks even for no-op saves.');
$rule = ['id' => 1, 'type' => 'date_range', 'percent' => 20, 'label' => 'Alta temporada', 'desc' => '',
  'priority' => 10, 'weekday' => 0, 'start_date' => '2026-09-15', 'end_date' => '2026-09-20',
  'groups' => ['A'], 'active' => true, 'show_resumo' => true, 'show_pdf' => true];
function change_rules($rules) {
  $old = $GLOBALS['options']['bvgn_dynamic_tariffs'];
  $GLOBALS['options']['bvgn_dynamic_tariffs'] = $rules;
  BVGN_TariffHistory::dynamic_updated($old, $rules);
}
function last_event() { return end($GLOBALS['wpdb']->rows); }
change_rules([$rule]);
check(count($wpdb->rows) === 3 && last_event()['evento'] === 'criada', 'Create dynamic event.');
$created = json_decode(last_event()['contexto'], true);
check($created['beforeRule'] === null && $created['afterRule']['percent'] == 20, 'Freeze new rule and previous absence.');
$rule['percent'] = 30;
change_rules([$rule]);
check(last_event()['evento'] === 'alterada', 'Price update event.');
$price_change = json_decode(last_event()['contexto'], true);
check($price_change['beforeRule']['percent'] == 20 && $price_change['afterRule']['percent'] == 30, 'Preserve previous and new percent.');
$rule['start_date'] = '2026-09-17';
$rule['end_date'] = '2026-09-25';
change_rules([$rule]);
$period_change = json_decode(last_event()['contexto'], true);
check($period_change['beforeRule']['startDate'] === '2026-09-15' && $period_change['afterRule']['startDate'] === '2026-09-17', 'Preserve both validity periods.');
$count = count($wpdb->rows);
change_rules([$rule]);
check(count($wpdb->rows) === $count, 'No duplicate for unchanged dynamic save.');
$rule['percent'] = '30.00';
change_rules([$rule]);
check(count($wpdb->rows) === $count, 'Normalize numeric dynamic values.');
$rule['active'] = false;
change_rules([$rule]);
check(last_event()['evento'] === 'desativada', 'Disable event.');
$disabled = json_decode(last_event()['contexto'], true);
$rule['active'] = true;
change_rules([$rule]);
check(last_event()['evento'] === 'ativada', 'Reactivate event.');
$competitor = $rule;
$competitor['id'] = 2;
$competitor['priority'] = 20;
$competitor['percent'] = 50;
$competitor['start_date'] = '2026-09-18';
$competitor['end_date'] = '2026-09-19';
change_rules([$rule, $competitor]);
$overlap = json_decode(last_event()['contexto'], true);
check(count($overlap['after']['rules']) === 2 && $overlap['after']['rules'][0]['id'] === 2, 'Freeze sorted competing rules.');
$count = count($wpdb->rows);
change_rules([$competitor, $rule]);
check(count($wpdb->rows) === $count, 'Reordering unchanged rules does not create events.');
save_price(101, '160.50');
BVGN_TariffHistory::flush();
check(last_event()['evento'] === 'base_alterada', 'General change also updates dynamic context.');
$base_change = json_decode(last_event()['contexto'], true);
check($base_change['before']['base'][0]['price'] == 149 && $base_change['after']['base'][0]['price'] == 160.5, 'Keep original unit precision on general change.');
check((BVGN_TariffHistoryRepository::latest(10)['tipo'] ?? 'geral') === 'geral', 'Dynamic rows must never become previous general snapshot.');
change_rules([$competitor]);
check(last_event()['evento'] === 'excluida' && last_event()['regra_id'] === 1, 'Rule removal recorded.');
$deleted = json_decode(last_event()['contexto'], true);
check(count($deleted['after']['rules']) === 1 && $deleted['afterRule'] === null, 'Deletion keeps competing rules.');
BVGN_TariffHistory::before_delete_option('bvgn_dynamic_tariffs');
$options['bvgn_dynamic_tariffs'] = [];
BVGN_TariffHistory::after_delete_option('bvgn_dynamic_tariffs');
check(last_event()['evento'] === 'excluida' && last_event()['regra_id'] === 2, 'Deleting entire option recorded.');
check(BVGN_TariffHistoryRepository::latest(10, 2)['valor_1_dia'] === '159.00', 'Legacy general values still readable.');
$rule['type'] = 'week_day';
$rule['weekday'] = 0;
$rule['end_date'] = '';
change_rules([$rule]);
$weekly = json_decode(last_event()['contexto'], true);
check($weekly['afterRule']['type'] === 'week_day', 'Weekly recurrence preserved.');
check(!empty($hooks['update_option_bvgn_dynamic_tariffs']) && !empty($hooks['deleted_option']), 'Register successful persistence observers.');
$rule['type'] = 'date_range';
$rule['end_date'] = '2026-09-13';
$options['bvgn_dynamic_tariffs'] = [$rule];
BVGN_DynamicTariffs::get_rules();
check(last_event()['evento'] === 'desativada', 'Automatic expiry in real get_rules path is audited.');
$count = count($wpdb->rows);
BVGN_DynamicTariffs::get_rules();
check(count($wpdb->rows) === $count, 'Reading already expired rules does not duplicate history.');
check(BVGN_DynamicTariffs::for_js() === BVGN_DynamicTariffs::rules_for_js(BVGN_DynamicTariffs::get_rules()), 'Public serialization still delegates to identical mapping.');
$rule['end_date'] = '2026-09-25';
$rule['id'] = 3;
BVGN_TariffHistory::dynamic_added('bvgn_dynamic_tariffs', [$rule]);
check(last_event()['evento'] === 'criada' && last_event()['regra_id'] === 3, 'First option creation observed.');
$moved = $rule;
$moved['groups'] = ['B'];
BVGN_TariffHistory::dynamic_updated([$rule], [$moved]);
check(last_event()['evento'] === 'alterada' && json_decode(last_event()['contexto'], true)['afterRule']['groups'] === ['B'], 'Removing group A still records its change.');
$fixtures = compact('created', 'price_change', 'period_change', 'disabled', 'overlap', 'base_change', 'deleted', 'weekly');
if (in_array('--fixtures', $argv, true)) echo json_encode(['checks' => $checks, 'fixtures' => $fixtures]);
else echo $checks . " checks passed.\n";
