<?php
if (!defined('ABSPATH')) exit;

class BVGN_ApiRest {
  public static function init() {
    add_action('rest_api_init', [__CLASS__, 'register_routes']);
  }

  public static function register_routes() {
    register_rest_route('bv/v1', '/veiculos', [
      'methods'  => WP_REST_Server::READABLE,
      'callback' => [__CLASS__, 'get_veiculos'],
      'permission_callback' => [__CLASS__, 'authorize_request'],
      'args' => [
        'page' => [
          'type' => 'integer',
          'default' => 1,
          'sanitize_callback' => 'absint',
        ],
        'per_page' => [
          'type' => 'integer',
          'default' => 50,
          'sanitize_callback' => 'absint',
        ],
      ],
    ]);

    register_rest_route('bv/v1', '/veiculos/(?P<id>\d+)/regras', [
      'methods'  => WP_REST_Server::READABLE,
      'callback' => [__CLASS__, 'get_regras_veiculo'],
      'permission_callback' => [__CLASS__, 'authorize_request'],
      'args' => [
        'id' => [
          'required' => true,
          'sanitize_callback' => 'absint',
          'validate_callback' => function($param) {
            return absint($param) > 0;
          },
        ],
      ],
    ]);
  }

  private static function assert_woocommerce() {
    if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
      return new WP_Error(
        'bvgn_woocommerce_inativo',
        'WooCommerce não está ativo.',
        ['status' => 503]
      );
    }
    return true;
  }

  // Valida Bearer token para acesso aos endpoints REST.
  public static function authorize_request(WP_REST_Request $request) {
    $expected = self::get_expected_token();
    if ($expected === '') {
      return new WP_Error(
        'bvgn_token_nao_configurado',
        'Token da API não configurado.',
        ['status' => 401]
      );
    }

    $auth = $request->get_header('authorization');
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
      $auth = wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
    }

    $auth = is_string($auth) ? trim($auth) : '';
    if ($auth === '' || stripos($auth, 'Bearer ') !== 0) {
      return new WP_Error(
        'bvgn_token_ausente',
        'Authorization Bearer token é obrigatório.',
        ['status' => 401]
      );
    }

    $provided = trim(substr($auth, 7));
    if ($provided === '' || !hash_equals($expected, $provided)) {
      return new WP_Error(
        'bvgn_token_invalido',
        'Bearer token inválido.',
        ['status' => 401]
      );
    }

    return true;
  }

  // Fonte simples do token: constante BVGN_API_TOKEN ou option bvgn_api_token.
  private static function get_expected_token() {
    if (defined('BVGN_API_TOKEN') && is_string(BVGN_API_TOKEN) && trim(BVGN_API_TOKEN) !== '') {
      return trim(BVGN_API_TOKEN);
    }

    $opt = get_option('bvgn_api_token', '');
    if (is_string($opt) && trim($opt) !== '') {
      return trim($opt);
    }

    return '';
  }

  public static function get_veiculos(WP_REST_Request $request) {
    $wc = self::assert_woocommerce();
    if (is_wp_error($wc)) return $wc;

    $page = max(1, absint($request->get_param('page')));
    $per_page = absint($request->get_param('per_page'));
    if ($per_page < 1) $per_page = 50;
    if ($per_page > 100) $per_page = 100;

    $products = wc_get_products([
      'status' => 'publish',
      'limit' => $per_page,
      'page' => $page,
      'paginate' => true,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    $items = [];
    foreach ($products->products as $product) {
      $items[] = self::format_product_basic($product);
    }

    return rest_ensure_response([
      'pagina' => $page,
      'por_pagina' => $per_page,
      'total' => intval($products->total),
      'total_paginas' => intval($products->max_num_pages),
      'itens' => $items,
    ]);
  }

  public static function get_regras_veiculo(WP_REST_Request $request) {
    $wc = self::assert_woocommerce();
    if (is_wp_error($wc)) return $wc;

    $product_id = absint($request['id']);
    $product = wc_get_product($product_id);
    if (!$product) {
      return new WP_Error('bvgn_produto_nao_encontrado', 'Produto não encontrado.', ['status' => 404]);
    }
    if ($product->get_status() !== 'publish') {
      return new WP_Error('bvgn_produto_indisponivel', 'Produto não está publicado.', ['status' => 404]);
    }

    $tipo_plano = self::resolve_tipo_por_categoria($product_id);
    $grupo = self::resolve_grupo_produto($product_id, $product->get_name());

    $variacoes = self::get_variacoes_regras($product, $tipo_plano);
    $taxas_base = self::get_taxas_base($product_id);
    $protecao_diaria = self::get_protecao_diaria($grupo, $tipo_plano);
    $caucao_mensal = self::get_caucao_mensal($grupo, $product_id);
    $tarifas_dinamicas = self::get_tarifas_dinamicas_aplicaveis($grupo, $tipo_plano);

    return rest_ensure_response([
      'produto_id' => $product_id,
      'produto_nome' => wp_strip_all_tags($product->get_name()),
      'grupo' => $grupo,
      'tipo_plano' => $tipo_plano,
      'variacoes' => $variacoes,
      'taxas_base' => $taxas_base['todas'],
      'taxa_limpeza' => $taxas_base['taxa_limpeza'],
      'cadeirinha' => $taxas_base['cadeirinha'],
      'condutor_adicional' => $taxas_base['condutor_adicional'],
      'protecao' => $protecao_diaria['protecao'],
      'caucao_diaria' => $protecao_diaria['caucao_diaria'],
      'caucao_mensal' => $caucao_mensal,
      'tarifa_dinamica' => $tarifas_dinamicas,
    ]);
  }

  private static function format_product_basic($product) {
    $cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'all']);
    if (is_wp_error($cats)) $cats = [];

    $categorias = [];
    foreach ($cats as $cat) {
      $categorias[] = [
        'id' => intval($cat->term_id),
        'nome' => sanitize_text_field($cat->name),
        'slug' => sanitize_title($cat->slug),
      ];
    }

    $variacoes = [];
    if ($product->is_type('variable')) {
      $variacoes = self::get_variacoes_basicas($product);
    }

    return [
      'id' => $product->get_id(),
      'nome' => wp_strip_all_tags($product->get_name()),
      'slug' => sanitize_title($product->get_slug()),
      'preco' => (float) $product->get_regular_price(),
      'preco_promocional' => $product->get_sale_price() !== '' ? (float) $product->get_sale_price() : null,
      'descricao_curta' => wp_strip_all_tags($product->get_short_description()),
      'link_produto' => get_permalink($product->get_id()),
      'imagem_principal' => self::get_image_url($product->get_image_id()),
      'categorias' => $categorias,
      'status_estoque' => sanitize_text_field($product->get_stock_status()),
      'tipo_produto' => sanitize_text_field($product->get_type()),
      'variacoes_basicas' => $variacoes,
    ];
  }

  private static function get_variacoes_basicas($product) {
    $rows = [];
    foreach ($product->get_children() as $variation_id) {
      $variation = wc_get_product($variation_id);
      if (!$variation) continue;

      $rows[] = [
        'id' => $variation->get_id(),
        'nome' => wp_strip_all_tags($variation->get_name()),
        'preco' => (float) $variation->get_regular_price(),
        'preco_promocional' => $variation->get_sale_price() !== '' ? (float) $variation->get_sale_price() : null,
        'status_estoque' => sanitize_text_field($variation->get_stock_status()),
        'atributos' => array_map('sanitize_text_field', $variation->get_attributes()),
      ];
    }
    return $rows;
  }

  private static function get_variacoes_regras($product, $tipo_plano) {
    $rows = [];
    if (!$product->is_type('variable')) return $rows;

    $attr_tax = 'franquia-de-km';
    $attr_key = 'attribute_' . $attr_tax;
    $available = $product->get_available_variations();

    foreach ($available as $raw) {
      $variation_id = isset($raw['variation_id']) ? absint($raw['variation_id']) : 0;
      if (!$variation_id) continue;
      $variation = wc_get_product($variation_id);
      if (!$variation) continue;

      $attrs = isset($raw['attributes']) && is_array($raw['attributes']) ? $raw['attributes'] : [];

      if ($tipo_plano === 'mensal') {
        $franquia_valor = isset($attrs[$attr_key]) ? trim((string)$attrs[$attr_key]) : '';
        if ($franquia_valor === '') continue;
        $rotulo = 'Franquia: ' . $franquia_valor;
        $min_max = [30, 30];
      } else {
        $rotulo = wc_get_formatted_variation($attrs, true, false, false);
        $min_max = self::min_max_by_label($rotulo, 'diario');
      }

      $rows[] = [
        'id' => $variation_id,
        'rotulo' => wp_strip_all_tags($rotulo),
        'preco' => (float) $variation->get_price(),
        'preco_regular' => (float) $variation->get_regular_price(),
        'preco_promocional' => $variation->get_sale_price() !== '' ? (float) $variation->get_sale_price() : null,
        'min_dias' => (int) $min_max[0],
        'max_dias' => (int) $min_max[1],
        'descricao' => sanitize_textarea_field($variation->get_description()),
      ];
    }

    return $rows;
  }

  private static function min_max_by_label($rotulo, $tipo) {
    if ($tipo === 'mensal') return [30, 30];

    if (preg_match('~(\d{1,2})[^\d]+(\d{1,2})~', (string)$rotulo, $m)) {
      return [intval($m[1]), intval($m[2])];
    }

    if (preg_match('~(\d{1,2})\s*dias?~i', (string)$rotulo, $m)) {
      $n = max(1, intval($m[1]));
      if ($n === 1) return [1, 2];
      return [$n, $n];
    }

    return [1, 30];
  }

  private static function get_taxas_base($product_id) {
    $all = [];
    if (class_exists('BVGN_IntegracoesPT') && is_callable(['BVGN_IntegracoesPT', 'obter_taxas_para_produto'])) {
      $all = BVGN_IntegracoesPT::obter_taxas_para_produto($product_id);
    }
    if (!is_array($all)) $all = [];

    $clean = [];
    $taxa_limpeza = null;
    $cadeirinha = null;
    $condutor = null;

    foreach ($all as $t) {
      if (!is_array($t)) continue;
      $rotulo = sanitize_text_field($t['rotulo'] ?? '');
      $preco = floatval($t['preco'] ?? 0);
      $item = [
        'rotulo' => $rotulo,
        'preco' => $preco,
        'icone' => sanitize_text_field($t['icone'] ?? ''),
      ];
      $clean[] = $item;

      $rotulo_lower = strtolower($rotulo);
      if ($taxa_limpeza === null && strpos($rotulo_lower, 'limpeza') !== false) $taxa_limpeza = $item;
      if ($cadeirinha === null && strpos($rotulo_lower, 'cadeirinha') !== false) $cadeirinha = $item;
      if ($condutor === null && strpos($rotulo_lower, 'condutor adicional') !== false) $condutor = $item;
    }

    return [
      'todas' => $clean,
      'taxa_limpeza' => $taxa_limpeza,
      'cadeirinha' => $cadeirinha,
      'condutor_adicional' => $condutor,
    ];
  }

  private static function get_protecao_diaria($grupo, $tipo_plano) {
    if ($tipo_plano !== 'diario') {
      return [
        'protecao' => [],
        'caucao_diaria' => null,
      ];
    }

    $grupo_cor = [
      'A' => 'verde', 'B' => 'verde', 'C' => 'verde',
      'D' => 'azul',  'E' => 'azul',  'F' => 'azul', 'G' => 'azul',
      'H' => 'laranja', 'I' => 'azul',
    ];

    $protecao_por_cor = [
      'verde'   => ['basica' => 35,  'premium' => 65],
      'azul'    => ['basica' => 45,  'premium' => 85],
      'laranja' => ['basica' => 65,  'premium' => 125],
    ];

    $caucao_por_grupo = [
      'A' => 2000, 'B' => 4000, 'C' => 4000, 'D' => 4000, 'E' => 4000,
      'F' => 4000, 'G' => 4000, 'I' => 4000, 'H' => 4000,
    ];

    $caucao_protecao_por_grupo = ['H' => 2000];
    $caucao_sem_protecao = isset($caucao_por_grupo[$grupo]) ? floatval($caucao_por_grupo[$grupo]) : 0.0;
    $caucao_com_protecao = isset($caucao_protecao_por_grupo[$grupo]) ? floatval($caucao_protecao_por_grupo[$grupo]) : 500.0;
    $cor = isset($grupo_cor[$grupo]) ? $grupo_cor[$grupo] : 'verde';
    $precos = isset($protecao_por_cor[$cor]) ? $protecao_por_cor[$cor] : $protecao_por_cor['verde'];

    return [
      'protecao' => [
        [
          'tipo' => 'basica',
          'preco_dia' => floatval($precos['basica']),
          'caucao' => $caucao_com_protecao,
        ],
        [
          'tipo' => 'premium',
          'preco_dia' => floatval($precos['premium']),
          'caucao' => $caucao_com_protecao,
        ],
      ],
      'caucao_diaria' => [
        'grupo' => $grupo,
        'caucao_sem_protecao' => $caucao_sem_protecao,
        'caucao_com_protecao' => $caucao_com_protecao,
      ],
    ];
  }

  private static function get_caucao_mensal($grupo, $product_id) {
    $mensal = null;
    if (class_exists('BVGN_IntegracoesPT') && is_callable(['BVGN_IntegracoesPT', 'obter_mensal_por_grupo'])) {
      $mensal = BVGN_IntegracoesPT::obter_mensal_por_grupo($product_id);
    }

    if (is_array($mensal) && isset($mensal['caucao'])) {
      return [
        'grupo' => !empty($mensal['grupo']) ? sanitize_text_field($mensal['grupo']) : $grupo,
        'caucao' => floatval($mensal['caucao']),
        'km_preco' => isset($mensal['km_preco']) ? floatval($mensal['km_preco']) : null,
        'origem' => 'integracao',
      ];
    }

    // Fallback local igual ao comportamento atual do template taxa-fixa-mensal.php
    $map = [
      'A' => ['caucao' => 1000.00, 'km' => 0.75],
      'B' => ['caucao' => 1000.00, 'km' => 0.75],
      'C' => ['caucao' => 1500.00, 'km' => 0.60],
      'D' => ['caucao' => 1500.00, 'km' => 0.60],
      'E' => ['caucao' => 2500.00, 'km' => 1.00],
      'F' => ['caucao' => 2500.00, 'km' => 1.00],
      'G' => ['caucao' => 2000.00, 'km' => 1.00],
      'I' => ['caucao' => 2000.00, 'km' => 1.00],
      'J' => ['caucao' => 2000.00, 'km' => 1.00],
      'H' => ['caucao' => 5000.00, 'km' => 1.50],
    ];

    if (!isset($map[$grupo])) return null;
    return [
      'grupo' => $grupo,
      'caucao' => floatval($map[$grupo]['caucao']),
      'km_preco' => floatval($map[$grupo]['km']),
      'origem' => 'fallback_local',
    ];
  }

  private static function get_tarifas_dinamicas_aplicaveis($grupo, $tipo_plano) {
    if ($tipo_plano !== 'diario') return [];
    if (!class_exists('BVGN_DynamicTariffs') || !is_callable(['BVGN_DynamicTariffs', 'for_js'])) return [];

    $rules = BVGN_DynamicTariffs::for_js();
    if (!is_array($rules)) return [];

    $out = [];
    foreach ($rules as $r) {
      if (!is_array($r)) continue;
      if (empty($r['active'])) continue;

      $groups = isset($r['groups']) && is_array($r['groups']) ? array_map('sanitize_text_field', $r['groups']) : [];
      if (!empty($groups) && !in_array($grupo, $groups, true)) continue;

      $out[] = [
        'tipo' => sanitize_text_field($r['type'] ?? ''),
        'percentual' => floatval($r['percent'] ?? 0),
        'rotulo' => sanitize_text_field($r['label'] ?? ''),
        'descricao' => sanitize_text_field($r['desc'] ?? ''),
        'prioridade' => intval($r['priority'] ?? 0),
        'dia_semana' => intval($r['weekday'] ?? 0),
        'data_inicio' => sanitize_text_field($r['startDate'] ?? ''),
        'data_fim' => sanitize_text_field($r['endDate'] ?? ''),
        'grupos' => $groups,
        'exibir_resumo' => !empty($r['showResumo']),
        'exibir_pdf' => !empty($r['showPdf']),
      ];
    }

    return $out;
  }

  private static function resolve_tipo_por_categoria($produto_id) {
    if ($produto_id && taxonomy_exists('product_cat')) {
      if (has_term('aluguel-de-carros-mensal', 'product_cat', $produto_id)) return 'mensal';
      if (has_term('aluguel-de-carros-diaria', 'product_cat', $produto_id)) return 'diario';
    }
    return 'diario';
  }

  private static function resolve_grupo_produto($produto_id, $product_name = '') {
    $grupo = '';
    $meta_grupo = get_post_meta($produto_id, '_bvgn_grupo', true);
    if (!empty($meta_grupo)) {
      $grupo = strtoupper(sanitize_text_field($meta_grupo));
    } elseif ($product_name && preg_match('/Grupo\s+([A-Z])/i', $product_name, $m)) {
      $grupo = strtoupper($m[1]);
    }

    if (!preg_match('/^[A-Z]$/', $grupo)) $grupo = 'A';
    return $grupo;
  }

  private static function get_image_url($image_id) {
    $image_id = absint($image_id);
    if (!$image_id) return null;
    $url = wp_get_attachment_image_url($image_id, 'full');
    return $url ? esc_url_raw($url) : null;
  }
}

BVGN_ApiRest::init();
