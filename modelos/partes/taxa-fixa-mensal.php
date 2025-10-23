<?php
if (!defined('ABSPATH')) exit;

// 1) Produto
$produto_id = 0;
if (isset($a['produto_id'])) {
  $produto_id = intval($a['produto_id']);
} elseif (function_exists('get_the_ID')) {
  $produto_id = intval(get_the_ID());
}

// 2) Descobrir se é "mensal"
$is_mensal = false;
if ($produto_id && function_exists('wp_get_post_terms')) {
  $cats = wp_get_post_terms($produto_id, 'product_cat', ['fields'=>'all']);
  if (!is_wp_error($cats)) {
    foreach ($cats as $c) {
      $slug = isset($c->slug) ? $c->slug : '';
      $name = isset($c->name) ? $c->name : '';
      if (stripos($slug, 'mensal') !== false || stripos($name, 'mensal') !== false) {
        $is_mensal = true; break;
      }
    }
  }
}

// 3) Grupo
$titulo = $produto_id ? get_the_title($produto_id) : '';
$grupo = null;
if ($titulo && preg_match('/Grupo\s+([A-Z])/i', $titulo, $m)) {
  $grupo = strtoupper($m[1]);
}

// 4) Dados
$caucao = null; $km = null;
if (class_exists('BVGN_IntegracoesPT') && is_callable(['BVGN_IntegracoesPT','obter_mensal_por_grupo'])) {
  $mensal = BVGN_IntegracoesPT::obter_mensal_por_grupo($produto_id);
  if (!empty($mensal['is_mensal'])) $is_mensal = true;
  if (!empty($mensal['grupo']))     $grupo     = strtoupper($mensal['grupo']);
  if (isset($mensal['caucao']))     $caucao    = (float)$mensal['caucao'];
  if (isset($mensal['km_preco']))   $km        = (float)$mensal['km_preco'];
}

if ($caucao === null || $km === null) {
  $map = [
    'A' => ['caucao'=>1000.00, 'km'=>0.60],
    'B' => ['caucao'=>1000.00, 'km'=>0.60],
    'C' => ['caucao'=>1500.00, 'km'=>0.60],
    'D' => ['caucao'=>1500.00, 'km'=>0.60],
    'E' => ['caucao'=>2500.00, 'km'=>1.00],
    'F' => ['caucao'=>2500.00, 'km'=>1.00],
    'G' => ['caucao'=>2000.00, 'km'=>1.00],
    'I' => ['caucao'=>2000.00, 'km'=>1.00],
    'J' => ['caucao'=>2000.00, 'km'=>1.00],
    'H' => ['caucao'=>5000.00, 'km'=>1.50],
  ];
  if ($grupo && isset($map[$grupo])) {
    if ($caucao === null) $caucao = $map[$grupo]['caucao'];
    if ($km     === null) $km     = $map[$grupo]['km'];
  }
}

// 5) Renderizar se tudo OK
if ($is_mensal && $grupo && $caucao !== null && $km !== null):
  $rotulo_fixo = sprintf(
    'Caução (aplicado na retirada, reembolsável em caso de devolução sem danos) — Grupo %s',
    $grupo
  );
  $preco_formatado = number_format((float)$caucao, 2, ',', '.');
  $km_formatado    = number_format((float)$km, 2, ',', '.');
  $icone = BVGN_URL . 'assets/svg/passos01.svg'; // ícone igual aos outros
?>

  <!-- entra no cálculo -->
  <input
    type="hidden"
    class="bvgn-taxa-fixa-input"
    data-preco="<?php echo esc_attr(number_format((float)$caucao, 2, '.', '')); ?>"
    data-rotulo="<?php echo esc_attr($rotulo_fixo); ?>"
    data-km-preco="<?php echo esc_attr(number_format((float)$km, 2, '.', '')); ?>"
    data-grupo="<?php echo esc_attr($grupo); ?>"
  />

  <!-- visual no mesmo formato das outras taxas -->
  <label class="bvgn-taxa selecionado obrigatorio bvgn-taxa-caucao">
  <input type="checkbox" checked disabled />
  <span class="lbl">
    <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos01.svg'; ?>" alt="">
    <span class="texto">
      <?php echo esc_html($rotulo_fixo); ?>
      <br><small>Quilometragem excedente: R$ <?php echo number_format((float)$km, 2, ',', '.'); ?>/km adicional</small>
    </span>
    <span class="preco">R$ <?php echo number_format((float)$caucao, 2, ',', '.'); ?></span>
    <span class="botao-fake">Selecionado</span>
  </span>
</label>

<?php endif; ?>
