<?php
if (!defined('ABSPATH')) exit;

// Adiciona ação em massa para exportar selecionados (hook correto com hífen)
add_filter('bulk_actions-edit-bvgn_cotacao', function($bulk_actions){
  $bulk_actions['bvgn_export_selected'] = 'Exportar CSV (selecionados)';
  return $bulk_actions;
});

// Botão de exportar tudo na listagem
add_action('restrict_manage_posts', function($post_type){
  if ($post_type !== 'bvgn_cotacao') return;
  if (!current_user_can('edit_posts')) return;
  $url = wp_nonce_url(admin_url('edit.php?post_type=bvgn_cotacao&bvgn_export_all=csv'), 'bvgn_export_all');
  echo '<a href="'.esc_url($url).'" class="button" style="margin-left:8px;">Exportar CSV (Excel)</a>';
});

// Exportar tudo
add_action('admin_init', function(){
  if (!is_admin()) return;
  if (!isset($_GET['bvgn_export_all']) || $_GET['bvgn_export_all'] !== 'csv') return;
  if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bvgn_export_all')) return;
  if (!current_user_can('edit_posts')) return;

  $q = new WP_Query([
    'post_type'      => 'bvgn_cotacao',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
  ]);

  $headers = bvgn_exportacao_csv_headers();

  $filename = 'cotacoes-' . gmdate('Ymd-His') . '.csv';
  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);

  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, $headers, ';');

  if ($q->have_posts()) {
    foreach ($q->posts as $post_id) {
      fputcsv($out, bvgn_exportacao_csv_row($post_id), ';');
    }
  }

  fclose($out);
  exit;
});

// Exportar selecionados via Ações em massa
add_action('load-edit.php', function(){
  if (!is_admin()) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->id !== 'edit-bvgn_cotacao') return;

  $do = $_REQUEST['action'] ?? '';
  if ($do === '-1') $do = $_REQUEST['action2'] ?? '';
  if ($do !== 'bvgn_export_selected') return;

  if (!current_user_can('edit_posts')) return;
  check_admin_referer('bulk-posts');

  $ids = isset($_REQUEST['post']) ? array_map('intval', (array) $_REQUEST['post']) : [];
  $ids = array_values(array_filter($ids));
  if (!$ids) return;

  $q = new WP_Query([
    'post_type'      => 'bvgn_cotacao',
    'post__in'       => $ids,
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
  ]);

  $headers = bvgn_exportacao_csv_headers();
  $filename = 'cotacoes-selecionadas-' . gmdate('Ymd-His') . '.csv';
  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);

  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, $headers, ';');

  if ($q->have_posts()) {
    foreach ($q->posts as $post_id) {
      fputcsv($out, bvgn_exportacao_csv_row($post_id), ';');
    }
  }

  fclose($out);
  exit;
});

// Cabeçalhos do CSV (em função para fácil reuso/extensão)
function bvgn_exportacao_csv_headers(){
  return [
    'ID', 'Data', 'Titulo', 'Codigo', 'Tipo', 'Produto', 'Produto ID',
    'Cliente Nome', 'Cliente WhatsApp', 'Inicio', 'Fim',
    'Totais Base', 'Totais Taxas', 'Totais Tarifa Dinamica', 'Totais Qtd', 'Totais Subtotal', 'Totais Total',
    'Local Retirada', 'Mensagem', 'PDF URL'
  ];
}

// Monta uma linha do CSV a partir do post_id
function bvgn_exportacao_csv_row($post_id){
  $produto_id = intval(get_post_meta($post_id, 'produto_id', true));
  $row = [
    $post_id,
    get_the_date('Y-m-d H:i:s', $post_id),
    get_the_title($post_id),
    get_post_meta($post_id, 'codigo', true),
    get_post_meta($post_id, 'totais_tipo', true),
    $produto_id ? get_the_title($produto_id) : '',
    $produto_id ?: '',
    get_post_meta($post_id, 'cliente_nome', true),
    get_post_meta($post_id, 'cliente_whats', true),
    get_post_meta($post_id, 'datas_inicio', true),
    get_post_meta($post_id, 'datas_fim', true),
    get_post_meta($post_id, 'totais_base', true),
    get_post_meta($post_id, 'totais_taxas', true),
    get_post_meta($post_id, 'totais_dynamic_extra', true),
    get_post_meta($post_id, 'totais_qtd', true),
    get_post_meta($post_id, 'totais_subtotal', true),
    get_post_meta($post_id, 'totais_total', true),
    get_post_meta($post_id, 'local_retirada', true),
    get_post_meta($post_id, 'mensagem', true),
    get_post_meta($post_id, 'pdf_url', true),
  ];
  foreach ($row as &$v) {
    if (is_array($v) || is_object($v)) {
      $v = wp_json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
  }
  unset($v);
  return $row;
}
