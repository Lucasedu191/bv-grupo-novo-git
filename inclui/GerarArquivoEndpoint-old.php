<?php
if (!defined('ABSPATH')) exit;

class BVGN_GerarArquivoEndpoint {
  public static function init(){
    add_action('wp_ajax_bvgn_gerar_arquivo', [__CLASS__,'executar']);
    add_action('wp_ajax_nopriv_bvgn_gerar_arquivo', [__CLASS__,'executar']);
  }

  public static function executar(){
    check_ajax_referer('bvgn_nonce');

    $dados = [
      'produtoId' => intval($_POST['produtoId'] ?? 0),
      'informacoes' => wp_kses_post($_POST['informacoes'] ?? ''),
      'variacaoRotulo' => sanitize_text_field($_POST['variacaoRotulo'] ?? ''),
      'datas' => [
        'inicio' => sanitize_text_field($_POST['datas']['inicio'] ?? ''),
        'fim'    => sanitize_text_field($_POST['datas']['fim'] ?? '')
      ],
      'taxas' => array_map(function($t){
        return ['rotulo'=> sanitize_text_field($t['rotulo'] ?? ''), 'preco'=> floatval($t['preco'] ?? 0)];
      }, $_POST['taxas'] ?? []),
      'totais' => [
        'base'=> floatval($_POST['totais']['base'] ?? 0),
        'taxas'=> floatval($_POST['totais']['taxas'] ?? 0),
        'qtd' => intval($_POST['totais']['qtd'] ?? 1),
        'subtotal'=> floatval($_POST['totais']['subtotal'] ?? 0),
        'total'   => floatval($_POST['totais']['total'] ?? 0),
        'tipo'    => sanitize_text_field($_POST['totais']['tipo'] ?? 'diario'),
      ],
      'formato' => sanitize_text_field($_POST['formato'] ?? 'pdf') 
    ];

    $produto = function_exists('wc_get_product') ? wc_get_product($dados['produtoId']) : null;
    $titulo  = $produto ? $produto->get_name() : 'Produto';

    ob_start(); ?>
    <html><head><meta charset="utf-8"><title>Cotacao - <?php echo esc_html($titulo); ?></title></head>
    <body>
      <h1><?php echo esc_html($titulo); ?></h1>
      <p><strong>Tipo:</strong> <?php echo esc_html(strtoupper($dados['totais']['tipo'])); ?></p>
      <?php if($dados['datas']['inicio']): ?>
        <p><strong>Periodo:</strong> <?php echo esc_html($dados['datas']['inicio'].' → '.$dados['datas']['fim']); ?></p>
      <?php endif; ?>
      <p><strong>Variacao:</strong> <?php echo esc_html($dados['variacaoRotulo']); ?></p>
      <?php if(!empty($dados['taxas'])): ?>
        <h3>Taxas/Opcionais</h3>
        <ul>
          <?php foreach($dados['taxas'] as $t): ?>
            <li><?php echo esc_html($t['rotulo']); ?> — R$ <?php echo number_format($t['preco'],2,',','.'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <h3>Resumo</h3>
      <p>Base: R$ <?php echo number_format($dados['totais']['base'],2,',','.'); ?> × <?php echo intval($dados['totais']['qtd']); ?></p>
      <p>Subtotal: R$ <?php echo number_format($dados['totais']['subtotal'],2,',','.'); ?></p>
      <p>Taxas: R$ <?php echo number_format($dados['totais']['taxas'],2,',','.'); ?></p>
      <p><strong>Total: R$ <?php echo number_format($dados['totais']['total'],2,',','.'); ?></strong></p>
      <?php if($dados['informacoes']): ?>
        <h3>Informacoes adicionais</h3>
        <p><?php echo nl2br($dados['informacoes']); ?></p>
      <?php endif; ?>
      <p style="margin-top:24px;font-size:12px;color:#666">* Datas e valores sujeitos a validacao de disponibilidade.</p>
    </body></html>
    <?php
    $html = ob_get_clean();

    
    if ($produto) {
      $nomeProd = $produto->get_name();
      // Pega só até o primeiro traço (– ou -)
      $primeiraParte = preg_split('/[\-–]/', $nomeProd)[0] ?? $nomeProd;
      $slugProd = sanitize_title($primeiraParte); // exemplo: 'Grupo A' → 'grupo-a'
    } else {
      $slugProd = 'produto';
    }
    $dataHora = date('d-m-Y_Hi');
    $nome = "cotacao-{$slugProd}-{$dataHora}";

    if ($dados['formato'] === 'pdf' && file_exists(BVGN_CAMINHO . 'vendor/autoload.php')) {
      require_once BVGN_CAMINHO . 'vendor/autoload.php';
      if (class_exists('\Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<meta charset="utf-8">' . $html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = BVGN_DIR_ARQUIVOS . "/{$nome}.pdf";
        file_put_contents($pdf, $dompdf->output());
        $url = BVGN_URL_ARQUIVOS . "/{$nome}.pdf";
      } else {
        wp_send_json_error(['msg' => 'Dompdf não encontrado']);
      }
    } else {
      // formato html, se for o caso
      $arquivo = BVGN_DIR_ARQUIVOS . "/{$nome}.html";
      file_put_contents($arquivo, $html);
      $url = BVGN_URL_ARQUIVOS . "/{$nome}.html";
    }


    wp_send_json_success(['url'=>$url]);
  }
}
BVGN_GerarArquivoEndpoint::init();
