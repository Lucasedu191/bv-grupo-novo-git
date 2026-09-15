<?php
if (!defined('ABSPATH')) exit;

class BVGN_TariffHistoryAdmin {
  const SLUG = 'bvgn-historico-tarifas';

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu() {
    add_submenu_page('edit.php?post_type=bvgn_cotacao', 'Histórico de Tarifas', 'Histórico de Tarifas',
      'manage_options', self::SLUG, [__CLASS__, 'render']);
  }

  private static function input($key) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
  }

  public static function date($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    return $date && $date->format('Y-m-d') === $value ? $date : null;
  }

  private static function label($id) {
    $title = get_the_title($id);
    return ($title !== '' ? $title : 'Grupo removido') . ' (#' . absint($id) . ')';
  }

  private static function money($value) {
    return $value === null ? 'Indisponível' : 'R$ ' . number_format((float) $value, 2, ',', '.');
  }

  public static function render() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    BVGN_TariffHistoryRepository::install();
    if (get_option(BVGN_TariffHistoryRepository::OPTION) !== BVGN_TariffHistoryRepository::VERSION) {
      echo '<div class="notice notice-error"><p>Não foi possível criar a tabela de histórico. Verifique as permissões do banco de dados.</p></div>';
      return;
    }
    $group = absint(self::input('grupo_id'));
    $start_text = self::input('data_inicial');
    $end_text = self::input('data_final');
    $start = self::date($start_text);
    $end = self::date($end_text);
    $invalid = ($start_text !== '' && !$start) || ($end_text !== '' && !$end) || ($start && $end && $start > $end);
    $utc = new DateTimeZone('UTC');
    $result = $invalid ? ['rows' => [], 'total' => 0, 'page' => 1] : BVGN_TariffHistoryRepository::search(
      $group,
      $start ? $start->setTimezone($utc)->format('Y-m-d H:i:s') : '',
      $end ? $end->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s') : '',
      max(1, absint(self::input('pagina')))
    );
    $base = admin_url('edit.php?post_type=bvgn_cotacao&page=' . self::SLUG);
    $groups = BVGN_TariffHistoryRepository::groups();
    if ($group && !in_array((string) $group, array_map('strval', $groups), true)) $groups[] = $group;
    ?>
    <div class="wrap">
      <h1>Histórico de Tarifas</h1>
      <p>Totais-base para 1, 3, 7 e 15 dias (preço da faixa × quantidade de dias), sem taxas, proteção ou tarifa dinâmica.
        Datas no fuso horário do WordPress. Somente alterações registradas após a implantação.</p>
      <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>">
        <input type="hidden" name="post_type" value="bvgn_cotacao">
        <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
        <label for="bvgn-history-group">Grupo</label>
        <select id="bvgn-history-group" name="grupo_id">
          <option value="0">Todos os grupos</option>
          <?php foreach ($groups as $id): ?>
            <option value="<?php echo esc_attr($id); ?>" <?php selected($group, $id); ?>><?php echo esc_html(self::label($id)); ?></option>
          <?php endforeach; ?>
        </select>
        <label for="bvgn-history-start">Data inicial</label>
        <input id="bvgn-history-start" type="date" name="data_inicial" value="<?php echo esc_attr($start_text); ?>">
        <label for="bvgn-history-end">Data final</label>
        <input id="bvgn-history-end" type="date" name="data_final" value="<?php echo esc_attr($end_text); ?>">
        <button type="submit" class="button button-primary">Filtrar</button>
        <a class="button" href="<?php echo esc_url($base); ?>">Limpar filtros</a>
      </form>
      <?php if ($invalid): ?>
        <div class="notice notice-error inline"><p>Informe datas válidas, com a data inicial anterior ou igual à data final.</p></div>
      <?php endif; ?>
      <p><?php echo esc_html($result['total']); ?> registro(s). Valores alterados aparecem em negrito, com o valor anterior seguido de uma seta.</p>
      <table class="widefat striped">
        <thead><tr><th scope="col">Data/Hora</th><th scope="col">Grupo</th><th scope="col">1 diária</th><th scope="col">3 diárias</th><th scope="col">7 diárias</th><th scope="col">15 diárias</th><th scope="col">Usuário</th></tr></thead>
        <tbody>
        <?php if (!$result['rows']): ?>
          <tr><td colspan="7">Nenhum histórico encontrado para os filtros informados.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['rows'] as $row):
          // Previous snapshot is independent of date filters and pagination.
          $previous = BVGN_TariffHistoryRepository::latest($row['grupo_id'], $row['id']);
          $values = BVGN_TariffHistoryRepository::values($row);
          $old = $previous ? BVGN_TariffHistoryRepository::values($previous) : null;
          $user = $row['usuario_id'] ? get_userdata($row['usuario_id']) : false;
          $user_label = $user ? $user->display_name : ($row['usuario_id'] ? 'Usuário removido (#' . $row['usuario_id'] . ')' : 'Sistema / não identificado');
          ?>
          <tr>
            <td><?php echo esc_html(get_date_from_gmt($row['data_alteracao'], 'd/m/Y H:i:s')); ?></td>
            <td><?php echo esc_html(self::label($row['grupo_id'])); ?></td>
            <?php foreach (BVGN_TariffHistoryRepository::FIELDS as $field): ?>
              <td><?php if ($old !== null && $old[$field] !== $values[$field]): ?>
                <?php echo esc_html(self::money($old[$field])); ?> → <strong><?php echo esc_html(self::money($values[$field])); ?></strong>
              <?php else: echo esc_html(self::money($values[$field])); endif; ?></td>
            <?php endforeach; ?>
            <td><?php echo esc_html($user_label); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php
      $pages = (int) ceil($result['total'] / 50);
      if ($pages > 1) {
        $url = add_query_arg(['grupo_id' => $group, 'data_inicial' => $start_text, 'data_final' => $end_text], $base);
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(paginate_links([
          'base' => add_query_arg('pagina', '%#%', $url), 'format' => '',
          'current' => $result['page'], 'total' => $pages,
          'prev_text' => '« Anterior', 'next_text' => 'Próxima »',
        ]));
        echo '</div></div>';
      }
      ?>
    </div>
    <?php
  }
}

BVGN_TariffHistoryAdmin::init();
