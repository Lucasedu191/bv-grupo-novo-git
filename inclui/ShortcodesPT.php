<?php
if (!defined('ABSPATH')) exit;

class BVGN_ShortcodesPT {
  public static function init(){
    add_shortcode('grupo_novo', [__CLASS__,'envolver']);
    add_shortcode('gn_imagem', [__CLASS__,'imagem']);
    add_shortcode('gn_titulo', [__CLASS__,'titulo']);
    add_shortcode('gn_descricao', [__CLASS__,'descricao']);
    add_shortcode('gn_variacoes', [__CLASS__,'variacoes']);      // type="auto|diario|mensal"
    add_shortcode('gn_agendamento', [__CLASS__,'agendamento']);  // type="auto|diario|mensal"
    add_shortcode('gn_taxas', [__CLASS__,'taxas']);
    add_shortcode('gn_protecao', [__CLASS__, 'protecao']);
    add_shortcode('gn_totais', [__CLASS__,'totais']);            // type="auto|diario|mensal"
    add_shortcode('gn_informacoes', [__CLASS__,'informacoes']);  // textarea
    add_shortcode('gn_botao_cotacao', [__CLASS__,'botao_cotacao']); // format="html|pdf" phone="+55..."
    add_shortcode('gn_botao_cotacao_popup', [__CLASS__,'botao_cotacao_popup']); // botão + modal
    add_shortcode('bvgn_agendamento_header', [__CLASS__, 'agendamento_header']); // calendario no cabeçalho
  }

  private static function resolver_produto_id($atts){
    $a = shortcode_atts(['produto_id'=>0], $atts);
    if (empty($a['produto_id'])) {
      if (!empty($_GET['produto'])) {
        $a['produto_id'] = intval($_GET['produto']);
      } else {
        if (function_exists('is_product') && is_product()) {
          $a['produto_id'] = get_the_ID();
        }
      }
    }
    return $a;
  }

  private static function resolver_tipo($produto_id, $tipo_attr){
    if (in_array($tipo_attr, ['diario','mensal'])) return $tipo_attr;
    // auto por categoria
    if ($produto_id && taxonomy_exists('product_cat')) {
      if (has_term('aluguel-de-carros-mensal', 'product_cat', $produto_id)) return 'mensal';
      if (has_term('aluguel-de-carros-diaria', 'product_cat', $produto_id)) return 'diario';
    }
    return 'diario'; // padrão
  }

  public static function envolver($atts, $conteudo=''){
    $a = self::resolver_produto_id($atts);
    return '<div class="bvgn-container" data-produto-id="'.esc_attr($a['produto_id']).'">'.do_shortcode($conteudo).'</div>';
  }

  public static function imagem($atts){
    $a = self::resolver_produto_id($atts);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/imagem.php'; return ob_get_clean();
  }

  public static function titulo($atts){
    $a = self::resolver_produto_id($atts);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/titulo.php'; return ob_get_clean();
  }

  public static function descricao($atts){
    $a = self::resolver_produto_id($atts);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/descricao.php'; return ob_get_clean();
  }

  public static function variacoes($atts){
    $atts = shortcode_atts(['produto_id'=>0,'type'=>'auto'], $atts);
    $a = self::resolver_produto_id($atts);
    $a['type'] = self::resolver_tipo($a['produto_id'], $atts['type']);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/variacoes.php'; return ob_get_clean();
  }

  public static function taxas($atts){
    $a = self::resolver_produto_id($atts);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/taxas.php'; return ob_get_clean();
  }
  
  public static function protecao($atts){
  $a = self::resolver_produto_id($atts);
  $a['type'] = self::resolver_tipo($a['produto_id'], $atts['type'] ?? 'auto');

  if ($a['type'] !== 'diario') return ''; // só para produtos do tipo "diario"

  ob_start();
  $GLOBALS['a'] = $a;
  include BVGN_CAMINHO . 'modelos/partes/taxa-variavel-diaria.php';
  return ob_get_clean();
}

  public static function agendamento($atts){
    $atts = shortcode_atts(['produto_id'=>0,'type'=>'auto'], $atts);
    $a = self::resolver_produto_id($atts);
    $a['type'] = self::resolver_tipo($a['produto_id'], $atts['type']);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/agendamento.php'; return ob_get_clean();
  }

  public static function totais($atts){
    $atts = shortcode_atts(['produto_id'=>0,'type'=>'auto'], $atts);
    $a = self::resolver_produto_id($atts);
    $a['type'] = self::resolver_tipo($a['produto_id'], $atts['type']);
    ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/totais.php'; return ob_get_clean();
  }

  public static function informacoes($atts){
    $a = shortcode_atts(['placeholder'=>'Informacoes adicionais…'], $atts);
    return '<textarea class="bvgn-informacoes" rows="4" placeholder="'.esc_attr($a['placeholder']).'"></textarea>';
  }

  public static function botao_cotacao($atts){
      $a = self::resolver_produto_id($atts);
      $atts = shortcode_atts(['produto_id'=>0,'format'=>'html','phone'=>''], $atts);
      $a['format'] = $atts['format'];
      $a['phone']  = $atts['phone'];
      ob_start(); $GLOBALS['a']=$a; include BVGN_CAMINHO.'modelos/partes/botao-cotacao.php'; return ob_get_clean();
    }
    public static function botao_cotacao_popup($atts){
    // permite passar produto_id explicitamente, rótulo e classe do botão
    $atts = shortcode_atts([
      'produto_id' => 0,
      'rotulo'     => 'Gerar cotação',
      'classe'     => 'bvgn-btn bvgn-btn--primario',
    ], $atts);

    // resolve produto_id como nos demais
    $a = self::resolver_produto_id($atts);

    // props do botão/modal
    $a['rotulo'] = $atts['rotulo'];
    $a['classe'] = $atts['classe'];
    $a['produto_titulo'] = !empty($a['produto_id']) ? get_the_title($a['produto_id']) : '';
    $a['nonce']  = wp_create_nonce('bvgn_cotacao_modal');

    // render: botão que inclui o modal (template novo)
    ob_start();
    $GLOBALS['a'] = $a;
    include BVGN_CAMINHO.'modelos/partes/botao-cotacao-popup.php';
    return ob_get_clean();
  }
  // agendamento no cabeçalho (shortcode)
public static function agendamento_header($atts = [], $content = '') {
  ob_start();
  include BVGN_CAMINHO . 'inclui/cabecalho-agendamento.php';
  return ob_get_clean();
}
}
BVGN_ShortcodesPT::init();
