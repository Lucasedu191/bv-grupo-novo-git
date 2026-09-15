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
<<<<<<< HEAD
  verify($version === '6', 'Upgrade version only after required columns exist.');
=======
  verify($version === '4', 'Upgrade version only after required columns exist.');
>>>>>>> 44824bb56c7f5809fd3d9fd6fc002a086dd114ed
  verify(strpos($ddl, 'CREATE TABLE tenant_bv_historico_diario_tarifas') !== false, 'Create the separate prefixed daily table.');
  verify(strpos($ddl, 'UNIQUE KEY grupo_data (grupo_id,data_referencia)') !== false, 'One row per group and reference date.');
  verify(!preg_match('/\b(DROP|TRUNCATE|DELETE|REPLACE)\b/i', $ddl), 'No destructive migration commands.');
  verify(strpos($ddl, 'valor_diaria decimal(18,2) NOT NULL') !== false, 'Store the effective 1-day value.');
  foreach (['valor_3_dias', 'valor_7_dias', 'valor_15_dias'] as $field) verify(strpos($ddl, "$field decimal(18,2) DEFAULT NULL") !== false, 'Store each requested duration.');
<<<<<<< HEAD
  foreach (['protecao_basica', 'protecao_premium'] as $field) verify(strpos($ddl, "$field decimal(18,2) DEFAULT NULL") !== false, 'Store each protection daily value.');
  verify(strpos($ddl, 'taxa_lavagem decimal(18,2) DEFAULT NULL') !== false, 'Store the one-time washing fee.');
=======
>>>>>>> 44824bb56c7f5809fd3d9fd6fc002a086dd114ed
  BVGN_TariffHistoryRepository::install();
  verify($calls === 1, 'Already upgraded installation is a no-op.');
  $version = '1';
  $fail = true;
  BVGN_TariffHistoryRepository::install();
  verify($version === '1', 'Failed migration remains retryable.');
  $fail = false;
  $wpdb->last_error = '';
  BVGN_TariffHistoryRepository::install();
<<<<<<< HEAD
  verify($version === '6', 'Retry completes upgrade.');
=======
  verify($version === '4', 'Retry completes upgrade.');
>>>>>>> 44824bb56c7f5809fd3d9fd6fc002a086dd114ed
  echo $checks . " schema checks passed.\n";
} finally {
  unlink($temp . '/wp-admin/includes/upgrade.php');
  rmdir($temp . '/wp-admin/includes');
  rmdir($temp . '/wp-admin');
  rmdir($temp);
}
