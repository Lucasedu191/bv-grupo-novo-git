
<?php
/**
 * Bloco de Protecao com valores variaveis por grupo.
 * So deve ser chamado em produtos do tipo "diario".
 * Usa: $a['produto_id']
 */
if (!defined('ABSPATH')) exit;

$produto_id = $a['produto_id'] ?? 0;
$p = $produto_id ? wc_get_product($produto_id) : null;
if (!$p) return;

// Detecta grupo (meta, titulo ou valor repassado pelo shortcode)
$grupo = '';
if (!empty($a['grupo'])) {
  $grupo = strtoupper($a['grupo']);
} else {
  $grupoMeta = get_post_meta($p->get_id(), '_bvgn_grupo', true);
  if (!empty($grupoMeta)) {
    $grupo = strtoupper($grupoMeta);
  } elseif (preg_match('/Grupo\s+([A-I])/i', $p->get_name(), $m)) {
    $grupo = strtoupper($m[1]);
  }
}
if (!$grupo) $grupo = 'A';
$isGrupoH = ($grupo === 'H');

// Mapeamento cor x grupo
$grupo_cor = [
  'A' => 'verde', 'B' => 'verde', 'C' => 'verde',
  'D' => 'azul',  'E' => 'azul',  'F' => 'azul', 'G' => 'azul',
  'H' => 'laranja',
  'I' => 'azul'
];

// Tabela de protecao por cor
$protecao = [
  'verde'   => ['basica' => 35,  'premium' => 65],
  'azul'    => ['basica' => 45,  'premium' => 85],
  'laranja' => ['basica' => 65,  'premium' => 125],
];

// Caucao padrao (sem protecao) por grupo
$caucaoPadrao = match ($grupo) {
  'A'     => 2000,
  'B','C','D','E','F','G','I' => 4000,
  'H'     => 4000,
  default => 0
};

// Caucao reduzido com protecao (nova tabela)
$caucaoReduzido = match ($grupo) {
  'H'     => 2000,
  'A','B','C','D','E','F','G','I' => 500,
  default => 500,
};

$cor    = $grupo_cor[$grupo] ?? 'verde';
$precoB = $protecao[$cor]['basica'];
$precoP = $protecao[$cor]['premium'];
?>

<div class="bvgn-taxas bvgn-cards-3"
     data-bvgn-protecao-grupo="<?php echo esc_attr($grupo); ?>">
  <div class="bvgn-totais-titulo">Protecao</div>

  <?php if (!$isGrupoH): ?>
    <label class="bvgn-taxa">
      <input type="radio"
             name="bvgn_protecao"
             value="sem"
             data-preco-dia="0"
             data-caucao="<?php echo esc_attr($caucaoPadrao); ?>">
      <span class="lbl">
        <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos01.svg'; ?>" alt="">
        <span class="texto">Sem protecao<br>caucao de</span>
        <span class="preco"><?php echo wc_price($caucaoPadrao); ?></span>
        <span class="botao-fake">Selecionar</span>
      </span>
    </label>
  <?php else: ?>
    <div class="bvgn-caucao-informativo">
      <span class="bvgn-caucao-informativo__icone">
        <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos01.svg'; ?>" alt="">
      </span>
      <span class="bvgn-caucao-informativo__texto">
        Caucao obrigatorio de R$ <?php echo number_format((float)$caucaoReduzido, 2, ',', '.'); ?> ? tratado diretamente com a equipe no atendimento.
      </span>
    </div>
  <?php endif; ?>

  <label class="bvgn-taxa">
    <input type="radio" name="bvgn_protecao" value="basica"
           data-preco-dia="<?php echo esc_attr($precoB); ?>"
           data-caucao="<?php echo esc_attr($caucaoReduzido); ?>"
           checked>
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos02.svg'; ?>" alt="">
      <span class="texto">Protecao Basica</span>
      <span class="preco">
        <?php echo wc_price($precoB); ?>
        <br><small>Caucao: R$ <?php echo number_format((float)$caucaoReduzido, 2, ',', '.'); ?></small>
      </span>

      <span class="botao-fake">Selecionar</span>
    </span>
  </label>

  <label class="bvgn-taxa">
    <input type="radio" name="bvgn_protecao" value="premium"
           data-preco-dia="<?php echo esc_attr($precoP); ?>"
           data-caucao="<?php echo esc_attr($caucaoReduzido); ?>">
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos03.svg'; ?>" alt="">
      <span class="texto">Protecao Premium</span>
      <span class="preco">
        <?php echo wc_price($precoP); ?>
        <br><small>Caucao: R$ <?php echo number_format((float)$caucaoReduzido, 2, ',', '.'); ?></small>
      </span>

      <span class="botao-fake">Selecionar</span>
    </span>
  </label>
</div>
