(function(window){
  'use strict';

  function parseISODateLocal(str){
    if(!str) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if(!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]), 0, 0, 0, 0);
  }

  function dateToISO(d){
    if(!d) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const dd= String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`;
  }

  function getDynamicRules(){
    if (window.BVGN && Array.isArray(BVGN.dynamicTariffs)) return BVGN.dynamicTariffs;
    return [];
  }

  function matchRuleForDate(rule, dateObj, grupo){
    if (!rule || !(dateObj instanceof Date)) return false;
    if (rule.active === false) return false;
    if (Array.isArray(rule.groups) && rule.groups.length) {
      const g = String(grupo || '').toUpperCase();
      if (!g || !rule.groups.includes(g)) return false;
    }
    const type = rule.type || 'week_day';
    const d = new Date(dateObj.getTime());
    d.setHours(0,0,0,0);

    if (type === 'week_day') {
      return d.getDay() === Number(rule.weekday || 0);
    }

    if (type === 'single_date') {
      const target = parseISODateLocal(rule.startDate || rule.date || '');
      return target && target.getTime() === d.getTime();
    }

    if (type === 'date_range') {
      const start = parseISODateLocal(rule.startDate || '');
      const end   = parseISODateLocal(rule.endDate   || '');
      if (!start || !end) return false;
      start.setHours(0,0,0,0); end.setHours(0,0,0,0);
      return d.getTime() >= start.getTime() && d.getTime() <= end.getTime();
    }

    return false;
  }

  function pickRuleForDate(dateObj, grupo){
    const rules = getDynamicRules();
    if (!rules.length) return null;
    let chosen = null;
    rules.forEach(r => {
      if (matchRuleForDate(r, dateObj, grupo)) {
        if (!chosen || (Number(r.priority||0) > Number(chosen.priority||0))) {
          chosen = r;
        }
      }
    });
    return chosen;
  }

  function calcularTarifaDinamica(baseDia, inicioStr, fimStr, qtdDias, grupo){
    const rules = getDynamicRules();
    if (!rules.length) return { extra: 0, detalhes: [] };
    const s = parseISODateLocal(inicioStr);
    const e = parseISODateLocal(fimStr);
    if (!s || !e) return { extra: 0, detalhes: [] };

    const r = pickRuleForDate(s, grupo);
    if (!r || baseDia <= 0) return { extra: 0, detalhes: [] };

    const dias = (typeof qtdDias === 'number' && qtdDias > 0)
      ? Math.max(1, Math.floor(qtdDias))
      : Math.max(1, Math.round((e - s) / 86400000) + 1);

    const perc = Number(r.percent || 0);
    const addPorDia  = baseDia * (perc / 100);
    const acc        = addPorDia * dias;
    const detalhe = {
      data: dateToISO(s),
      rotulo: r.label || '',
      desc: r.desc || '',
      percent: perc,
      valor: acc,
      showResumo: !!r.showResumo,
      showPdf: !!r.showPdf
    };
    return { extra: acc, detalhes: [detalhe] };
  }

  window.BVGN_Dynamic = {
    calcularTarifaDinamica,
    _helpers: { getDynamicRules, matchRuleForDate, pickRuleForDate }
  };
})(window);
