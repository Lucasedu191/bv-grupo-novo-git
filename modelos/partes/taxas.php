<?php
$taxas = BVGN_IntegracoesPT::obter_taxas_para_produto($a['produto_id']);
?>
<div class="bvgn-taxas bvgn-cards-3">
  <div class="bvgn-totais-titulo">Serviços Opcionais</div>

  <?php include __DIR__ . '/taxa-fixa-mensal.php'; ?>

  <?php foreach($taxas as $t): ?>
    <?php
      $icone = !empty($t['icone']) ? $t['icone'] : 'assets/svg/passos03.svg';
    ?>
    <label class="bvgn-taxa">
      <input type="checkbox"
             data-preco="<?php echo esc_attr($t['preco']); ?>"
             data-rotulo="<?php echo esc_attr($t['rotulo']); ?>" />
      <span class="lbl">
        <img class="bvgn-icon" src="<?php echo BVGN_URL . $icone; ?>" alt="">
        <span class="texto"><?php echo esc_html($t['rotulo']); ?></span>
        <span class="preco">R$ <?php echo number_format($t['preco'], 2, ',', '.'); ?></span>
        <span class="botao-fake">Selecionar</span>
      </span>
    </label>
  <?php endforeach; ?>
</div>
