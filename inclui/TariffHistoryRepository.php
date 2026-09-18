<?php
if (!defined('ABSPATH')) exit;

/** Tabela nova: a tabela legada bv_historico_tarifas não é alterada nem apagada. */
class BVGN_TariffHistoryRepository {
  const VERSION = '8';
  const OPTION = 'bvgn_daily_tariff_history_db_version';
  public static function table() { global $wpdb; return $wpdb->prefix . 'bv_historico_diario_tarifas'; }
  public static function install() {
    if (get_option(self::OPTION) === self::VERSION) return;
    global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = self::table(); $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      grupo_id bigint(20) unsigned NOT NULL,
      data_referencia date NOT NULL,
      valor_diaria decimal(18,2) DEFAULT NULL,
      valor_3_dias decimal(18,2) DEFAULT NULL,
      valor_7_dias decimal(18,2) DEFAULT NULL,
      valor_15_dias decimal(18,2) DEFAULT NULL,
      protecao_basica decimal(18,2) DEFAULT NULL,
      protecao_premium decimal(18,2) DEFAULT NULL,
      taxa_lavagem decimal(18,2) DEFAULT NULL,
      data_atualizacao datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY grupo_data (grupo_id,data_referencia),
      KEY data_referencia (data_referencia,grupo_id)
    ) $charset;");
    // dbDelta não altera apenas a nulabilidade de um campo com o mesmo tipo.
    $daily = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'valor_diaria'");
    if ($daily && $daily->Null !== 'YES') {
      $wpdb->query("ALTER TABLE $table MODIFY COLUMN valor_diaria decimal(18,2) DEFAULT NULL");
      $daily = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'valor_diaria'");
    }
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
    if (!$wpdb->last_error && $daily && $daily->Null === 'YES' && !array_diff(['id','grupo_id','data_referencia','valor_diaria','valor_3_dias','valor_7_dias','valor_15_dias','protecao_basica','protecao_premium','taxa_lavagem','data_atualizacao'], $columns ?: [])) update_option(self::OPTION, self::VERSION, false);
    else error_log('[BVGN] Não foi possível instalar a tabela do histórico diário de tarifas.');
  }
  /** A chave única e este upsert tornam o processo idempotente, inclusive sob concorrência. */
  public static function upsert($group_id, $date, $values) {
    global $wpdb; self::install(); if (get_option(self::OPTION) !== self::VERSION) return false;
    // Mantém NULL distinto de zero, inclusive nos planos não aplicáveis.
    $valueSlots = [];
    $args = [absint($group_id), $date];
    foreach (['valor_diaria','valor_3_dias','valor_7_dias','valor_15_dias','protecao_basica','protecao_premium','taxa_lavagem'] as $field) {
      $value = $values[$field] ?? null;
      $valueSlots[] = is_numeric($value) ? '%f' : 'NULL';
      if (is_numeric($value)) $args[] = (float)$value;
    }
    $args[] = current_time('mysql', true);
    $sql = "INSERT INTO " . self::table() . " (grupo_id,data_referencia,valor_diaria,valor_3_dias,valor_7_dias,valor_15_dias,protecao_basica,protecao_premium,taxa_lavagem,data_atualizacao) VALUES (%d,%s," . implode(',', $valueSlots) . ",%s) ON DUPLICATE KEY UPDATE valor_diaria=VALUES(valor_diaria),valor_3_dias=VALUES(valor_3_dias),valor_7_dias=VALUES(valor_7_dias),valor_15_dias=VALUES(valor_15_dias),protecao_basica=VALUES(protecao_basica),protecao_premium=VALUES(protecao_premium),taxa_lavagem=VALUES(taxa_lavagem),data_atualizacao=VALUES(data_atualizacao)";
    $result = $wpdb->query($wpdb->prepare($sql, $args));
    if ($result === false) error_log('[BVGN] Falha ao gravar histórico diário do grupo ' . absint($group_id));
    return $result !== false;
  }
  public static function search($group_id, $start, $end, $page) {
    global $wpdb; $table=self::table(); $where='WHERE 1=1'; $args=[];
    if ($group_id) { $where.=' AND grupo_id=%d'; $args[]=absint($group_id); }
    if ($start !== '') { $where.=' AND data_referencia >= %s'; $args[]=$start; }
    if ($end !== '') { $where.=' AND data_referencia <= %s'; $args[]=$end; }
    $total=(int)$wpdb->get_var($args?$wpdb->prepare("SELECT COUNT(*) FROM $table $where",$args):"SELECT COUNT(*) FROM $table $where");
    $page=min(max(1,(int)$page),max(1,(int)ceil($total/50))); $args[]=50; $args[]=($page-1)*50;
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table $where ORDER BY data_referencia DESC,grupo_id ASC LIMIT %d OFFSET %d",$args),ARRAY_A);
    return ['rows'=>$rows?:[],'total'=>$total,'page'=>$page];
  }
  public static function groups() { global $wpdb; return $wpdb->get_col('SELECT DISTINCT grupo_id FROM '.self::table().' ORDER BY grupo_id')?:[]; }
}
