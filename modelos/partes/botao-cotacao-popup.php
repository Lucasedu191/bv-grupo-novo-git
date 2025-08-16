<?php
/**
 * Botão que abre o modal de cotação.
 * Usa $GLOBALS['a'] com: produto_id, produto_titulo, rotulo, classe, nonce
 */
$a = isset($GLOBALS['a']) ? $GLOBALS['a'] : [];
$rotulo         = isset($a['rotulo']) ? $a['rotulo'] : 'Gerar cotação';
$classe         = isset($a['classe']) ? $a['classe'] : 'bvgn-btn bvgn-btn--primario';
$produto_id     = isset($a['produto_id']) ? (int)$a['produto_id'] : 0;
$produto_titulo = isset($a['produto_titulo']) ? $a['produto_titulo'] : '';
$nonce          = isset($a['nonce']) ? $a['nonce'] : '';
?>
<button type="button"
        class="<?php echo esc_attr($classe); ?> js-bvgn-open-modal"
        data-bvgn-modal="bvgn-cotacao-enviar">
  <?php echo esc_html($rotulo); ?>
</button>
<?php
// Inclui o modal apenas uma vez por página
if ( ! defined('BVGN_MODAL_INCLUDED') ) {
  define('BVGN_MODAL_INCLUDED', true);
  $modal_data = [
    'produto_id'     => $produto_id,
    'produto_titulo' => $produto_titulo,
    'nonce'          => $nonce,
  ];
  include BVGN_CAMINHO.'modelos/partes/modal-cotacao.php';
}
