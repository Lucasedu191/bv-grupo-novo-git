<?php
$tipo = $a['type'] ?? 'diario';
?>
<div class="bvgn-totais" data-bvgn-tipo="<?php echo esc_attr($tipo); ?>">

  <!-- Descrição da variação selecionada -->
  <div class="bvgn-var" style="display:none;">
    <small>Plano selecionado: <strong id="bvgn-var-view">—</strong></small>
  </div>

  <!-- Local de retirada (JS preenche "Sede") -->
  <div class="bvgn-local" style="display:none;">
    <small>Retirada em: <strong id="bvgn-local-view">—</strong></small>
  </div>

  <!-- Mostrador de dias -->
  <div class="bvgn-dias" style="display:none;">
    <small><span id="bvgn-days-label">Período:</span> <strong id="bvgn-days-view">—</strong></small>
  </div>

  <!-- Mostrador de taxas totais -->
  <div class="bvgn-taxas" style="display:none;">
    Taxas: R$ <span class="valor" id="bvgn-taxas">0,00</span>
  </div>

  <!-- Lista de taxas detalhadas -->
  <div class="bvgn-taxas-lista" style="display:none;">
    <ul id="bvgn-taxas-itens"></ul>
  </div>

  <!-- Subtotal e total -->
  <div class="bvgn-subtotal">
    Subtotal: R$ <span class="valor" id="bvgn-subtotal">0,00</span>
  </div>

  <div class="bvgn-total">
    <strong>Total: R$ <span class="valor" id="bvgn-total">0,00</span></strong>
  </div>

  <!-- Valores crus -->
  <input type="hidden" id="bvgn-days-raw" value="0">
  <input type="hidden" id="bvgn-taxas-raw" value="0">
  <input type="hidden" id="bvgn-subtotal-raw" value="0">
  <input type="hidden" id="bvgn-total-raw" value="0">
</div>
