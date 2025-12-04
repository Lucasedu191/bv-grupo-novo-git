<?php
/**
 * Plugin Name: BV Grupo Novo (Produto Paralelo)
 * Description: Página de produto paralela com shortcodes modulares (Diário/Mensal), taxas, agendamento, totais e cotação (HTML/PDF + WhatsApp).
 * Version: 9.9.88
 * Author: Lucas
 * Update URI: https://github.com/Lucasedu191/bv-grupo-novo-git
 */

if (!defined('ABSPATH')) exit;

// Número fixo da BV para receber cotações (fallback global)
if (!defined('BVGN_WHATS_DESTINO')) {
  // só dígitos (DDI+DDD+número)
  define('BVGN_WHATS_DESTINO', '5595981022395');
}
// --- Constantes seguras (fallback) ---
// Caminhos/URLs do plugin (não dependem de nada externo)
if (!defined('BVGN_DIR')) define('BVGN_DIR', trailingslashit(plugin_dir_path(__FILE__)));
if (!defined('BVGN_URL')) define('BVGN_URL', trailingslashit(plugin_dir_url(__FILE__)));

// Mantém compat com nomes antigos usados no código
if (!defined('BVGN_CAMINHO'))        define('BVGN_CAMINHO', BVGN_DIR);
//if (!defined('BVGN_DIR_ARQUIVOS'))   define('BVGN_DIR_ARQUIVOS', BVGN_DIR.'arquivos/');
if (!defined('BVGN_USAR_TEMPLATE'))  define('BVGN_USAR_TEMPLATE', false);

// === Carrega PUC (Composer OU pasta embutida) ===
$puccComposer = __DIR__ . '/vendor/autoload.php';
$puccEmbedded = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puccComposer)) {
    require $puccComposer; // precisa ter instalado yahnis-elsts/plugin-update-checker
} elseif (file_exists($puccEmbedded)) {
    require $puccEmbedded; // lib copiada dentro do plugin
} else {
    error_log('[BVGN] Plugin Update Checker não encontrado; updates automáticos desativados.');
}

// === Descobre qual namespace da lib está disponível (v5 ou v5p6) ===
$factory = null;
if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $factory = '\YahnisElsts\PluginUpdateChecker\v5\PucFactory';
} elseif (class_exists('\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory')) {
    $factory = '\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory';
}

// === Configura o update checker (se a lib existir) ===
if ($factory) {
    $bvgn_update = $factory::buildUpdateChecker(
        'https://github.com/Lucasedu191/bv-grupo-novo-git',
        __FILE__,
        'bv-grupo-novo' // mantenha igual ao nome da pasta do plugin
    );

    // Branch padrão (se o repo usa "main")
    if (method_exists($bvgn_update, 'setBranch')) {
        $bvgn_update->setBranch('main');
    }

    // Usar assets anexados na Release (substitui o inexistente setReleaseAsset)
    $api = $bvgn_update->getVcsApi();
    if ($api && method_exists($api, 'enableReleaseAssets')) {
        $api->enableReleaseAssets();
    }

    // Se repo for privado:
    // $bvgn_update->setAuthentication('ghp_xxx...');
}
//fim do PUC
if (!defined('ABSPATH')) exit;

// Constantes de caminho/URL (evita redefinir caso outro arquivo tenha setado)
if (!defined('BVGN_CAMINHO'))      define('BVGN_CAMINHO', plugin_dir_path(__FILE__));
if (!defined('BVGN_URL'))          define('BVGN_URL', plugin_dir_url(__FILE__));
if (!defined('BVGN_DIR_ARQUIVOS')) define('BVGN_DIR_ARQUIVOS', WP_CONTENT_DIR.'/uploads/grupo-novo');
if (!defined('BVGN_URL_ARQUIVOS')) define('BVGN_URL_ARQUIVOS', content_url('uploads/grupo-novo'));

// Toggle opcional (template do plugin). Mantenha 'false' para usar Elementor.
if (!defined('BVGN_USAR_TEMPLATE')) define('BVGN_USAR_TEMPLATE', false);

require_once BVGN_CAMINHO.'inclui/ShortcodesPT.php';
require_once BVGN_CAMINHO.'inclui/RenderPT.php';
require_once BVGN_CAMINHO.'inclui/GerarArquivoEndpoint.php';
require_once BVGN_CAMINHO.'inclui/IntegracoesPT.php';
require_once BVGN_CAMINHO.'inclui/ExportacaoCSV.php';
require_once BVGN_CAMINHO.'inclui/DynamicTariffs.php';

// === Admin: Registro CPT "Cotações" e colunas ===
add_action('init', function(){
  register_post_type('bvgn_cotacao', [
    'labels' => [
      'name'               => 'Cotações',
      'singular_name'      => 'Cotação',
      'menu_name'          => 'Cotações',
      'add_new'            => 'Adicionar nova',
      'add_new_item'       => 'Adicionar cotação',
      'edit_item'          => 'Editar cotação',
      'new_item'           => 'Nova cotação',
      'view_item'          => 'Ver cotação',
      'search_items'       => 'Buscar cotações',
      'not_found'          => 'Nenhuma cotação encontrada',
      'not_found_in_trash' => 'Nenhuma cotação na lixeira',
    ],
    'public'            => false,
    'show_ui'           => true,
    'show_in_menu'      => true,
    'show_in_admin_bar' => false,
    'menu_icon'         => 'dashicons-media-document',
    'supports'          => ['title'],
    'capability_type'   => 'post',
    'map_meta_cap'      => true,
  ]);
});

// Bloqueia a tela de edição para o CPT, mantendo apenas listagem/exports
// Removido o bloqueio de edição/redirect para evitar impacto no acesso ao admin

// Remove ações de linha (Quick Edit/Lixeira) e ações em massa
add_filter('post_row_actions', function($actions, $post){
  if ($post->post_type === 'bvgn_cotacao') {
    unset($actions['inline hide-if-no-js']); // Quick Edit
    unset($actions['trash']);
    // Remover ações de duplicar/clonar adicionadas por plugins de terceiros
    foreach ($actions as $key => $label) {
      $txt = is_string($label) ? wp_strip_all_tags($label) : '';
      if (
        stripos($key, 'duplicate') !== false || stripos($key, 'clone') !== false ||
        stripos($txt, 'duplicar') !== false || stripos($txt, 'duplicate') !== false ||
        stripos($txt, 'clonar') !== false || stripos($txt, 'clone') !== false ||
        stripos($txt, 'copiar') !== false || stripos($txt, 'copy') !== false
      ) {
        unset($actions[$key]);
      }
    }
  }
  return $actions;
}, 99, 2);

// Bloqueia a deleção de cotações via capabilities, mesmo por URL
add_filter('map_meta_cap', function($caps, $cap, $user_id, $args){
  if ($cap === 'delete_post') {
    $post_id = isset($args[0]) ? intval($args[0]) : 0;
    if ($post_id && get_post_type($post_id) === 'bvgn_cotacao') {
      return ['do_not_allow'];
    }
  }
  return $caps;
}, 10, 4);

// Corrige hook: usa hífen, não sublinhado
add_filter('bulk_actions-edit-bvgn_cotacao', function($bulk_actions){
  unset($bulk_actions['edit']);
  unset($bulk_actions['trash']);
  // Remover ações de duplicar/clonar de plugins de terceiros
  foreach ($bulk_actions as $key => $label) {
    $txt = is_string($label) ? wp_strip_all_tags($label) : '';
    if (
      stripos($key, 'duplicate') !== false || stripos($key, 'clone') !== false ||
      stripos($txt, 'duplicar') !== false || stripos($txt, 'duplicate') !== false ||
      stripos($txt, 'clonar') !== false || stripos($txt, 'clone') !== false ||
      stripos($txt, 'copiar') !== false || stripos($txt, 'copy') !== false
    ) {
      unset($bulk_actions[$key]);
    }
  }
  return $bulk_actions;
});

// Garante ordenação: mais recentes primeiro
add_action('pre_get_posts', function($q){
  if (is_admin() && $q->is_main_query() && $q->get('post_type') === 'bvgn_cotacao') {
    $q->set('orderby', 'date');
    $q->set('order', 'DESC');
  }
});

// Colunas personalizadas da lista
add_filter('manage_bvgn_cotacao_posts_columns', function($columns){
  $novo = [
    'cb'      => $columns['cb'] ?? '<input type="checkbox" />',
    'title'   => 'Título',
    'codigo'  => 'Código',
    'tipo'    => 'Tipo',
    'produto' => 'Produto',
    'cliente' => 'Cliente',
    'whats'   => 'WhatsApp',
    'periodo' => 'Período',
    'total'   => 'Total',
    'pdf'     => 'PDF',
    'date'    => 'Data',
  ];
  return $novo;
});

add_action('manage_bvgn_cotacao_posts_custom_column', function($col, $post_id){
  switch($col){
    case 'codigo':
      $cod = get_post_meta($post_id, 'codigo', true);
      echo $cod !== '' ? esc_html($cod) : '-';
      break;
    case 'tipo':
      $tipo = strtolower((string) get_post_meta($post_id, 'totais_tipo', true));
      if ($tipo === 'mensal') {
        echo 'Mensal';
      } elseif ($tipo === 'diario') {
        echo 'Diária';
      } else {
        echo '-';
      }
      break;
    case 'produto':
      $pid = intval(get_post_meta($post_id, 'produto_id', true));
      echo $pid ? esc_html(get_the_title($pid)) : '-';
      break;
    case 'cliente':
      echo esc_html(get_post_meta($post_id, 'cliente_nome', true));
      break;
    case 'whats':
      echo esc_html(get_post_meta($post_id, 'cliente_whats', true));
      break;
    case 'periodo':
      $i = get_post_meta($post_id, 'datas_inicio', true);
      $f = get_post_meta($post_id, 'datas_fim', true);
      echo esc_html(trim(($i ?: '—').' a '.($f ?: '—')));
      break;
    case 'total':
      $t = get_post_meta($post_id, 'totais_total', true);
      if ($t !== '') {
        // formata em BRL se possível
        if (function_exists('wc_price')) {
          echo wp_kses_post(wc_price((float)$t));
        } else {
          echo 'R$ ' . number_format((float)$t, 2, ',', '.');
        }
      } else {
        echo '-';
      }
      break;
    case 'pdf':
      $url = esc_url(get_post_meta($post_id, 'pdf_url', true));
      if ($url) {
        echo '<a href="'.$url.'" target="_blank">Abrir PDF</a>';
      } else {
        echo '-';
      }
      break;
  }
}, 10, 2);

add_action('init', function(){
  if (!file_exists(BVGN_DIR_ARQUIVOS)) wp_mkdir_p(BVGN_DIR_ARQUIVOS);
});

add_action('wp_enqueue_scripts', function () {

  // Cabeçalho de agendamento
    wp_enqueue_style(
      'bvgn-cabecalho',
      BVGN_URL . 'assets/css/cabecalho.css',
      [],
      filemtime(BVGN_DIR . 'assets/css/cabecalho.css')
    );

    wp_enqueue_style(
      'bvgn-cotacao',
      BVGN_URL . 'assets/css/cotacao.css',
      [],
      filemtime(BVGN_DIR . 'assets/css/cotacao.css')
    );

    wp_enqueue_script(
      'bvgn-cabecalho',
      BVGN_URL . 'assets/js/cabecalho.js',
      [],
      filemtime(BVGN_DIR . 'assets/js/cabecalho.js'),
      true
    );
    wp_enqueue_script(
      'flatpickr-lang-pt',
      'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js',
      ['flatpickr-js'],
      null,
      true
    );
    wp_enqueue_script(
      'bvgn-botao-fake',
      BVGN_URL . 'assets/js/ajuste-botao-selecao.js',
      [],
      file_exists(BVGN_CAMINHO . 'assets/js/ajuste-botao-selecao.js')
        ? filemtime(BVGN_CAMINHO . 'assets/js/ajuste-botao-selecao.js')
        : '1.0.0',
      true
    );
  // CSS do Flatpickr calendário cabecalho
    wp_enqueue_style(
      'flatpickr-css',
      'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
      [],
      null
    );

    // JS do Flatpickr
    wp_enqueue_script(
      'flatpickr-js',
      'https://cdn.jsdelivr.net/npm/flatpickr',
      [],
      null,
      true
    );
    // Inicialização do Flatpickr no agendamento da página do produto
    wp_enqueue_script(
      'bvgn-agendamento-flatpickr',
      BVGN_URL . 'assets/js/agendamento-flatpickr.js',
      ['flatpickr-js'],
      file_exists(BVGN_DIR . 'assets/js/agendamento-flatpickr.js') ? filemtime(BVGN_DIR . 'assets/js/agendamento-flatpickr.js') : '1.0.0',
      true
    );
    // fim CSS do Flatpickr calendário cabecalho

    // chamada do arquivo de script descricao grupo mensal
    wp_enqueue_script(
      'bvgn-mensal-descricao',
      BVGN_URL . 'assets/js/mensal-descricao.js',
      ['jquery'],
      filemtime(BVGN_CAMINHO . 'assets/js/mensal-descricao.js'),
      true
    );
    // fim chamada do arquivo de script descricao grupo mensal

  if ( ! is_singular() ) return;

  // segurança: post pode ser null em alguns contextos
  global $post;
  $has_post  = ( $post && isset($post->post_content) );

  $tem_grupo = $has_post && has_shortcode($post->post_content, 'grupo_novo');
  $tem_popup = $has_post && has_shortcode($post->post_content, 'gn_botao_cotacao_popup');
  $e_produto = function_exists('is_product') && is_product();

  if ( $tem_grupo || $tem_popup || $e_produto ) {
    // ===== CSS: enfileirar cada arquivo (sem @import)
    $css_url = trailingslashit(BVGN_URL) . 'assets/css/';
    $css_dir = trailingslashit(BVGN_DIR) . 'assets/css/';

    $ver = function($file) use ($css_dir){
      $p = $css_dir . $file;
      return file_exists($p) ? filemtime($p) : '1.0.0';
    };

    // Base primeiro
    wp_enqueue_style('bvgn-base',        $css_url.'base.css',        [],                     $ver('base.css'));
    // Blocos
    wp_enqueue_style('bvgn-variacoes',   $css_url.'variacoes.css',   ['bvgn-base'],          $ver('variacoes.css'));
    wp_enqueue_style('bvgn-taxas',       $css_url.'taxas.css',       ['bvgn-base'],          $ver('taxas.css'));
    wp_enqueue_style('bvgn-agendamento', $css_url.'agendamento.css', ['bvgn-base'],          $ver('agendamento.css'));
    wp_enqueue_style('bvgn-totais',      $css_url.'totais.css',      ['bvgn-base'],          $ver('totais.css'));
    wp_enqueue_style('bvgn-modal-css',   $css_url.'modal.css',       ['bvgn-base'],          $ver('modal.css'));
    wp_enqueue_style('bvgn-cotacao',     $css_url.'cotacao.css',     ['bvgn-base'],          $ver('cotacao.css'));


    // Opcional: CSS geral (sem @import) por último
    wp_enqueue_style(
      'bvgn-css',
      $css_url.'grupo-novo.css',
      ['bvgn-base','bvgn-variacoes','bvgn-taxas','bvgn-agendamento','bvgn-totais','bvgn-modal-css'],
      $ver('grupo-novo.css')
    );

    // ===== JS principal (inalterado)
    wp_enqueue_script(
      'bvgn-dynamic',
      BVGN_URL . 'assets/js/bvgn-dynamic.js',
      [],
      file_exists(BVGN_DIR.'assets/js/bvgn-dynamic.js') ? filemtime(BVGN_DIR.'assets/js/bvgn-dynamic.js') : '1.0.0',
      true
    );

    $ver_js    = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/grupo-novo.js') )
                 ? filemtime(BVGN_DIR.'assets/js/grupo-novo.js')    : '1.0.0';
    $ver_modal = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/bvgn-modal.js') )
                 ? filemtime(BVGN_DIR.'assets/js/bvgn-modal.js')    : '1.0.0';

    
    // JS principal
    wp_enqueue_script(
      'bvgn-js',
      BVGN_URL . 'assets/js/grupo-novo.js',
      ['jquery','bvgn-dynamic'],
      $ver_js,
      true
    );

    // === [NOVO] Versão modular em 3 arquivos ===
    // $ver_core   = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/bvgn-core.js') )
    //               ? filemtime(BVGN_DIR.'assets/js/bvgn-core.js')   : '1.0.0';
    // $ver_calc   = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/bvgn-calc.js') )
    //               ? filemtime(BVGN_DIR.'assets/js/bvgn-calc.js')   : '1.0.0';
    // $ver_events = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/bvgn-events.js') )
    //               ? filemtime(BVGN_DIR.'assets/js/bvgn-events.js') : '1.0.0';

    // wp_enqueue_script(
    //   'bvgn-core',
    //   BVGN_URL . 'assets/js/bvgn-core.js',
    //   ['jquery'],
    //   $ver_core,
    //   true
    // );

    // wp_enqueue_script(
    //   'bvgn-calc',
    //   BVGN_URL . 'assets/js/bvgn-calc.js',
    //   ['jquery','bvgn-core'],
    //   $ver_calc,
    //   true
    // );

    // wp_enqueue_script(
    //   'bvgn-events',
    //   BVGN_URL . 'assets/js/bvgn-events.js',
    //   ['jquery','bvgn-core','bvgn-calc'],
    //   $ver_events,
    //   true
    // );



    // Proteção JS (só quando tem [gn_protecao] no conteúdo)
    if ( $has_post && has_shortcode($post->post_content, 'gn_protecao') ) {
      $ver_protecao = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/protecao.js') )
                        ? filemtime(BVGN_DIR.'assets/js/protecao.js') : '1.0.0';

      wp_enqueue_script(
        'bvgn-protecao',
        BVGN_URL . 'assets/js/protecao.js',
        ['jquery'],
        $ver_protecao,
        true
      );
    }

    // Modal JS (só quando precisa)
    if ( $tem_popup || $e_produto ) {
      wp_enqueue_script(
        'bvgn-modal',
        BVGN_URL . 'assets/js/bvgn-modal.js',
        [],
        $ver_modal,
        true
      );
    }
    

    // Variáveis para o JS
    wp_localize_script('bvgn-js', 'BVGN', [
      'ajaxUrl'      => admin_url('admin-ajax.php'),
      'nonce'        => wp_create_nonce('bvgn_nonce'),
      'whatsDestino' => BVGN_WHATS_DESTINO, // <- fallback global lido no JS
      'dynamicTariffs' => class_exists('BVGN_DynamicTariffs') ? BVGN_DynamicTariffs::for_js() : [],
    ]);

   
  }
   
},99);


// Template do plugin (desligado por padrão)
add_filter('template_include', function($template){
  if (BVGN_USAR_TEMPLATE && function_exists('is_product') && is_product()) {
    $custom = BVGN_CAMINHO.'modelos/single-grupo-novo.php';
    if (file_exists($custom)) return $custom;
  }
  return $template;
});
