<?php
// php tests/tariff-history-fees-test.php (no WordPress/database required)
define('ABSPATH', __DIR__ . '/');
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
function add_action() {}
function add_filter() {}
function absint($v) { return abs((int)$v); }
function get_option($key) { return BVGN_TariffHistoryRepository::VERSION; }
function current_time($format, $gmt = false) { return $format === 'Y-m-d' ? '2026-09-16' : '2026-09-16 12:00:00'; }
function get_post_type($id) { return 'product'; }
function has_term($term, $taxonomy, $id) { return $term === (isset($GLOBALS['monthly'][$id]) ? 'aluguel-de-carros-mensal' : 'aluguel-de-carros-diaria'); }
function get_posts($args) {
  if ($args['post_type'] === 'product') {
    check($args['tax_query'][0]['terms'] === ['aluguel-de-carros-diaria','aluguel-de-carros-mensal'], 'Scheduled/manual generation selects both categories.');
    return [1,2,3];
  }
  return [11];
}
function get_post_meta($id, $key, $single) { return $key === '_price' ? 100 : 'H'; }
function get_the_title($id) { return $GLOBALS['monthly'][$id]['title'] ?? 'Grupo H'; }
function wc_get_product($id) {
  if (isset($GLOBALS['monthly'][$id])) return new MonthlyProduct($id);
  return isset($GLOBALS['monthlyPrices'][$id]) ? new MonthlyVariation($id) : false;
}
class MonthlyProduct {
  private $id;
  public function __construct($id) { $this->id = $id; }
  public function is_type($type) { return $type === 'variable'; }
  public function get_available_variations() { return $GLOBALS['monthly'][$this->id]['variations']; }
  public function get_id() { return $this->id; }
}
class MonthlyVariation {
  private $id;
  public function __construct($id) { $this->id = $id; }
  public function get_price() { return $GLOBALS['monthlyPrices'][$this->id]['current']; }
  public function get_regular_price() { return $GLOBALS['monthlyPrices'][$this->id]['regular']; }
}
function wc_get_formatted_variation($attrs, $a, $b, $c) { return '1 a 30 Dias'; }
class WC_Product_Variation {
  public function __construct($id) {}
  public function get_variation_attributes() { return []; }
}
class BVGN_DynamicTariffs {
  public static function rule_for_date($date, $group) { return ['percent'=>20]; }
}
class FeesDB {
  public $prefix = 'tenant_';
  public $writes = [];
  public $reads = 0;
  public $rows = [];
  public $lastSearch;
  public function prepare($sql, ...$args) {
    $args = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
    check(preg_match_all('/%[dfs]/', $sql) === count($args), 'SQL placeholders match arguments.');
    return ['sql'=>$sql, 'args'=>$args];
  }
  public function query($prepared) { $this->writes[] = $prepared; return 1; }
  public function get_var($sql) { $this->reads++; return count($this->rows); }
  public function get_results($sql, $format) { $this->reads++; $this->lastSearch = $sql; return $this->rows; }
  public function get_col($sql) { $this->reads++; return []; }
}
$checks = 0;
function check($ok, $message) { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($message); }
require __DIR__ . '/../inclui/TariffHistoryRepository.php';
require __DIR__ . '/../inclui/TariffHistory.php';
$wpdb = new FeesDB();
check(BVGN_TariffHistory::washing_rate(1) === null, 'Missing integration remains unavailable.');
// Load the actual local source after checking its absence.
require __DIR__ . '/../inclui/IntegracoesPT.php';
check(BVGN_TariffHistory::washing_rate(1) === 48.90, 'Reuse actual local cleaning price.');
foreach (['A','B','C','D','E','F','G','H','I',''] as $group) {
  $expected = $group === 'H' ? [68.90,128.90] : (in_array($group, ['D','E','F','G','I'], true) ? [47.90,86.90] : [36.90,66.90]);
  check(array_values(BVGN_TariffHistory::protection_rates($group)) === $expected, 'Protection prices for group ' . $group);
}
check(BVGN_TariffHistory::record_group(1, '2026-09-16'), 'Snapshot succeeds.');
check(count($wpdb->writes) === 1, 'Only the existing single write per group.');
check(array_slice($wpdb->writes[0]['args'], 2, 7) === [120.0,360.0,840.0,1800.0,68.90,128.90,48.90], 'Dynamic percentage affects rental only; fees are daily/unit amounts.');
$base = ['valor_diaria'=>100, 'valor_3_dias'=>300, 'valor_7_dias'=>700, 'valor_15_dias'=>1500];
foreach ([[], ['protecao_basica'=>null,'protecao_premium'=>'invalid','taxa_lavagem'=>null]] as $fees) {
  BVGN_TariffHistoryRepository::upsert(1, '2026-09-16', $base + $fees);
  $write = end($wpdb->writes);
  check(strpos($write['sql'], '%f,NULL,NULL,NULL,%s)') !== false, 'Missing/invalid fees persist as SQL NULL.');
}
BVGN_TariffHistoryRepository::upsert(1, '2026-09-16', $base + ['protecao_basica'=>0,'protecao_premium'=>0,'taxa_lavagem'=>0]);
$write = end($wpdb->writes);
check(array_slice($write['args'], 6, 3) === [0.0,0.0,0.0], 'Zero fees remain numeric zero.');

// Monthly manual/automatic groups have distinct prices, deliberately shuffled.
function monthlyVariation($id, $km) {
  return ['variation_id'=>$id, 'attributes'=>['attribute_franquia-de-km'=>$km]];
}
$monthly = [
  2=>['title'=>'Grupo B Manual Mensal', 'variations'=>[monthlyVariation(25,'5.000 km'),monthlyVariation(21,'1.000 km'),monthlyVariation(23,'3.000 km')]],
  3=>['title'=>'Grupo F Automático Mensal', 'variations'=>[monthlyVariation(33,'3000 km'),monthlyVariation(35,'5000 KM'),monthlyVariation(31,'1000 km')]],
];
$monthlyPrices = [
  21=>['current'=>'1999.90','regular'=>'2199.90'],23=>['current'=>'2499.50','regular'=>'2499.50'],25=>['current'=>'2999.99','regular'=>'2999.99'],
  31=>['current'=>'2899.90','regular'=>'2899.90'],33=>['current'=>'3399.50','regular'=>'3399.50'],35=>['current'=>'3899.99','regular'=>'3899.99'],
];
$manual = ['valor_diaria'=>null,'valor_3_dias'=>1999.90,'valor_7_dias'=>2499.50,'valor_15_dias'=>2999.99];
$automatic = ['valor_diaria'=>null,'valor_3_dias'=>2899.90,'valor_7_dias'=>3399.50,'valor_15_dias'=>3899.99];
check(BVGN_TariffHistory::rates(2) === $manual, 'Manual monthly prices match mileage, including sale price.');
check(BVGN_TariffHistory::rates(3) === $automatic, 'Automatic monthly prices match mileage regardless of order.');
check(BVGN_TariffHistory::daily_rates(2) === null, 'Monthly variations never use daily range parsing.');
foreach ([2=>$manual,3=>$automatic] as $id=>$expected) {
  check(BVGN_TariffHistory::record_group($id,'2026-09-16'), 'Monthly snapshot succeeds.');
  $write = end($wpdb->writes);
  check(array_slice($write['args'],2,3) === array_slice(array_values($expected),1), 'Monthly totals have no day multiplier or dynamic markup.');
  check(strpos($write['sql'], 'VALUES (%d,%s,NULL,%f,%f,%f,') !== false, 'Daily value is NULL for monthly rows.');
  check(strpos($write['sql'], 'ON DUPLICATE KEY UPDATE') !== false, 'Repeated generation updates the existing group/date.');
}
$monthly[2]['variations'][] = monthlyVariation(29,'10.000 km');
$monthlyPrices[29] = ['current'=>'9999','regular'=>'9999'];
check(BVGN_TariffHistory::rates(2) === $manual, 'Unsupported mileage never populates a known plan.');
$monthlyPrices[33]['current'] = '';
$monthlyPrices[35]['current'] = '0';
$partial = BVGN_TariffHistory::rates(3);
check($partial['valor_7_dias'] === null && $partial['valor_15_dias'] === 0.0, 'Missing prices stay unavailable; zero remains zero.');
BVGN_TariffHistory::record_group(3,'2026-09-16');
$write = end($wpdb->writes);
check(strpos($write['sql'], 'VALUES (%d,%s,NULL,%f,NULL,%f,') !== false, 'Unavailable monthly plans persist NULL, not zero.');
$beforeWrites = count($wpdb->writes);
BVGN_TariffHistory::before_save(new MonthlyProduct(2));
$monthlyPrices[21]['current'] = '2099.90';
BVGN_TariffHistory::flush();
check(count($wpdb->writes) === $beforeWrites + 1, 'Monthly product changes schedule a snapshot on flush.');
$write = end($wpdb->writes);
check($write['args'][2] === 2099.90, 'Flush captures the updated monthly price.');
$beforeWrites = count($wpdb->writes);
BVGN_TariffHistory::record_date('2026-09-16');
check(count($wpdb->writes) === $beforeWrites + 3, 'Daily generation includes daily, manual monthly and automatic monthly groups.');

// Render the real report with legacy, NULL and zero-valued rows.
define('ARRAY_A', 'ARRAY_A');
function current_user_can($cap) { return true; }
function admin_url($path) { return '/wp-admin/' . $path; }
function wp_nonce_field($name) {}
function esc_url($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function esc_attr($v) { return esc_url($v); }
function esc_html($v) { return esc_url($v); }
require __DIR__ . '/../inclui/TariffHistoryAdmin.php';
$row = $base + ['grupo_id'=>1,'data_referencia'=>'2026-09-16'];
$wpdb->rows = [$row, $row + ['protecao_basica'=>null,'protecao_premium'=>null,'taxa_lavagem'=>null], $row + ['protecao_basica'=>0,'protecao_premium'=>0,'taxa_lavagem'=>0]];
$_GET = $_POST = [];
$writesBefore = count($wpdb->writes);
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(strpos($html, 'Proteção Básica / diária') !== false && strpos($html, 'Proteção Premium / diária') !== false && strpos($html, 'Limpeza / única') !== false, 'Fee headers identify units and preserve accents.');
check(substr_count($html, 'Indisponível') === 6, 'Legacy and null fees render without warnings.');
check(substr_count($html, 'R$ 0,00') === 3, 'Free fees render zero.');
check($wpdb->reads === 3, 'Report keeps three repository reads regardless of fee columns.');
check(count($wpdb->writes) === $writesBefore, 'Viewing the report does not generate snapshots.');
check(substr_count($html, '<td>Diária</td>') === 3, 'Daily rows display their category.');
$wpdb->rows = [$manual + ['grupo_id'=>2,'data_referencia'=>'2026-09-16'], $automatic + ['grupo_id'=>3,'data_referencia'=>'2026-09-16']];
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(substr_count($html, '<th>') === 10, 'Report adds only the category column.');
foreach (['3 dias / 1.000 km','7 dias / 3.000 km','15 dias / 5.000 km'] as $header) check(strpos($html,$header)!==false, 'Combined daily/mileage header: '.$header);
foreach (['1.999,90','2.499,50','2.999,99','2.899,90','3.399,50','3.899,99'] as $price) check(strpos($html,'R$ '.$price)!==false, 'Monthly group price rendered: '.$price);
check(strpos($html,'Grupo B Manual Mensal')!==false && strpos($html,'Grupo F Automático Mensal')!==false, 'Both monthly group labels are retained.');
check(substr_count($html, '<td>Mensal</td>') === 2, 'Manual and automatic monthly rows display their category.');
check(strpos($html, '<th>Grupo</th><th>Categoria</th>') !== false, 'Category is next to the group.');
$wpdb->rows = [$base + ['grupo_id'=>999,'data_referencia'=>'2026-09-16']];
$wpdb->rows[0]['valor_diaria'] = 0;
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(strpos($html, '<td>Diária</td>') !== false, 'Zero daily price does not classify as monthly.');
$wpdb->rows[0]['valor_diaria'] = null;
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(strpos($html, '<td>Mensal</td>') !== false, 'Historical monthly category does not depend on current product terms.');
$wpdb->rows = [];
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(strpos($html, 'colspan="10"') !== false, 'Empty report spans all ten columns.');
// Verify grouping happens in SQL before pagination, retaining date/group filters.
$wpdb->rows = array_fill(0, 120, $row);
$result = BVGN_TariffHistoryRepository::search(2, '2026-09-01', '2026-09-17', 2);
check(strpos($wpdb->lastSearch['sql'], 'ORDER BY (valor_diaria IS NULL) ASC,data_referencia DESC,grupo_id ASC LIMIT %d OFFSET %d') !== false, 'Daily and monthly groups stay separated across pages; dates descend within category.');
check(strpos($wpdb->lastSearch['sql'], 'AND grupo_id=%d AND data_referencia >= %s AND data_referencia <= %s') !== false, 'Existing group/date filters remain in SQL.');
check($wpdb->lastSearch['args'] === [2,'2026-09-01','2026-09-17',50,50], 'Second page keeps its limit/offset and bound filter arguments.');
check($result['page'] === 2 && $result['total'] === 120, 'Pagination totals remain unchanged.');

echo $checks . " fee/monthly checks passed.\n";
