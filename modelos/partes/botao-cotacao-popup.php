<?php
/**
 * Botão que abre o modal de cotação.
 * Usa $GLOBALS['a'] com: produto_id, produto_titulo, rotulo, classe, nonce
 */

// Define $a com fallback
$a = isset($GLOBALS['a']) ? $GLOBALS['a'] : [];

// Define variáveis locais com valores padrão
$rotulo         = isset($a['rotulo']) ? $a['rotulo'] : 'Gerar cotação';
$classe         = isset($a['classe']) ? $a['classe'] : 'bvgn-btn bvgn-btn--primario';
$produto_id     = isset($a['produto_id']) ? (int)$a['produto_id'] : get_the_ID();
$produto_titulo = isset($a['produto_titulo']) ? $a['produto_titulo'] : get_the_title();
$nonce          = isset($a['nonce']) ? $a['nonce'] : wp_create_nonce('bvgn_nonce');
?>

<!-- Botão que abre o modal -->
<div class="bvgn-cotacao">
  <button type="button"
          class="<?php echo esc_attr($classe); ?> js-bvgn-open-modal"
          data-bvgn-modal="bvgn-cotacao-modal"
          data-formato="html">
    <?php echo esc_html($rotulo); ?>
  </button>
</div>

<?php
// Inclui o modal apenas uma vez por página
if ( ! defined('BVGN_MODAL_INCLUDED') ) {
  define('BVGN_MODAL_INCLUDED', true);

  $modal_data = [
    'produto_id'     => $produto_id,
    'produto_titulo' => $produto_titulo,
    'nonce'          => $nonce,
  ];

  include BVGN_CAMINHO . 'modelos/partes/modal-cotacao.php';
}
?>
