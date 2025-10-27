<?php
$taxas = BVGN_IntegracoesPT::obter_taxas_para_produto($a['produto_id']);
$tipo_atual = $a['type'] ?? 'diario';
$grupo_atual = isset($a['grupo']) ? strtoupper($a['grupo']) : '';
$mostrar_caucao_diario = ($tipo_atual === 'diario' && $grupo_atual === 'H');
?>
<div class="bvgn-taxas bvgn-cards-3 bvgn-servicos-opcionais"
     data-bvgn-servicos-grupo="<?php echo esc_attr($grupo_atual); ?>">
  <div class="bvgn-totais-titulo">Serviços Opcionais</div>

  <?php include __DIR__ . '/taxa-fixa-mensal.php'; ?>

  <?php if ($mostrar_caucao_diario): ?>
    <?php
      $caucao_valor = 4000.00;
      $caucao_rotulo = 'Caução obrigatória (Grupo H)';
    ?>
    <label class="bvgn-taxa selecionado obrigatorio bvgn-taxa-caucao" data-bvgn-caucao-card="1">
      <input type="checkbox"
             data-preco="<?php echo esc_attr(number_format($caucao_valor, 2, '.', '')); ?>"
             data-rotulo="<?php echo esc_attr($caucao_rotulo); ?>"
             data-tipo="caucao"
             checked
             disabled />
      <span class="lbl">
        <img class="bvgn-icon" src="<?php echo BVGN_URL . 'assets/svg/passos01.svg'; ?>" alt="">
        <span class="texto">
          <?php echo esc_html($caucao_rotulo); ?>
          <br><small>Aplicado na retirada, reembolsável sem danos</small>
        </span>
        <span class="preco">R$ <?php echo number_format($caucao_valor, 2, ',', '.'); ?></span>
        <span class="botao-fake">Selecionado</span>
      </span>
    </label>
  <?php endif; ?>

<?php foreach($taxas as $t): ?>
  <?php
    $icone = !empty($t['icone']) ? $t['icone'] : 'assets/svg/passos03.svg';
    $rotulo = $t['rotulo'];

    // Detecta se é taxa obrigatória
    $is_obrigatoria = stripos($rotulo, 'limpeza') !== false;

    // Substitui partes do rótulo por <small>
    $rotulo = str_replace(
      ['(diaria)', '(obrigatória)'],
      ['<br><small>(diaria)</small>', '<br><small>(obrigatória)</small>'],
      $rotulo
    );
  ?>
  <label class="bvgn-taxa<?php echo $is_obrigatoria ? ' selecionado obrigatorio' : ''; ?>">
    <input type="checkbox"
          data-preco="<?php echo esc_attr($t['preco']); ?>"
          data-rotulo="<?php echo esc_attr($t['rotulo']); ?>"
          <?php echo $is_obrigatoria ? 'checked disabled' : ''; ?> />
    <span class="lbl">
      <img class="bvgn-icon" src="<?php echo BVGN_URL . esc_attr($icone); ?>" alt="">
      <span class="texto"><?php echo $rotulo; ?></span>
      <span class="preco">R$ <?php echo number_format($t['preco'], 2, ',', '.'); ?></span>
      <span class="botao-fake"><?php echo $is_obrigatoria ? 'Selecionado' : 'Selecionar'; ?></span>
    </span>
  </label>
<?php endforeach; ?>
</div>

