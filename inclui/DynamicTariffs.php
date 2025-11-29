<?php
if (!defined('ABSPATH')) exit;

/**
 * Gerencia as regras de Tarifa Dinâmica (admin + exposição para o front).
 */
class BVGN_DynamicTariffs {
  const OPTION_KEY = 'bvgn_dynamic_tariffs';

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
    ?>
    <div class="wrap">
      <h1>Tarifa Dinâmica</h1>
      <p>Cadastre regras de acréscimo percentual por dia da semana, datas específicas ou intervalos. A regra com maior prioridade vence no dia.</p>
      <?php if (!empty($_GET['atualizado'])): ?>
        <div class="notice notice-success is-dismissible"><p>Regras salvas com sucesso.</p></div>
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
                  <select name="rules[<?php echo esc_attr($i); ?>][type]">
                    <option value="week_day" <?php selected($r['type'],'week_day'); ?>>Dia da semana</option>
                    <option value="single_date" <?php selected($r['type'],'single_date'); ?>>Data específica</option>
                    <option value="date_range" <?php selected($r['type'],'date_range'); ?>>Intervalo de datas</option>
                  </select>
                </td>
                <td>
                  <input type="number" step="0.01" min="0" name="rules[<?php echo esc_attr($i); ?>][percent]" value="<?php echo esc_attr($r['percent']); ?>" style="width:100%;">
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
                      $dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                      foreach ($dias as $idx=>$dia): ?>
                        <option value="<?php echo esc_attr($idx); ?>" <?php selected((int)$r['weekday'],$idx); ?>><?php echo esc_html($dia); ?></option>
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
                  <label><input type="checkbox" name="rules[<?php echo esc_attr($i); ?>][active]" value="1" <?php checked(!empty($r['active'])); ?>> Sim</label>
                </td>
                <td style="text-align:center;">
                  <label><input type="checkbox" name="rules[<?php echo esc_attr($i); ?>][show_resumo]" value="1" <?php checked(!empty($r['show_resumo'])); ?>> Sim</label>
                </td>
                <td style="text-align:center;">
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
                <label><input type="checkbox" name="rules[${idx}][show_resumo]" value="1" checked> Sim</label>
              </td>
              <td style="text-align:center;">
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
    $sanitized = [];
    if (is_array($raw)) {
      foreach ($raw as $r) {
        $s = self::sanitize_rule($r);
        if ($s) $sanitized[] = $s;
      }
    }
    update_option(self::OPTION_KEY, $sanitized);

    wp_redirect(add_query_arg('atualizado', '1', admin_url('edit.php?post_type=bvgn_cotacao&page=bvgn-tarifa-dinamica')));
    exit;
  }

  private static function sanitize_rule($r) {
    $type = isset($r['type']) ? $r['type'] : 'week_day';
    if (!in_array($type, ['week_day','single_date','date_range'], true)) $type = 'week_day';

    $percent  = isset($r['percent']) ? floatval($r['percent']) : 0;
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
    // grupos pode vir como string "A,B" ou array ['A','B']; tratamos ambos
    $groupsInput = $r['groups'] ?? '';
    if (is_array($groupsInput)) {
      $groupsRawList = $groupsInput;
    } else {
      $groupsRawList = explode(',', (string)$groupsInput);
    }
    $groups = array_values(array_filter(array_unique(array_map(function($g){
      $g = strtoupper(trim($g));
      return preg_match('/^[A-Z]$/', $g) ? $g : '';
    }, $groupsRawList))));

    $showResumo = !empty($r['show_resumo']);
    $showPdf    = !empty($r['show_pdf']);
    $active     = !empty($r['active']);

    return [
      'type'       => $type,
      'percent'    => $percent,
      'label'      => $label ?: ucfirst($type),
      'desc'       => $descRaw,
      'priority'   => $priority,
      'weekday'    => $weekday,
      'start_date' => $start,
      'end_date'   => $end,
      'groups'     => $groups,
      'active'     => $active,
      'show_resumo' => $showResumo,
      'show_pdf'    => $showPdf,
    ];
  }

  public static function get_rules() {
    $rules = get_option(self::OPTION_KEY, []);
    if (!is_array($rules)) $rules = [];

    $clean = [];
    foreach ($rules as $r) {
      $s = self::sanitize_rule($r);
      if ($s) $clean[] = $s;
    }

    usort($clean, function($a, $b){
      return intval($b['priority']) <=> intval($a['priority']);
    });

    return $clean;
  }

  public static function for_js() {
    $list = [];
    foreach (self::get_rules() as $r) {
      $list[] = [
        'type'      => $r['type'],
        'percent'   => floatval($r['percent']),
        'label'     => $r['label'],
        'priority'  => intval($r['priority']),
        'weekday'   => intval($r['weekday']),
        'startDate' => $r['start_date'],
        'endDate'   => $r['end_date'],
        'desc'      => $r['desc'],
        'groups'    => isset($r['groups']) && is_array($r['groups']) ? array_values($r['groups']) : [],
        'active'    => !empty($r['active']),
        'showResumo'=> !empty($r['show_resumo']),
        'showPdf'   => !empty($r['show_pdf']),
      ];
    }
    return $list;
  }
}

BVGN_DynamicTariffs::init();
