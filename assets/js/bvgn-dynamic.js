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

  function matchRuleForDate(rule, dateObj){
    if (!rule || !(dateObj instanceof Date)) return false;
    if (rule.active === false) return false;
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

  function pickRuleForDate(dateObj){
    const rules = getDynamicRules();
    if (!rules.length) return null;
    let chosen = null;
    rules.forEach(r => {
      if (matchRuleForDate(r, dateObj)) {
        if (!chosen || (Number(r.priority||0) > Number(chosen.priority||0))) {
          chosen = r;
        }
      }
    });
    return chosen;
  }

  function calcularTarifaDinamica(baseDia, inicioStr, fimStr){
    const rules = getDynamicRules();
    if (!rules.length) return { extra: 0, detalhes: [] };
    const s = parseISODateLocal(inicioStr);
    const e = parseISODateLocal(fimStr);
    if (!s || !e) return { extra: 0, detalhes: [] };

    let cursor = new Date(s.getTime());
    const detalhes = [];
    let acc = 0;

    while (cursor.getTime() <= e.getTime()) {
      const r = pickRuleForDate(cursor);
      const isInicio = cursor.getTime() === s.getTime();
      if (r && baseDia > 0) {
        if (r.type === 'week_day' && !isInicio) {
          cursor.setDate(cursor.getDate() + 1);
          continue;
        }

        const perc = Number(r.percent || 0);
        const add  = baseDia * (perc / 100);
        acc += add;
        detalhes.push({
          data: dateToISO(cursor),
          rotulo: r.label || '',
          desc: r.desc || '',
          percent: perc,
          valor: add,
          showResumo: !!r.showResumo,
          showPdf: !!r.showPdf
        });
      }
      cursor.setDate(cursor.getDate() + 1);
    }
    return { extra: acc, detalhes };
  }

  window.BVGN_Dynamic = {
    calcularTarifaDinamica,
    _helpers: { getDynamicRules, matchRuleForDate, pickRuleForDate }
  };
})(window);
