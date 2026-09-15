(function (window) {
  'use strict';
  const daysList = [1, 3, 7, 15];
  const money = value => value === null ? 'Indisponível' : value.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
  function parseDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
    const [y, m, d] = value.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    return date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d ? date : null;
  }
  function iso(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }
  function baseForDays(base, days) {
    let fallback = null;
    for (const rule of base) {
      if (days >= rule.min && days <= rule.max) return Number(rule.price);
      if (!fallback || rule.max > fallback.max) fallback = rule;
    }
    return fallback ? Number(fallback.price) : null;
  }
  // Run the SAME public engine against immutable historical inputs, never today's settings.
  function evaluate(state, group, start) {
    const date = parseDate(start);
    if (!date) return null;
    const previous = window.BVGN;
    window.BVGN = {dynamicTariffs: state.rules};
    try {
      const winner = window.BVGN_Dynamic._helpers.pickRuleForDate(date, group);
      const values = daysList.map(days => {
        const base = baseForDays(state.base, days);
        if (base === null) return null;
        const end = new Date(date.getTime());
        end.setDate(end.getDate() + days);
        const dynamic = window.BVGN_Dynamic.calcularTarifaDinamica(base, start, iso(end), days, group);
        return Number((base * days + Number(dynamic.extra || 0)).toFixed(2));
      });
      return {values, winner};
    } finally {
      if (previous === undefined) delete window.BVGN;
      else window.BVGN = previous;
    }
  }
  function reference(context) {
    const rule = context.afterRule || context.beforeRule;
    if (rule.type !== 'week_day') return rule.startDate;
    const date = parseDate(context.recordedDate);
    if (!date) return '';
    date.setDate(date.getDate() + (Number(rule.weekday) - date.getDay() + 7) % 7);
    return iso(date);
  }
  function period(rule) {
    if (!rule) return 'Ausente';
    const fmt = value => parseDate(value) ? value.split('-').reverse().join('/') : (value || 'Não informada');
    if (rule.type === 'week_day') return 'Recorrente: ' + ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][rule.weekday]
      + (rule.endDate ? '; desativação após ' + fmt(rule.endDate) : '');
    return rule.type === 'single_date' ? fmt(rule.startDate) : `${fmt(rule.startDate)} até ${fmt(rule.endDate)}`;
  }
  function render(row) {
    try {
      const context = JSON.parse(row.dataset.bvgnHistory);
      const input = row.querySelector('[data-history-date]');
      const before = context.beforeRule, after = context.afterRule;
      row.querySelector('[data-history-period]').textContent = `${period(before)} → ${period(after)}`;
      const describe = rule => rule ? `${rule.label} (${rule.percent}%, prioridade ${rule.priority}; ${rule.active ? 'ativa' : 'inativa'}; grupos ${rule.groups.join(', ')})` : 'Ausente';
      row.querySelector('[data-history-rule]').textContent = `${describe(before)} → ${describe(after)}`;
      input.value = reference(context);
      const update = () => {
        const prior = evaluate(context.before, context.group, input.value);
        const current = evaluate(context.after, context.group, input.value);
        const cells = row.querySelectorAll('[data-history-value]');
        if (!prior || !current) {
          cells.forEach(cell => { cell.textContent = 'Informe uma retirada válida'; });
          row.querySelector('[data-history-winner]').textContent = '';
          return;
        }
        const describeWinner = result => result.winner ? `#${result.winner.id} ${result.winner.label}` : 'Geral';
        row.querySelector('[data-history-winner]').textContent = `Aplicada: ${describeWinner(prior)} → ${describeWinner(current)}`;
        cells.forEach((cell, index) => {
          cell.textContent = '';
          if (prior.values[index] !== current.values[index]) {
            cell.appendChild(document.createTextNode(money(prior.values[index]) + ' → '));
            const strong = document.createElement('strong');
            strong.textContent = money(current.values[index]);
            cell.appendChild(strong);
          } else cell.textContent = money(current.values[index]);
        });
      };
      input.addEventListener('change', update);
      update();
    } catch (error) {
      row.querySelectorAll('[data-history-value]').forEach(cell => { cell.textContent = 'Não foi possível ler o contexto preservado'; });
    }
  }
  window.BVGN_History = {evaluate, reference};
  if (typeof document !== 'undefined') document.querySelectorAll('[data-bvgn-history]').forEach(render);
})(window);
