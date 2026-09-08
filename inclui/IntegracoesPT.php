<?php
if (!defined('ABSPATH')) exit;

class BVGN_IntegracoesPT {
  public static function obter_taxas_para_produto($produto_id){
    $taxas = [];

    // Integração com YITH WAPO (mapear conforme sua loja)
    if (defined('YITH_WAPO')) {
      // TODO: mapear opções -> $taxas[] = ['rotulo'=>'...', 'preco'=>xx, 'icone'=>'...'];
    }

    // Fallback padrão com caminho completo do SVG relativo ao plugin
    $taxas[] = [
      'rotulo' => 'Cadeirinha 0–25 kg (diaria)',
      'preco'  => 22.90,
      'icone'  => 'assets/svg/passos01.svg'
    ];

    $taxas[] = [
      'rotulo' => 'Condutor adicional (diaria)',
      'preco'  => 24.90,
      'icone'  => 'assets/svg/passos02.svg'
    ];

    $taxas[] = [
      'rotulo' => 'Taxa de limpeza (obrigatória)',
      'preco'  => 48.90,
      'icone'  => 'assets/svg/passos03.svg' 
    ];

    return $taxas;
  }
}

