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
    <em>Plano Mensal: Não é necessário selecionar datas.</em>
  </div>
<?php endif; ?>
