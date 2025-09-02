<?php
$tipo = $a['type'] ?? 'diario';
?>
<?php if ($tipo === 'diario'): ?>
  <div class="bvgn-agendamento bvgn-agendamento--diaria" data-bvgn-tipo="diario">
    <label>
      Retirada:
      <input type="date" id="bv-date-start" class="bvgn-data-inicio">
    </label>
    <label>
      Devolução:
      <input type="date" id="bv-date-end" class="bvgn-data-fim">
    </label>
    <div id="bv-date-msg" class="bv-date-msg" aria-live="polite"></div>
  </div>
  <div id="bv-date-msg" class="bvgn-msg"></div>
<?php else: ?>
  <div class="bvgn-agendamento bvgn-agendamento--mensal" data-bvgn-tipo="mensal">
    <label>
      Data de Retirada :
      <input type="date" id="bv-date-start-mensal" class="bvgn-data-inicio" placeholder="Selecione a data">
    </label>
    <div class="bvgn-tip" style="font-size:12px;color:#555;margin-top:6px;">
      Informativo: não altera valores. A devolução é considerada como 30 dias.
    </div>
  </div>
<?php endif; ?>
