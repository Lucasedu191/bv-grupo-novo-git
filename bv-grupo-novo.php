<?php
/**
 * Plugin Name: BV Grupo Novo (Produto Paralelo)
 * Description: Página de produto paralela com shortcodes modulares (Diário/Mensal), taxas, agendamento, totais e cotação (HTML/PDF + WhatsApp).
 * Version: 7.0.4
 * Author: Lucas
 * Update URI: https://github.com/Lucasedu191/bv-grupo-novo-git
 */

if (!defined('ABSPATH')) exit;

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

add_action('wp_enqueue_scripts', function(){
  if ( ! is_singular() ) return;

  global $post;
  $tem_grupo   = ( $post && has_shortcode($post->post_content, 'grupo_novo') );
  $tem_popup   = ( $post && has_shortcode($post->post_content, 'gn_botao_cotacao_popup') );
  $e_produto   = function_exists('is_product') && is_product();

  if ( $tem_grupo || $tem_popup || $e_produto ) {
    wp_enqueue_style('bvgn-css', BVGN_URL.'assets/css/grupo-novo.css', [], '0.3.1');
    wp_enqueue_script('bvgn-js',  BVGN_URL.'assets/js/grupo-novo.js', ['jquery'], '0.3.1', true);

    // carrega o JS do modal apenas quando o shortcode do popup estiver presente ou em páginas de produto
    if ( $tem_popup || $e_produto ) {
      wp_enqueue_script('bvgn-modal', BVGN_URL.'assets/js/bvgn-modal.js', [], '0.3.1', true);
    }

    wp_localize_script('bvgn-js','BVGN',[
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce('bvgn_nonce')
    ]);
  }
});


// Template do plugin (desligado por padrão)
add_filter('template_include', function($template){
  if (BVGN_USAR_TEMPLATE && function_exists('is_product') && is_product()) {
    $custom = BVGN_CAMINHO.'modelos/single-grupo-novo.php';
    if (file_exists($custom)) return $custom;
  }
  return $template;
});
