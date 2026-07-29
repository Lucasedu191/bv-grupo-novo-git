<?php
if (!defined('ABSPATH')) exit;

/**
 * Gerencia as regras de Tarifa Dinamica (admin + exposicao para o front).
 */
class BVGN_DynamicTariffs {
  const OPTION_KEY = 'bvgn_dynamic_tariffs';
  const NEXT_ID_OPTION_KEY = 'bvgn_dynamic_tariffs_next_id';
  const WEBHOOK_URL = 'https://ipaas.ecomtools.com.br/webhook/bv-tarifa-dinamica';

  public static function init() {
    if (is_admin()) {
      add_action('admin_menu', [__CLASS__, 'register_menu']);
      add_action('admin_post_bvgn_save_tariffs', [__CLASS__, 'save']);
    }
  }

  public static function register_menu() {
    add_submenu_page(
      'edit.php?post_type=bvgn_cotacao',
      'Tarifa Dinâmica BV',
      'Tarifa Dinâmica',
      'manage_options',
      'bvgn-tarifa-dinamica',
      [__CLASS__, 'render_page']
    );
  }

  public static function render_page() {
    if (!current_user_can('manage_options')) {
      wp_die('Sem permissão.');
    }

    $rules = self::get_rules();
    $conflicts = self::find_rule_conflicts($rules);
    ?>
    <div class="wrap">
      <h1>Tarifa Dinâmica</h1>
      <p>Cadastre regras de acréscimo percentual por dia da semana, datas específicas ou intervalos. A regra com maior prioridade vence no dia.</p>
      <?php if (!empty($_GET['atualizado'])): ?>
        <div class="notice notice-success is-dismissible"><p>Regras salvas com sucesso.</p></div>
      <?php endif; ?>
      <?php if (!empty($conflicts)): ?>
        <div class="notice notice-warning">
          <p><strong>Atenção:</strong> existem tarifas ativas com sobreposição de grupo e aplicação. Revise os conflitos abaixo para evitar cobrança indevida.</p>
          <ul style="list-style:disc; margin-left:20px;">
            <?php foreach ($conflicts as $conflict): ?>
              <li><?php echo esc_html($conflict); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('bvgn_save_tariffs'); ?>
        <input type="hidden" name="action" value="bvgn_save_tariffs" />

        <table class="widefat striped" style="margin-top:12px;">
          <thead>
            <tr>
              <th style="width:120px;">Tipo</th>
              <th style="width:70px;">% Extra</th>
              <th>Nome/Descrição</th>
              <th style="width:200px;">Observação curta</th>
              <th style="width:90px;">Prioridade</th>
              <th style="width:120px;">Dia da semana</th>
              <th style="width:140px;">Data inicial</th>
              <th style="width:140px;">Data final</th>
              <th style="width:140px;">Grupos diários</th>
              <th style="width:80px;">Ativa</th>
              <th style="width:100px;">Exibir resumo</th>
              <th style="width:80px;">Exibir PDF</th>
              <th style="width:70px;">Ação</th>
            </tr>
          </thead>
          <tbody id="bvgn-rows">
            <?php
              $rows = !empty($rules) ? $rules : [
                ['type'=>'week_day','percent'=>30,'label'=>'Domingo (+30%)','priority'=>10,'weekday'=>0,'start_date'=>'','end_date'=>'','show_resumo'=>true,'show_pdf'=>true]
              ];
              foreach ($rows as $i => $r):
            ?>
              <tr>
                <td>
                  <input type="hidden" name="rules[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($r['id'] ?? ''); ?>">
                  <select name="rules[<?php echo esc_attr($i); ?>][type]">
                    <option value="week_day" <?php selected($r['type'], 'week_day'); ?>>Dia da semana</option>
                    <option value="single_date" <?php selected($r['type'], 'single_date'); ?>>Data específica</option>
                    <option value="date_range" <?php selected($r['type'], 'date_range'); ?>>Intervalo de datas</option>
                  </select>
                </td>
                <td>
                  <input type="number" step="0.01" min="0" name="rules[<?php echo esc_attr($i); ?>][percent]" value="<?php echo esc_attr(self::format_percent_for_input($r['percent'] ?? 0)); ?>" style="width:100%;">
                </td>
                <td>
                  <input type="text" name="rules[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($r['label']); ?>" style="width:100%;">
                </td>
                <td>
                  <input type="text" name="rules[<?php echo esc_attr($i); ?>][desc]" value="<?php echo esc_attr($r['desc'] ?? ''); ?>" style="width:100%;" maxlength="120" placeholder="Ex.: Alta demanda de fds">
                  <small style="color:#666;">até 120 caracteres</small>
                </td>
                <td>
                  <input type="number" name="rules[<?php echo esc_attr($i); ?>][priority]" value="<?php echo esc_attr($r['priority']); ?>" style="width:100%;">
                </td>
                <td>
                  <select name="rules[<?php echo esc_attr($i); ?>][weekday]">
                    <?php
                      $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                      foreach ($dias as $idx => $dia): ?>
                        <option value="<?php echo esc_attr($idx); ?>" <?php selected((int) $r['weekday'], $idx); ?>><?php echo esc_html($dia); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="date" name="rules[<?php echo esc_attr($i); ?>][start_date]" value="<?php echo esc_attr($r['start_date']); ?>"></td>
                <td><input type="date" name="rules[<?php echo esc_attr($i); ?>][end_date]" value="<?php echo esc_attr($r['end_date']); ?>"></td>
                <td>
                  <input type="text" name="rules[<?php echo esc_attr($i); ?>][groups]" value="<?php echo esc_attr(!empty($r['groups']) && is_array($r['groups']) ? implode(',', $r['groups']) : ''); ?>" placeholder="Ex.: A,B,C" style="width:100%;">
                  <small style="color:#666;">Deixe vazio para todos</small>
                </td>
                <td style="text-align:center;">
                  <input type="hidden" name="rules[<?php echo esc_attr($i); ?>][active]" value="0">
                  <label><input type="checkbox" name="rules[<?php echo esc_attr($i); ?>][active]" value="1" <?php checked(self::is_rule_effectively_active($r)); ?>> Sim</label>
                </td>
                <td style="text-align:center;">
                  <input type="hidden" name="rules[<?php echo esc_attr($i); ?>][show_resumo]" value="0">
                  <label><input type="checkbox" name="rules[<?php echo esc_attr($i); ?>][show_resumo]" value="1" <?php checked(!empty($r['show_resumo'])); ?>> Sim</label>
                </td>
                <td style="text-align:center;">
                  <input type="hidden" name="rules[<?php echo esc_attr($i); ?>][show_pdf]" value="0">
                  <label><input type="checkbox" name="rules[<?php echo esc_attr($i); ?>][show_pdf]" value="1" <?php checked(!empty($r['show_pdf'])); ?>> Sim</label>
                </td>
                <td><button type="button" class="button link-delete">Remover</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <p style="margin-top:10px;">
          <button type="button" class="button" id="bvgn-add-row">Adicionar regra</button>
          <button type="submit" class="button button-primary">Salvar regras</button>
        </p>
      </form>
    </div>
    <script>
      (function(){
        const tbody = document.getElementById('bvgn-rows');
        const btnAdd = document.getElementById('bvgn-add-row');
        if (!tbody || !btnAdd) return;

        btnAdd.addEventListener('click', function(){
          const idx = tbody.querySelectorAll('tr').length;
          const tpl = `
            <tr>
              <td>
                <input type="hidden" name="rules[${idx}][id]" value="">
                <select name="rules[${idx}][type]">
                  <option value="week_day">Dia da semana</option>
                  <option value="single_date">Data específica</option>
                  <option value="date_range">Intervalo de datas</option>
                </select>
              </td>
              <td><input type="number" step="0.01" min="0" name="rules[${idx}][percent]" value="0" style="width:100%;"></td>
              <td><input type="text" name="rules[${idx}][label]" value="" style="width:100%;"></td>
              <td>
                <input type="text" name="rules[${idx}][desc]" value="" style="width:100%;" maxlength="120" placeholder="Frase curta">
                <small style="color:#666;">até 120 caracteres</small>
              </td>
              <td><input type="number" name="rules[${idx}][priority]" value="0" style="width:100%;"></td>
              <td>
                <select name="rules[${idx}][weekday]">
                  <option value="0">Dom</option><option value="1">Seg</option><option value="2">Ter</option>
                  <option value="3">Qua</option><option value="4">Qui</option><option value="5">Sex</option><option value="6">Sáb</option>
                </select>
              </td>
              <td><input type="date" name="rules[${idx}][start_date]" value=""></td>
              <td><input type="date" name="rules[${idx}][end_date]" value=""></td>
              <td>
                <input type="text" name="rules[${idx}][groups]" value="" placeholder="Ex.: A,B,C" style="width:100%;">
                <small style="color:#666;">Deixe vazio para todos</small>
              </td>
              <td style="text-align:center;">
                <input type="hidden" name="rules[${idx}][active]" value="0">
                <label><input type="checkbox" name="rules[${idx}][active]" value="1" checked> Sim</label>
              </td>
              <td style="text-align:center;">
                <input type="hidden" name="rules[${idx}][show_resumo]" value="0">
                <label><input type="checkbox" name="rules[${idx}][show_resumo]" value="1" checked> Sim</label>
              </td>
              <td style="text-align:center;">
                <input type="hidden" name="rules[${idx}][show_pdf]" value="0">
                <label><input type="checkbox" name="rules[${idx}][show_pdf]" value="1" checked> Sim</label>
              </td>
              <td><button type="button" class="button link-delete">Remover</button></td>
            </tr>`;
          tbody.insertAdjacentHTML('beforeend', tpl);
        });

        tbody.addEventListener('click', function(e){
          if (e.target && e.target.classList.contains('link-delete')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            if (tr) tr.remove();
          }
        });
      })();
    </script>
    <?php
  }

  public static function save() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('bvgn_save_tariffs');

    $raw = $_POST['rules'] ?? [];
    self::replace_rules($raw);

    wp_redirect(add_query_arg('atualizado', '1', admin_url('edit.php?post_type=bvgn_cotacao&page=bvgn-tarifa-dinamica')));
    exit;
  }

  public static function replace_rules($raw_rules) {
    $previous_rules = self::get_rules();
    $sanitized = [];
    $seen_ids = [];
    if (is_array($raw_rules)) {
      foreach ($raw_rules as $r) {
        $s = self::sanitize_rule($r, $seen_ids);
        if ($s) $sanitized[] = $s;
      }
    }

    $sanitized = self::sort_rules($sanitized);
    update_option(self::OPTION_KEY, $sanitized);
    self::dispatch_webhook_events($previous_rules, $sanitized);

    return $sanitized;
  }

  public static function sanitize_rule($r, &$seen_ids = null) {
    $type = isset($r['type']) ? $r['type'] : 'week_day';
    if (!in_array($type, ['week_day', 'single_date', 'date_range'], true)) $type = 'week_day';

    $id = isset($r['id']) ? absint($r['id']) : 0;
    if ($id <= 0 || (is_array($seen_ids) && in_array($id, $seen_ids, true))) {
      $id = self::generate_rule_id();
    }
    if (is_array($seen_ids)) {
      $seen_ids[] = $id;
    }

    $percent_raw = $r['percent'] ?? 0;
    if (is_string($percent_raw)) {
      $percent_raw = str_replace(',', '.', $percent_raw);
    }
    $percent  = floatval($percent_raw);
    $priority = isset($r['priority']) ? intval($r['priority']) : 0;
    $label    = sanitize_text_field($r['label'] ?? '');
    $descRaw  = sanitize_text_field($r['desc'] ?? '');
    if (function_exists('mb_substr')) {
      $descRaw = mb_substr($descRaw, 0, 120);
    } else {
      $descRaw = substr($descRaw, 0, 120);
    }
    $weekday  = isset($r['weekday']) ? intval($r['weekday']) : 0;
    $start    = sanitize_text_field($r['start_date'] ?? '');
    $end      = sanitize_text_field($r['end_date'] ?? '');
    $groupsInput = $r['groups'] ?? '';
    if (is_array($groupsInput)) {
      $groupsRawList = $groupsInput;
    } else {
      $groupsRawList = explode(',', (string) $groupsInput);
    }
    $groups = array_values(array_filter(array_unique(array_map(function($g){
      $g = strtoupper(trim($g));
      return preg_match('/^[A-Z]$/', $g) ? $g : '';
    }, $groupsRawList))));

    $showResumo = !empty($r['show_resumo']);
    $showPdf    = !empty($r['show_pdf']);
    $active     = !empty($r['active']);

    return [
      'id'          => $id,
      'type'        => $type,
      'percent'     => $percent,
      'label'       => $label ?: ucfirst($type),
      'desc'        => $descRaw,
      'priority'    => $priority,
      'weekday'     => $weekday,
      'start_date'  => $start,
      'end_date'    => $end,
      'groups'      => $groups,
      'active'      => $active,
      'show_resumo' => $showResumo,
      'show_pdf'    => $showPdf,
    ];
  }

  public static function get_rules() {
    $rules = get_option(self::OPTION_KEY, []);
    if (!is_array($rules)) $rules = [];

    $clean = [];
    $seen_ids = [];
    $has_changes = false;
    foreach ($rules as $r) {
      $s = self::sanitize_rule($r, $seen_ids);
      if (!$s) continue;

      if (self::is_rule_expired($s) && !empty($s['active'])) {
        $s['active'] = false;
        $has_changes = true;
      }

      $clean[] = $s;
    }

    $clean = self::sort_rules($clean);
    if ($has_changes || $clean !== array_values($rules)) {
      update_option(self::OPTION_KEY, $clean);
    }

    return $clean;
  }

  public static function sort_rules($rules) {
    if (!is_array($rules)) return [];

    usort($rules, function($a, $b){
      $a_active = self::is_rule_effectively_active($a) ? 1 : 0;
      $b_active = self::is_rule_effectively_active($b) ? 1 : 0;

      if ($a_active !== $b_active) {
        return $b_active <=> $a_active;
      }

      $priority_compare = intval($b['priority']) <=> intval($a['priority']);
      if ($priority_compare !== 0) {
        return $priority_compare;
      }

      return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
    });

    return array_values($rules);
  }

  public static function for_js() {
    $list = [];
    foreach (self::get_rules() as $r) {
      $list[] = [
        'id'         => intval($r['id'] ?? 0),
        'type'       => $r['type'],
        'percent'    => floatval($r['percent']),
        'label'      => $r['label'],
        'priority'   => intval($r['priority']),
        'weekday'    => intval($r['weekday']),
        'startDate'  => $r['start_date'],
        'endDate'    => $r['end_date'],
        'desc'       => $r['desc'],
        'groups'     => self::expand_groups_for_api($r['groups'] ?? []),
        'active'     => self::is_rule_effectively_active($r),
        'showResumo' => !empty($r['show_resumo']),
        'showPdf'    => !empty($r['show_pdf']),
      ];
    }
    return $list;
  }

  public static function get_all_groups() {
    return ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
  }

  public static function expand_groups_for_api($groups) {
    if (!is_array($groups) || empty($groups)) {
      return self::get_all_groups();
    }

    return array_values(array_map('sanitize_text_field', $groups));
  }

  public static function is_rule_effectively_active($rule) {
    if (!is_array($rule) || empty($rule['active'])) {
      return false;
    }

    return !self::is_rule_expired($rule);
  }

  private static function find_rule_conflicts($rules) {
    if (!is_array($rules) || count($rules) < 2) {
      return [];
    }

    $conflicts = [];
    $count = count($rules);
    for ($i = 0; $i < $count; $i++) {
      $rule_a = $rules[$i];
      if (!self::is_rule_effectively_active($rule_a)) continue;

      for ($j = $i + 1; $j < $count; $j++) {
        $rule_b = $rules[$j];
        if (!self::is_rule_effectively_active($rule_b)) continue;

        $shared_groups = self::intersect_rule_groups($rule_a, $rule_b);
        if (empty($shared_groups)) continue;
        if (!self::rules_overlap_on_same_day($rule_a, $rule_b)) continue;

        $conflicts[] = sprintf(
          'Regras #%d (%s) e #%d (%s) podem coincidir nos grupos %s.',
          intval($rule_a['id'] ?? 0),
          self::get_rule_label_for_notice($rule_a),
          intval($rule_b['id'] ?? 0),
          self::get_rule_label_for_notice($rule_b),
          implode(', ', $shared_groups)
        );
      }
    }

    return $conflicts;
  }

  private static function dispatch_webhook_events($previous_rules, $current_rules) {
    if (!function_exists('wp_remote_post')) return;

    $webhook_url = self::get_webhook_url();
    if ($webhook_url === '') return;

    $previous_map = [];
    if (is_array($previous_rules)) {
      foreach ($previous_rules as $rule) {
        $rule_id = isset($rule['id']) ? absint($rule['id']) : 0;
        if ($rule_id > 0) {
          $previous_map[$rule_id] = self::normalize_rule_for_compare($rule);
        }
      }
    }

    foreach ((array) $current_rules as $rule) {
      $rule_id = isset($rule['id']) ? absint($rule['id']) : 0;
      if ($rule_id <= 0) continue;

      $normalized = self::normalize_rule_for_compare($rule);
      $event = !isset($previous_map[$rule_id]) ? 'created' : 'updated';
      if ($event === 'updated' && $previous_map[$rule_id] === $normalized) {
        continue;
      }

      $payload = self::format_rule_for_api($rule);
      $payload['event'] = $event;
      $payload['sent_at'] = current_time('mysql');

      $response = wp_remote_post($webhook_url, [
        'method' => 'POST',
        'timeout' => 15,
        'headers' => [
          'Content-Type' => 'application/json; charset=utf-8',
        ],
        'body' => wp_json_encode($payload),
      ]);

      if (is_wp_error($response)) {
        error_log('[BVGN] Falha ao enviar webhook de tarifa dinâmica: ' . $response->get_error_message());
      }
    }
  }

  private static function get_webhook_url() {
    $url = self::WEBHOOK_URL;
    if (defined('BVGN_DYNAMIC_TARIFF_WEBHOOK_URL') && is_string(BVGN_DYNAMIC_TARIFF_WEBHOOK_URL) && trim(BVGN_DYNAMIC_TARIFF_WEBHOOK_URL) !== '') {
      $url = BVGN_DYNAMIC_TARIFF_WEBHOOK_URL;
    }

    $url = apply_filters('bvgn_dynamic_tariffs_webhook_url', $url);
    return is_string($url) ? trim($url) : '';
  }

  private static function normalize_rule_for_compare($rule) {
    $normalized = self::format_rule_for_api($rule);
    unset($normalized['event'], $normalized['sent_at']);
    return $normalized;
  }

  private static function format_rule_for_api($rule) {
    return [
      'id' => intval($rule['id'] ?? 0),
      'tipo' => sanitize_text_field($rule['type'] ?? ''),
      'percentual' => floatval($rule['percent'] ?? 0),
      'rotulo' => sanitize_text_field($rule['label'] ?? ''),
      'descricao' => sanitize_text_field($rule['desc'] ?? ''),
      'prioridade' => intval($rule['priority'] ?? 0),
      'dia_semana' => intval($rule['weekday'] ?? 0),
      'data_inicio' => sanitize_text_field($rule['start_date'] ?? ''),
      'data_fim' => sanitize_text_field($rule['end_date'] ?? ''),
      'grupos' => self::expand_groups_for_api($rule['groups'] ?? []),
      'ativa' => self::is_rule_effectively_active($rule),
      'exibir_resumo' => !empty($rule['show_resumo']),
      'exibir_pdf' => !empty($rule['show_pdf']),
    ];
  }

  private static function is_rule_expired($rule) {
    if (!is_array($rule)) return false;

    $end_date = sanitize_text_field($rule['end_date'] ?? '');
    if ($end_date === '') return false;

    $today = current_time('Y-m-d');
    return $today > $end_date;
  }

  private static function intersect_rule_groups($rule_a, $rule_b) {
    $groups_a = self::expand_groups_for_api($rule_a['groups'] ?? []);
    $groups_b = self::expand_groups_for_api($rule_b['groups'] ?? []);

    return array_values(array_intersect($groups_a, $groups_b));
  }

  private static function rules_overlap_on_same_day($rule_a, $rule_b) {
    $type_a = sanitize_text_field($rule_a['type'] ?? '');
    $type_b = sanitize_text_field($rule_b['type'] ?? '');

    if ($type_a === 'week_day' && $type_b === 'week_day') {
      return intval($rule_a['weekday'] ?? -1) === intval($rule_b['weekday'] ?? -2);
    }

    if ($type_a === 'single_date' && $type_b === 'single_date') {
      return sanitize_text_field($rule_a['start_date'] ?? '') === sanitize_text_field($rule_b['start_date'] ?? '');
    }

    if ($type_a === 'date_range' && $type_b === 'date_range') {
      return self::date_ranges_overlap(
        sanitize_text_field($rule_a['start_date'] ?? ''),
        sanitize_text_field($rule_a['end_date'] ?? ''),
        sanitize_text_field($rule_b['start_date'] ?? ''),
        sanitize_text_field($rule_b['end_date'] ?? '')
      );
    }

    if ($type_a === 'week_day' && $type_b === 'single_date') {
      return self::single_date_matches_weekday($rule_b, $rule_a);
    }

    if ($type_a === 'single_date' && $type_b === 'week_day') {
      return self::single_date_matches_weekday($rule_a, $rule_b);
    }

    if ($type_a === 'week_day' && $type_b === 'date_range') {
      return self::date_range_contains_weekday($rule_b, intval($rule_a['weekday'] ?? -1));
    }

    if ($type_a === 'date_range' && $type_b === 'week_day') {
      return self::date_range_contains_weekday($rule_a, intval($rule_b['weekday'] ?? -1));
    }

    if ($type_a === 'single_date' && $type_b === 'date_range') {
      return self::single_date_in_range($rule_a, $rule_b);
    }

    if ($type_a === 'date_range' && $type_b === 'single_date') {
      return self::single_date_in_range($rule_b, $rule_a);
    }

    return false;
  }

  private static function single_date_matches_weekday($single_rule, $weekday_rule) {
    $date = self::parse_rule_date($single_rule['start_date'] ?? '');
    if (!$date) return false;

    return intval($date->format('w')) === intval($weekday_rule['weekday'] ?? -1);
  }

  private static function single_date_in_range($single_rule, $range_rule) {
    $date = sanitize_text_field($single_rule['start_date'] ?? '');
    $start = sanitize_text_field($range_rule['start_date'] ?? '');
    $end = sanitize_text_field($range_rule['end_date'] ?? '');
    if ($date === '' || $start === '' || $end === '') return false;

    return $date >= $start && $date <= $end;
  }

  private static function date_range_contains_weekday($range_rule, $weekday) {
    if ($weekday < 0 || $weekday > 6) return false;

    $start = self::parse_rule_date($range_rule['start_date'] ?? '');
    $end = self::parse_rule_date($range_rule['end_date'] ?? '');
    if (!$start || !$end || $start > $end) return false;

    $interval_days = (int) $start->diff($end)->format('%a');
    if ($interval_days >= 6) {
      return true;
    }

    $cursor = clone $start;
    while ($cursor <= $end) {
      if (intval($cursor->format('w')) === $weekday) {
        return true;
      }
      $cursor->modify('+1 day');
    }

    return false;
  }

  private static function date_ranges_overlap($start_a, $end_a, $start_b, $end_b) {
    if ($start_a === '' || $end_a === '' || $start_b === '' || $end_b === '') {
      return false;
    }

    return $start_a <= $end_b && $start_b <= $end_a;
  }

  private static function parse_rule_date($date) {
    $date = sanitize_text_field($date);
    if ($date === '') return null;

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed instanceof DateTime) return null;
    $parsed->setTime(0, 0, 0);
    return $parsed;
  }

  private static function get_rule_label_for_notice($rule) {
    $label = sanitize_text_field($rule['label'] ?? '');
    if ($label !== '') {
      return $label;
    }

    return sanitize_text_field($rule['type'] ?? 'regra');
  }

  private static function format_percent_for_input($value) {
    $number = is_numeric($value) ? (float) $value : floatval(str_replace(',', '.', (string) $value));
    $formatted = number_format($number, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
  }

  private static function generate_rule_id() {
    $next_id = absint(get_option(self::NEXT_ID_OPTION_KEY, 0));

    if ($next_id <= 0) {
      $rules = get_option(self::OPTION_KEY, []);
      $max_id = 0;
      if (is_array($rules)) {
        foreach ($rules as $rule) {
          $rule_id = isset($rule['id']) ? absint($rule['id']) : 0;
          if ($rule_id > $max_id) $max_id = $rule_id;
        }
      }
      $next_id = $max_id + 1;
    }

    update_option(self::NEXT_ID_OPTION_KEY, $next_id + 1, false);
    return $next_id;
  }
}

BVGN_DynamicTariffs::init();
