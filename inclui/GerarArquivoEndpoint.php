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
      // Garantimos que arrays venham no formato esperado para evitar TypeError ao mapear
      $postTaxas  = $_POST['taxas'] ?? [];
      $postTotais = $_POST['totais'] ?? [];
      if (!is_array($postTaxas))  $postTaxas  = [];
      if (!is_array($postTotais)) $postTotais = [];

      // Sanitiza listas evitando array_map em valores nulos
      $taxasSanit = [];
      foreach ($postTaxas as $t) {
        if (!is_array($t)) continue;
        $taxasSanit[] = [
          'rotulo' => sanitize_text_field($t['rotulo'] ?? ''),
          'preco'  => floatval($t['preco'] ?? 0)
        ];
      }

      $dynDetRaw = is_array($postTotais['dynamicDetalhes'] ?? null) ? $postTotais['dynamicDetalhes'] : [];
      $dynDetSanit = [];
      $asBool = function($v){
        return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
      };
      foreach ($dynDetRaw as $d) {
        if (!is_array($d)) continue;
        $dynDetSanit[] = [
          'data'    => sanitize_text_field($d['data'] ?? ''),
          'rotulo'  => sanitize_text_field($d['rotulo'] ?? ''),
          'desc'    => sanitize_text_field($d['desc'] ?? ''),
          'percent' => floatval($d['percent'] ?? 0),
          'valor'   => floatval($d['valor'] ?? 0),
          'show_resumo' => $asBool($d['showResumo'] ?? ($d['show_resumo'] ?? false)),
          'show_pdf'    => $asBool($d['showPdf'] ?? ($d['show_pdf'] ?? false)),
        ];
      }

      $dados = [
        'produtoId' => intval($_POST['produtoId'] ?? 0),
        'nome'      => sanitize_text_field($_POST['bvgn_nome'] ?? ''),
        'whats'     => sanitize_text_field($_POST['bvgn_whats'] ?? ''),
        'variacaoRotulo' => sanitize_text_field($_POST['variacaoRotulo'] ?? ''),
        'datas' => [
          'inicio' => sanitize_text_field($_POST['datas']['inicio'] ?? ''),
          'fim'    => sanitize_text_field($_POST['datas']['fim'] ?? '')
        ],
        'taxas' => $taxasSanit,
        'totais' => [
          'base'    => floatval($postTotais['base'] ?? 0),
          'taxas'   => floatval($postTotais['taxas'] ?? 0),
          'dynamic_extra' => floatval($postTotais['dynamicExtra'] ?? 0),
          'dynamic_detalhes' => $dynDetSanit,
          'qtd'     => intval($postTotais['qtd'] ?? 1),
          'subtotal'=> floatval($postTotais['subtotal'] ?? 0),
          'total'   => floatval($postTotais['total'] ?? 0),
          'tipo'    => sanitize_text_field($postTotais['tipo'] ?? 'diario'),
        ],
        'local'       => sanitize_text_field($_POST['bvgn_local'] ?? ''),       // Local de retirada
        'mensagem'    => sanitize_textarea_field($_POST['bvgn_mensagem'] ?? ''),// Mensagem do modal


        'formato' => 'pdf' // HTML não é mais salvo
      ];

      // === IDENTIFICADOR / REGISTRO DA COTAÇÃO ===
      $produto = function_exists('wc_get_product') ? wc_get_product($dados['produtoId']) : null;
      $slugProd = $produto ? sanitize_title(preg_split('/[\-–]/', $produto->get_name())[0]) : 'produto';

      // Cria um registro no admin (CPT) para listar as cotações
      $post_id = wp_insert_post([
        'post_type'   => 'bvgn_cotacao',
        'post_status' => 'publish',
        'post_title'  => sprintf('Cotação %s - %s', date_i18n('d/m/Y H:i'), $produto ? $produto->get_name() : 'Produto'),
      ], true);
      if (is_wp_error($post_id)) {
        throw new Exception('Falha ao registrar a cotação no admin.');
      }

      // Guarda metadados para consulta no admin
      update_post_meta($post_id, 'produto_id', $dados['produtoId']);
      update_post_meta($post_id, 'cliente_nome', $dados['nome']);
      update_post_meta($post_id, 'cliente_whats', $dados['whats']);
      update_post_meta($post_id, 'variacao_rotulo', $dados['variacaoRotulo']);
      update_post_meta($post_id, 'datas_inicio', $dados['datas']['inicio']);
      update_post_meta($post_id, 'datas_fim', $dados['datas']['fim']);
      update_post_meta($post_id, 'totais_base', $dados['totais']['base']);
      update_post_meta($post_id, 'totais_taxas', $dados['totais']['taxas']);
      update_post_meta($post_id, 'totais_dynamic_extra', $dados['totais']['dynamic_extra'] ?? 0);
      update_post_meta($post_id, 'totais_qtd', $dados['totais']['qtd']);
      update_post_meta($post_id, 'totais_subtotal', $dados['totais']['subtotal']);
      update_post_meta($post_id, 'totais_total', $dados['totais']['total']);
      update_post_meta($post_id, 'totais_tipo', $dados['totais']['tipo']);
      update_post_meta($post_id, 'local_retirada', $dados['local']);
      update_post_meta($post_id, 'mensagem', $dados['mensagem']);

      // Gera o código da cotação (5 dígitos) e mantém o mesmo no PDF e no template
      $codigo = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
      update_post_meta($post_id, 'codigo', $codigo);

      // Nome do arquivo: grupo_data_e_codigo.pdf
      $dataCurta = date('d-m-Y');
      $nome_base = sprintf('%s_%s_e_%s', $slugProd, $dataCurta, $codigo);

      // === GERAR HTML VIA TEMPLATE ===
      ob_start();
      // disponibiliza $codigo ao template
      include BVGN_CAMINHO . 'modelos/partes/cotacao.php';
      $html = ob_get_clean();

      // === PREPARA DIRETÓRIO ===
      $base = BVGN_DIR_ARQUIVOS;
      $urlBase = BVGN_URL_ARQUIVOS;
      if (!file_exists($base)) wp_mkdir_p($base);
      

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

          $arquivo_pdf = "$base/{$nome_base}.pdf";
          file_put_contents($arquivo_pdf, $dompdf->output());
        }
      }

      // Só trabalhamos com PDF agora
      if (!$arquivo_pdf) {
        throw new Exception('Não foi possível gerar o PDF.');
      }
      $url = "$urlBase/{$nome_base}.pdf";
      update_post_meta($post_id, 'pdf_url', esc_url_raw($url));
      wp_send_json_success(['url' => $url]);

    } catch (Throwable $e) {
      // Tratamento de erro genérico
      wp_send_json_error(['msg' => 'Erro ao gerar o PDF: ' . $e->getMessage()]);
    }
  }
}

BVGN_GerarArquivoEndpoint::init();
