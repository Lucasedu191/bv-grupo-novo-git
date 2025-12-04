<?php
$tipo = $a['type'] ?? 'diario';
?>
<div class="bvgn-totais-cotacao" data-bvgn-tipo="<?php echo esc_attr($tipo); ?>">

  <div class="bvgn-resumo">

    <div class="resumo-linha bvgn-var" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Plano selecionado:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-var-view">–</span>
    </div>

    <div class="resumo-linha bvgn-local" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Retirada em:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-local-view">–</span>
    </div>

    <div class="resumo-linha bvgn-dias" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Período:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-days-view">–</span>
    </div>

    <div class="resumo-linha bvgn-protecao" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Proteção:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-protecao-view">–</span>
    </div>

    <div class="resumo-linha bvgn-opcionais" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Serviços opcionais:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-opcionais-view">–</span>
    </div>

    <div class="resumo-linha bvgn-dyn" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Tarifa dinâmica:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-dyn-view">R$ 0,00</span>
    </div>

    <div class="resumo-linha bvgn-caucao-aviso" style="display:none;">
      <span class="resumo-label"><?php esc_html_e('Caução:', 'bvgn'); ?></span>
      <span class="resumo-valor" id="bvgn-caucao-view">R$ 0,00</span>
    </div>

    <!-- Linha de Taxas removida conforme requisito -->
    <!-- Linha de Subtotal removida conforme requisito -->

    <div class="resumo-linha total">
      <span class="resumo-label"><?php esc_html_e('Total Estimado:', 'bvgn'); ?></span>
      <span class="resumo-valor destaque">R$ <span class="valor" id="bvgn-total-view">0,00</span></span>
    </div>
  </div>

  <!-- Hidden para envio posterior -->
  <input type="hidden" id="bvgn-days-raw" name="bvgn_dias" value="1" />
  <input type="hidden" id="bvgn-taxas-raw" name="bvgn_taxas" value="0" />
  <input type="hidden" id="bvgn-subtotal-raw" name="bvgn_subtotal" value="0" />
  <input type="hidden" id="bvgn-total-raw" name="bvgn_total" value="0" />
  <input type="hidden" id="bvgn-caucao-raw" name="bvgn_caucao" value="0" />
  <input type="hidden" id="bvgn-dynamic-extra-raw" name="bvgn_dynamic_extra" value="0" />

</div>
