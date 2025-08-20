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

    <div class="resumo-linha">
      <span class="resumo-label"><?php esc_html_e('Taxas:', 'bvgn'); ?></span>
      <span class="resumo-valor">R$ <span class="valor" id="bvgn-taxas">0,00</span></span>
    </div>

    <div class="resumo-linha">
      <span class="resumo-label"><?php esc_html_e('Subtotal:', 'bvgn'); ?></span>
      <span class="resumo-valor">R$ <span class="valor" id="bvgn-subtotal">0,00</span></span>
    </div>

    <div class="resumo-linha total">
      <span class="resumo-label"><?php esc_html_e('Total:', 'bvgn'); ?></span>
      <span class="resumo-valor destaque">R$ <span class="valor" id="bvgn-total">0,00</span></span>
    </div>
  </div>

  <!-- Hidden para envio posterior -->
  <input type="hidden" id="bvgn-days-raw" name="bvgn_dias" value="1" />
  <input type="hidden" id="bvgn-taxas-raw" name="bvgn_taxas" value="0" />
  <input type="hidden" id="bvgn-subtotal-raw-hidden" name="bvgn_subtotal" value="0" />
  <input type="hidden" id="bvgn-total-raw-hidden" name="bvgn_total" value="0" />

</div>
