<?php
$tipo = $a['type'] ?? 'diario';
?>
<div class="bvgn-totais" data-bvgn-tipo="<?php echo esc_attr($tipo); ?>">
  <!-- Opcional: mostrador de dias / regra atual (JS preenche) -->
  <div class="bvgn-dias" style="display:none;">
    <small><span id="bvgn-days-label">Período:</span> <strong id="bvgn-days-view">—</strong></small>
  </div>

  <!-- Opcional: mostrador de taxas (JS preenche) -->
  <div class="bvgn-taxas" style="display:none;">
    Taxas: R$ <span class="valor" id="bvgn-taxas">0,00</span>
  </div>

  <div class="bvgn-subtotal">
    Subtotal: R$ <span class="valor" id="bvgn-subtotal">0,00</span>
  </div>

  <div class="bvgn-total">
    <strong>Total: R$ <span class="valor" id="bvgn-total">0,00</span></strong>
  </div>

  <!-- Valores crus para o JS (números em formato ponto para cálculo) -->
  <input type="hidden" id="bvgn-days-raw" value="0">
  <input type="hidden" id="bvgn-taxas-raw" value="0">
  <input type="hidden" id="bvgn-subtotal-raw" value="0">
  <input type="hidden" id="bvgn-total-raw" value="0">
</div>
