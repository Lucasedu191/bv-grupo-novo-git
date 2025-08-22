<?php

if (!defined('ABSPATH')) exit;

use Dompdf\Dompdf;

class BVGN_GerarArquivoEndpoint {
  public static function init() {
    add_action('wp_ajax_bvgn_gerar_arquivo', [__CLASS__, 'executar']);
    add_action('wp_ajax_nopriv_bvgn_gerar_arquivo', [__CLASS__, 'executar']);
  }

  public static function executar() {
    check_ajax_referer('bvgn_nonce');

    try {
      // === DADOS DA COTAÇÃO ===
      $dados = [
        'produtoId' => intval($_POST['produtoId'] ?? 0),
        'nome'      => sanitize_text_field($_POST['bvgn_nome'] ?? ''),
        'whats'     => sanitize_text_field($_POST['bvgn_whats'] ?? ''),
        'variacaoRotulo' => sanitize_text_field($_POST['variacaoRotulo'] ?? ''),
        'datas' => [
          'inicio' => sanitize_text_field($_POST['datas']['inicio'] ?? ''),
          'fim'    => sanitize_text_field($_POST['datas']['fim'] ?? '')
        ],
        'taxas' => array_map(function ($t) {
          return [
            'rotulo' => sanitize_text_field($t['rotulo'] ?? ''),
            'preco'  => floatval($t['preco'] ?? 0)
          ];
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

      // === IDENTIFICADOR DO ARQUIVO ===
      $produto = function_exists('wc_get_product') ? wc_get_product($dados['produtoId']) : null;
      $slugProd = $produto ? sanitize_title(preg_split('/[\-–]/', $produto->get_name())[0]) : 'produto';
      $dataHora = date('d-m-Y_Hi');
      $nome = "cotacao-{$slugProd}-{$dataHora}";

      // === GERAR HTML VIA TEMPLATE ===
      ob_start();
      include BVGN_CAMINHO . 'modelos/partes/cotacao.php';
      $html = ob_get_clean();

      // === SALVAR HTML PARA DEBUG / BACKUP ===
      $base = BVGN_DIR_ARQUIVOS;
      $urlBase = BVGN_URL_ARQUIVOS;
      if (!file_exists($base)) wp_mkdir_p($base);

      $arquivo_html = "$base/{$nome}.html";
      file_put_contents($arquivo_html, $html);

      // === GERAR PDF COM DOMPDF ===
      $arquivo_pdf = '';
      if ($dados['formato'] === 'pdf' && file_exists(BVGN_CAMINHO . 'vendor/autoload.php')) {
        require_once BVGN_CAMINHO . 'vendor/autoload.php';

        if (class_exists('\Dompdf\Dompdf')) {
          $dompdf = new Dompdf([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
          ]);

          $dompdf->loadHtml($html);
          $dompdf->setPaper('A4', 'portrait');
          $dompdf->render();

          // Numeração de páginas (mesmo que deprecated, ainda funcional)
          $canvas = $dompdf->get_canvas();
          $font = $dompdf->getFontMetrics()->get_font("Helvetica", "normal");
          $canvas->page_text(520, 820, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 9, [0, 0, 0]);

          $arquivo_pdf = "$base/{$nome}.pdf";
          file_put_contents($arquivo_pdf, $dompdf->output());
        }
      }

      // === RESPONDER COM LINK GERADO ===
      $url = $arquivo_pdf ? "$urlBase/{$nome}.pdf" : "$urlBase/{$nome}.html";
      wp_send_json_success(['url' => $url]);

    } catch (Throwable $e) {
      // Tratamento de erro genérico
      wp_send_json_error(['msg' => 'Erro ao gerar o PDF: ' . $e->getMessage()]);
    }
  }
}

BVGN_GerarArquivoEndpoint::init();
