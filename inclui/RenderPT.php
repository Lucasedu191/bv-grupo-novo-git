<?php
if (!defined('ABSPATH')) exit;

// Auxiliares (poderemos expandir depois).
class BVGN_RenderPT {
  public static function tipo_por_categoria($produto_id){
    if (!$produto_id) return 'diario';
    if (taxonomy_exists('product_cat')){
      if (has_term('aluguel-de-carros-mensal', 'product_cat', $produto_id)) return 'mensal';
      if (has_term('aluguel-de-carros-diaria', 'product_cat', $produto_id)) return 'diario';
    }
    return 'diario';
  }
}
