<?php
// Contract test for migration SQL and version handling; no live MySQL required.
$temp = sys_get_temp_dir() . '/bvgn-schema-' . uniqid();
mkdir($temp . '/wp-admin/includes', 0777, true);
file_put_contents($temp . '/wp-admin/includes/upgrade.php', '<?php');
define('ABSPATH', $temp . '/');
$version = '1';
$calls = 0;
$ddl = '';
$fail = false;
function get_option($key) { return $GLOBALS['version']; }
function update_option($key, $value, $autoload) { $GLOBALS['version'] = $value; }
function dbDelta($sql) {
  $GLOBALS['ddl'] = $sql;
  $GLOBALS['calls']++;
  if ($GLOBALS['fail']) $GLOBALS['wpdb']->last_error = 'Simulated ALTER permission failure';
}
class SchemaDB {
  public $prefix = 'tenant_';
  public $last_error = '';
  public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
  public function get_col($sql) {
    if ($GLOBALS['fail']) return ['id', 'grupo_id'];
    preg_match_all('/^\s+(\w+) (?:bigint|decimal|date|datetime|varchar|longtext)/m', $GLOBALS['ddl'], $m);
    return $m[1];
  }
}
$wpdb = new SchemaDB();
require __DIR__ . '/../inclui/TariffHistoryRepository.php';
$checks = 0;
function verify($value, $message) {
  $GLOBALS['checks']++;
  if (!$value) throw new RuntimeException($message);
}
try {
  BVGN_TariffHistoryRepository::install();
  verify($version === '7', 'Upgrade version only after required columns exist.');
  verify(strpos($ddl, 'CREATE TABLE tenant_bv_historico_diario_tarifas') !== false, 'Create the separate prefixed daily table.');
  verify(strpos($ddl, 'UNIQUE KEY grupo_data (grupo_id,data_referencia)') !== false, 'One row per group and reference date.');
  verify(!preg_match('/\b(DROP|TRUNCATE|DELETE|REPLACE)\b/i', $ddl), 'No destructive migration commands.');
  verify(strpos($ddl, 'valor_diaria decimal(18,2) NOT NULL') !== false, 'Store the effective 1-day value.');
  foreach (['valor_3_dias', 'valor_7_dias', 'valor_15_dias', 'protecao_basica', 'protecao_premium', 'taxa_lavagem'] as $field) verify(strpos($ddl, "$field decimal(18,2) DEFAULT NULL") !== false, 'Store each requested duration.');
  BVGN_TariffHistoryRepository::install();
  verify($calls === 1, 'Already upgraded installation is a no-op.');
  foreach (['4', '6'] as $previous) {
    $version = $previous;
    BVGN_TariffHistoryRepository::install();
    verify($version === '7', 'Upgrade from current or previously attempted fee schema.');
  }
  $version = '1';
  $fail = true;
  BVGN_TariffHistoryRepository::install();
  verify($version === '1', 'Failed migration remains retryable.');
  $fail = false;
  $wpdb->last_error = '';
  BVGN_TariffHistoryRepository::install();
  verify($version === '7', 'Retry completes upgrade.');
  echo $checks . " schema checks passed.\n";
} finally {
  unlink($temp . '/wp-admin/includes/upgrade.php');
  rmdir($temp . '/wp-admin/includes');
  rmdir($temp . '/wp-admin');
  rmdir($temp);
}
