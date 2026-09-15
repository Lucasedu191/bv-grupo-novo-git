<?php
if (!defined('ABSPATH')) exit;

class BVGN_TariffHistoryRepository {
  const VERSION = '2';
  const OPTION = 'bvgn_tariff_history_db_version';
  const FIELDS = ['valor_1_dia', 'valor_3_dias', 'valor_7_dias', 'valor_15_dias'];

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'bv_historico_tarifas';
  }

  public static function install() {
    if (get_option(self::OPTION) === self::VERSION) return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = self::table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      grupo_id bigint(20) unsigned NOT NULL,
      valor_1_dia decimal(18,2) DEFAULT NULL,
      valor_3_dias decimal(18,2) DEFAULT NULL,
      valor_7_dias decimal(18,2) DEFAULT NULL,
      valor_15_dias decimal(18,2) DEFAULT NULL,
      data_alteracao datetime NOT NULL,
      usuario_id bigint(20) unsigned NOT NULL DEFAULT 0,
      tipo varchar(16) NOT NULL DEFAULT 'geral',
      regra_id bigint(20) unsigned NOT NULL DEFAULT 0,
      evento varchar(24) NOT NULL DEFAULT '',
      contexto longtext DEFAULT NULL,
      PRIMARY KEY  (id),
      KEY grupo_historico (grupo_id,id),
      KEY origem_historico (grupo_id,tipo,regra_id,id),
      KEY data_alteracao (data_alteracao,id)
    ) $charset;");
    $schema_error = $wpdb->last_error;
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
    $required = array_merge(self::FIELDS, ['id', 'grupo_id', 'data_alteracao', 'usuario_id', 'tipo', 'regra_id', 'evento', 'contexto']);
    if (!$schema_error && !$wpdb->last_error && !array_diff($required, $columns ?: [])) {
      update_option(self::OPTION, self::VERSION, false);
    } else {
      error_log('[BVGN] Não foi possível instalar a tabela de histórico de tarifas.');
    }
  }

  public static function values($row) {
    $values = [];
    foreach (self::FIELDS as $field) {
      $values[$field] = isset($row[$field]) ? number_format((float) $row[$field], 2, '.', '') : null;
    }
    return $values;
  }

  public static function latest($group_id, $before_id = 0) {
    global $wpdb;
    $table = self::table();
    $sql = "SELECT * FROM $table WHERE grupo_id = %d AND tipo = 'geral'";
    $args = [absint($group_id)];
    if ($before_id) {
      $sql .= ' AND id < %d';
      $args[] = absint($before_id);
    }
    return $wpdb->get_row($wpdb->prepare($sql . ' ORDER BY id DESC LIMIT 1', $args), ARRAY_A);
  }

  // Read the final source values inside the lock, preventing concurrent duplicate snapshots.
  public static function record($group_id, $before, $user_id) {
    global $wpdb;
    self::install();
    if (get_option(self::OPTION) !== self::VERSION) return;
    $lock = 'bvgn_th_' . md5(self::table() . ':' . absint($group_id));
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
      error_log('[BVGN] Histórico de tarifas: não foi possível obter bloqueio do grupo ' . absint($group_id));
      return;
    }
    try {
      $current = BVGN_TariffHistory::snapshot($group_id);
      if ($current === null || $current === $before) return;
      $last = self::latest($group_id);
      if ($wpdb->last_error) {
        error_log('[BVGN] Falha ao consultar o histórico de tarifas.');
        return;
      }
      if ($last && self::values($last) === $current) return;
      $data = array_merge(['grupo_id' => absint($group_id)], $current, [
        'data_alteracao' => current_time('mysql', true),
        'usuario_id' => absint($user_id),
      ]);
      if ($wpdb->insert(self::table(), $data, ['%d', '%s', '%s', '%s', '%s', '%s', '%d']) === false) {
        error_log('[BVGN] Falha ao gravar histórico de tarifas do grupo ' . absint($group_id));
      }
    } finally {
      $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
  }

  public static function record_dynamic($group_id, $rule_id, $event, $context, $user_id) {
    global $wpdb;
    self::install();
    if (get_option(self::OPTION) !== self::VERSION) return;
    $data = [
      'grupo_id' => absint($group_id), 'tipo' => 'dinamica', 'regra_id' => absint($rule_id),
      'evento' => $event, 'contexto' => wp_json_encode($context),
      'data_alteracao' => current_time('mysql', true), 'usuario_id' => absint($user_id),
    ];
    if ($wpdb->insert(self::table(), $data, ['%d', '%s', '%d', '%s', '%s', '%s', '%d']) === false) {
      error_log('[BVGN] Falha ao gravar histórico de tarifa dinâmica #' . absint($rule_id));
    }
  }

  public static function search($group_id, $start, $end, $page, $type = '') {
    global $wpdb;
    $table = self::table();
    $where = 'WHERE 1=1';
    $args = [];
    if (in_array($type, ['geral', 'dinamica'], true)) {
      $where .= ' AND tipo = %s';
      $args[] = $type;
    }
    foreach (['grupo_id = %d' => $group_id, 'data_alteracao >= %s' => $start, 'data_alteracao < %s' => $end] as $condition => $value) {
      if ($value !== '' && $value !== 0) {
        $where .= ' AND ' . $condition;
        $args[] = $value;
      }
    }
    $count_sql = "SELECT COUNT(*) FROM $table $where";
    $total = (int) $wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);
    $page = min(max(1, (int) $page), max(1, (int) ceil($total / 50)));
    $args[] = 50;
    $args[] = ($page - 1) * 50;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table $where ORDER BY data_alteracao DESC, id DESC LIMIT %d OFFSET %d", $args), ARRAY_A);
    return ['rows' => $rows ?: [], 'total' => $total, 'page' => $page];
  }

  public static function groups() {
    global $wpdb;
    return $wpdb->get_col('SELECT DISTINCT grupo_id FROM ' . self::table() . ' ORDER BY grupo_id') ?: [];
  }
}
