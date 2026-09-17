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
function has_term($term, $taxonomy, $id) { return $term === 'aluguel-de-carros-diaria'; }
function get_posts($args) { return [11]; }
function get_post_meta($id, $key, $single) { return $key === '_price' ? 100 : 'H'; }
function get_the_title($id) { return 'Grupo H'; }
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
  public function prepare($sql, ...$args) {
    $args = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
    check(preg_match_all('/%[dfs]/', $sql) === count($args), 'SQL placeholders match arguments.');
    return ['sql'=>$sql, 'args'=>$args];
  }
  public function query($prepared) { $this->writes[] = $prepared; return 1; }
  public function get_var($sql) { $this->reads++; return count($this->rows); }
  public function get_results($sql, $format) { $this->reads++; return $this->rows; }
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
$wpdb->rows = [];
ob_start(); BVGN_TariffHistoryAdmin::render(); $html = ob_get_clean();
check(strpos($html, 'colspan="9"') !== false, 'Empty report spans all nine columns.');
echo $checks . " fee checks passed.\n";
