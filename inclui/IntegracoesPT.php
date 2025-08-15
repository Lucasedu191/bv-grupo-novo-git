<?php
if (!defined('ABSPATH')) exit;

class BVGN_IntegracoesPT {
  public static function obter_taxas_para_produto($produto_id){
    $taxas = [];

    // Integração com YITH WAPO (mapear conforme sua loja)
    if (defined('YITH_WAPO')) {
      // TODO: mapear opções -> $taxas[] = ['rotulo'=>'...', 'preco'=>xx];
    }

    // Fallback padrão
    $taxas[] = ['rotulo'=>'Cadeirinha 0–25 kg (diaria)','preco'=>20.00];
    $taxas[] = ['rotulo'=>'Condutor adicional (diaria)','preco'=>20.00];
    $taxas[] = ['rotulo'=>'Taxa de limpeza','preco'=>45.00];

    return $taxas;
  }
}
