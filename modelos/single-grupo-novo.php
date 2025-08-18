<?php
/* Template de produto usando layout "grupo novo" (mantem a URL /product/slug/) */
if (!defined('ABSPATH')) exit;
get_header('shop');

global $product;
if (!$product && function_exists('wc_get_product')) {
  $product = wc_get_product(get_the_ID());
}
$pid = $product ? $product->get_id() : 0;
?>
<div class="bvgn-container" data-produto-id="<?php echo esc_attr($pid); ?>">
  <?php
  echo do_shortcode('[gn_imagem]');
  echo do_shortcode('[gn_titulo]');
  echo do_shortcode('[gn_descricao]');
  echo do_shortcode('[gn_agendamento]');      // auto por categoria
  echo do_shortcode('[gn_variacoes]');        // auto por categoria
  echo do_shortcode('[gn_taxas]');
  echo do_shortcode('[gn_totais]');           // auto por categoria
  echo do_shortcode('[gn_informacoes placeholder="Observacoes do cliente..."]');
  echo do_shortcode('[gn_botao_cotacao format="html" phone="+5541XXXXXXXX"]');
  ?>
</div>
<?php get_footer('shop'); ?>
