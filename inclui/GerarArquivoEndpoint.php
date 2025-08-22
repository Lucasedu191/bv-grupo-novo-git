<?php
use Dompdf\Dompdf;

class BVGN_GerarArquivoEndpoint {
  public static function init() {
    add_action('wp_ajax_bvgn_gerar_arquivo', [__CLASS__,'executar']);
    add_action('wp_ajax_nopriv_bvgn_gerar_arquivo', [__CLASS__,'executar']);
  }

  public static function executar(){
    check_ajax_referer('bvgn_nonce');

    $dados = [
      'produtoId' => intval($_POST['produtoId'] ?? 0),
      'nome'      => sanitize_text_field($_POST['bvgn_nome'] ?? ''),
      'whats'     => sanitize_text_field($_POST['bvgn_whats'] ?? ''),
      'variacaoRotulo' => sanitize_text_field($_POST['variacaoRotulo'] ?? ''),
      'datas' => [
        'inicio' => sanitize_text_field($_POST['datas']['inicio'] ?? ''),
        'fim'    => sanitize_text_field($_POST['datas']['fim'] ?? '')
      ],
      'taxas' => array_map(function($t){
        return ['rotulo'=> sanitize_text_field($t['rotulo'] ?? ''), 'preco'=> floatval($t['preco'] ?? 0)];
      }, $_POST['taxas'] ?? []),
      'totais' => [
        'base'    => floatval($_POST['totais']['base'] ?? 0),
        'taxas'   => floatval($_POST['totais']['taxas'] ?? 0),
        'qtd'     => intval($_POST['totais']['qtd'] ?? 1),
        'subtotal'=> floatval($_POST['totais']['subtotal'] ?? 0),
        'total'   => floatval($_POST['totais']['total'] ?? 0),
        'tipo'    => sanitize_text_field($_POST['totais']['tipo'] ?? 'diario'),
      ],
      'formato' => sanitize_text_field($_POST['formato'] ?? 'pdf')
    ];

    $produto = function_exists('wc_get_product') ? wc_get_product($dados['produtoId']) : null;
    $slugProd = $produto ? sanitize_title(preg_split('/[\-–]/', $produto->get_name())[0]) : 'produto';
    $dataHora = date('d-m-Y_Hi');
    $nome = "cotacao-{$slugProd}-{$dataHora}";

    ob_start(); ?>
    <html><head><meta charset="utf-8"><style>
      body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
      .container { width: 100%; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ccc; }
      h1 { font-size: 18px; text-align: center; margin-bottom: 20px; }
      table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      th, td { text-align: left; padding: 6px; border-bottom: 1px solid #ddd; }
      .total { background: #002c5f; color: #fff; font-weight: bold; }
      .footer { font-size: 10px; text-align: center; margin-top: 20px; }
      .label { font-weight: bold; width: 100px; display: inline-block; }
    </style></head><body>
    <div class="container">
      <h1>BVLOCADORA - COTAÇÃO DE SERVIÇO</h1>

      <p><span class="label">Cliente:</span> <?php echo esc_html($dados['nome']); ?><br>
         <span class="label">WhatsApp:</span> <?php echo esc_html($dados['whats']); ?></p>

      <p><span class="label">Plano:</span> <?php echo esc_html($dados['variacaoRotulo']); ?><br>
         <span class="label">Período:</span> <?php echo esc_html($dados['datas']['inicio'] . ' a ' . $dados['datas']['fim']); ?><br>
         <span class="label">Retirada:</span> BV Locadora, Rua Coronel Mota, 629</p>

      <table>
        <tr><td>Diária/Mensal</td><td>R$ <?php echo number_format($dados['totais']['base'],2,',','.'); ?></td></tr>
        <tr><td>Caução</td><td>R$ 2.500,00</td></tr>
        <?php if (!empty($dados['taxas'])): ?>
          <?php foreach($dados['taxas'] as $t): ?>
            <tr><td><?php echo esc_html($t['rotulo']); ?></td><td>R$ <?php echo number_format($t['preco'],2,',','.'); ?></td></tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr><td>Subtotal</td><td>R$ <?php echo number_format($dados['totais']['subtotal'],2,',','.'); ?></td></tr>
        <tr class="total"><td>Total estimado</td><td>R$ <?php echo number_format($dados['totais']['total'],2,',','.'); ?></td></tr>
      </table>

      <div class="footer">
        Telefone: (95) 98102-2395 – <a href="mailto:bvlocadora@outlook.com">bvlocadora@outlook.com</a><br>
        www.bvlocadora.com.br – Rua Coronel Mota, 629, Boa Vista – RR<br>
        A cotação possui validade de 5 dias.
      </div>
    </div>
    </body></html>
    <?php
    $html = ob_get_clean();

    if ($dados['formato'] === 'pdf' && file_exists(BVGN_CAMINHO . 'vendor/autoload.php')) {
      require_once BVGN_CAMINHO . 'vendor/autoload.php';
      if (class_exists('\Dompdf\Dompdf')) {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<meta charset="utf-8">' . $html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = BVGN_DIR_ARQUIVOS . "/{$nome}.pdf";
        file_put_contents($pdf, $dompdf->output());
        $url = BVGN_URL_ARQUIVOS . "/{$nome}.pdf";
        wp_send_json_success(['url' => $url]);
      }
    }

    wp_send_json_error(['msg' => 'Erro ao gerar PDF']);
  }
}
