<?php
/**
 * Plugin Name: BV Grupo Novo (Produto Paralelo)
 * Description: Página de produto paralela com shortcodes modulares (Diário/Mensal), taxas, agendamento, totais e cotação (HTML/PDF + WhatsApp).
 * Version: 7.0.0
 * Author: Lucas
 */
require __DIR__ . '/vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// ===== Atualizador via GitHub =====
$bvgn_update = PucFactory::buildUpdateChecker(
  'https://github.com/SEU-USUARIO/bv-grupo-novo', // URL do seu repositório
  __FILE__,                                        // arquivo principal do plugin
  'bv-grupo-novo'                                  // slug único do plugin
);

// Se usa Releases do GitHub, ative:
$bvgn_update->setReleaseAsset(true);

// Se o branch padrão for "main" (provável), garanta isso:
$bvgn_update->setBranch('main');
// Forçar auto-update só deste plugin:
add_filter('auto_update_plugin', function($update, $item){
  return (isset($item->slug) && $item->slug === 'bv-grupo-novo') ? true : $update;
}, 10, 2);

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
