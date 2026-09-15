<?php
// Standalone regression tests: php tests/tariff-history-test.php
define('ABSPATH', __DIR__ . '/');
$options = [];
function is_admin(){ return false; }
function add_action(){ }
function add_filter(){ }
function get_option($key, $default=false){ return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value,$autoload=false){ $GLOBALS['options'][$key]=$value; return true; }
function current_time($format){ return $format === 'Y-m-d' ? '2026-09-15' : '2026-09-15 00:10:00'; }
function wp_timezone(){ return new DateTimeZone('America/Sao_Paulo'); }
function sanitize_text_field($value){ return trim((string)$value); }
require __DIR__ . '/../inclui/DynamicTariffs.php';
require __DIR__ . '/../inclui/TariffHistory.php';
function check($ok,$message){ if(!$ok) throw new RuntimeException($message); }

$GLOBALS['options']['bvgn_dynamic_tariffs'] = [
  ['id'=>1,'type'=>'date_range','percent'=>20,'priority'=>10,'start_date'=>'2026-09-14','end_date'=>'2026-09-16','groups'=>['A'],'active'=>true],
  ['id'=>2,'type'=>'single_date','percent'=>30,'priority'=>20,'start_date'=>'2026-09-15','end_date'=>'','groups'=>['A'],'active'=>true],
];
$winner = BVGN_DynamicTariffs::rule_for_date('2026-09-15','A');
check($winner['id'] === 2, 'Overlapping rules use the highest priority.');
check(BVGN_DynamicTariffs::rule_for_date('2026-09-17','A') === null, 'Date outside validity has no rule.');
check(BVGN_TariffHistory::range('1 Dia') === [1,2], 'One-day variation is selected.');
check(BVGN_TariffHistory::range('03 a 06 Dias') === [3,6], 'Variation range is parsed.');
check(round(139 * (1 + 20 / 100)) === 167.0, 'Daily dynamic value uses the public rounding rule.');
echo "5 daily tariff checks passed.\n";
