<?php
/**
 * Plugin Name: BV Grupo Novo (Produto Paralelo)
 * Description: Página de produto paralela com shortcodes modulares (Diário/Mensal), taxas, agendamento, totais e cotação (HTML/PDF + WhatsApp).
 * Version: 9.1.5
 * Author: Lucas
 * Update URI: https://github.com/Lucasedu191/bv-grupo-novo-git
 */

if (!defined('ABSPATH')) exit;

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

define('BVGN_CAMINHO', plugin_dir_path(__FILE__));
define('BVGN_URL', plugin_dir_url(__FILE__));
define('BVGN_DIR_ARQUIVOS', WP_CONTENT_DIR.'/uploads/grupo-novo');
define('BVGN_URL_ARQUIVOS', content_url('uploads/grupo-novo'));

// Toggle opcional (template do plugin). Mantenha 'false' para usar Elementor.
if (!defined('BVGN_USAR_TEMPLATE')) define('BVGN_USAR_TEMPLATE', false);

require_once BVGN_CAMINHO.'inclui/ShortcodesPT.php';
require_once BVGN_CAMINHO.'inclui/RenderPT.php';
require_once BVGN_CAMINHO.'inclui/GerarArquivoEndpoint.php';
require_once BVGN_CAMINHO.'inclui/IntegracoesPT.php';

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
      filemtime(BVGN_CAMINHO . 'assets/js/botao-fake.js'),
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
    $ver_js    = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/grupo-novo.js') )
                 ? filemtime(BVGN_DIR.'assets/js/grupo-novo.js')    : '1.0.0';
    $ver_modal = ( defined('BVGN_DIR') && file_exists(BVGN_DIR.'assets/js/bvgn-modal.js') )
                 ? filemtime(BVGN_DIR.'assets/js/bvgn-modal.js')    : '1.0.0';

    
    // JS principal
    wp_enqueue_script(
      'bvgn-js',
      BVGN_URL . 'assets/js/grupo-novo.js',
      ['jquery'],
      $ver_js,
      true
    );

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
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce('bvgn_nonce'),
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
