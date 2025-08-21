<?php
/**
 * Bloco de Proteção com valores variáveis por grupo.
 * Só deve ser chamado em produtos do tipo "diario".
 * Usa: $a['produto_id']
 */
if (!defined('ABSPATH')) exit;

$p = wc_get_product($a['produto_id'] ?? 0);
if (!$p) return;

// Detecta grupo (meta ou título)
$grupo = get_post_meta($p->get_id(), '_bvgn_grupo', true);
if (!$grupo && preg_match('/Grupo\s+([A-H])/', $p->get_name(), $m)) {
  $grupo = strtoupper($m[1]);
}
if (!$grupo) $grupo = 'A';

// Mapeamento cor x grupo
$grupo_cor = [
  'A' => 'verde', 'B' => 'verde', 'C' => 'verde',
  'D' => 'azul',  'E' => 'azul',  'F' => 'azul', 'G' => 'azul',
  'H' => 'laranja'
];

// Tabela de proteção por cor
$protecao = [
  'verde'   => ['basica' => 35,  'premium' => 65],
  'azul'    => ['basica' => 45,  'premium' => 85],
  'laranja' => ['basica' => 65,  'premium' => 125],
];

// Caução por grupo
$caucao = match ($grupo) {
  'A'     => 2000,
  'B','C','D','E','F','G' => 4000,
  'H'     => 8000,
  default => 0
};

$cor    = $grupo_cor[$grupo] ?? 'verde';
$precoB = $protecao[$cor]['basica'];
$precoP = $protecao[$cor]['premium'];
?>

<div class="bvgn-taxas bvgn-cards-3">
  <div class="bvgn-totais-titulo">Proteção</div>

  <label class="bvgn-taxa">
    <input type="radio" name="bvgn_protecao" value="sem"
           data-preco-dia="0" data-caucao="<?php echo esc_attr($caucao); ?>" checked>
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos01.svg'; ?>" alt="">
      <span class="texto">Sem proteção </br> caução de</span>
      <span class="preco"><?php echo wc_price($caucao); ?></span>
      <span class="botao-fake">Selecionar</span>
    </span>
  </label>

  <label class="bvgn-taxa">
    <input type="radio" name="bvgn_protecao" value="basica"
           data-preco-dia="<?php echo esc_attr($precoB); ?>" data-caucao="0">
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos02.svg'; ?>" alt="">
      <span class="texto">Proteção Básica</span>
      <span class="preco"><?php echo wc_price($precoB); ?><br><small>(isenta caução)</small></span>

      <span class="botao-fake">Selecionar</span>
    </span>
  </label>

  <label class="bvgn-taxa">
    <input type="radio" name="bvgn_protecao" value="premium"
           data-preco-dia="<?php echo esc_attr($precoP); ?>" data-caucao="0">
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos03.svg'; ?>" alt="">
      <span class="texto">Proteção Premium</span>
      <span class="preco"><?php echo wc_price($precoP); ?><br><small>(isenta caução)</small></span>

      <span class="botao-fake">Selecionar</span>
    </span>
  </label>
</div>



