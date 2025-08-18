<?php
$taxas = BVGN_IntegracoesPT::obter_taxas_para_produto($a['produto_id']);
?>
<div class="bvgn-taxas">
  <div class="bvgn-totais-titulo">Serviços Opcionais</div>

  <?php include __DIR__ . '/taxa-fixa-mensal.php'; ?>

  <?php foreach($taxas as $t): ?>
    <label class="bvgn-taxa">
      <input type="checkbox" data-preco="<?php echo esc_attr($t['preco']); ?>" data-rotulo="<?php echo esc_attr($t['rotulo']); ?>" />
      <span class="lbl"><?php echo esc_html($t['rotulo']); ?> — R$ <?php echo number_format($t['preco'],2,',','.'); ?></span>
    </label>
  <?php endforeach; ?>
</div>
