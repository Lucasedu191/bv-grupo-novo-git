(function (window, $) {
  'use strict';

  // Namespace
  window.BVGN = window.BVGN || {};

  // ===== Constantes =====
  const BVGN_MAX_DIAS_ABSOLUTO = 30;

  // ===== Helpers =====
  function numero(v) { return Number(String(v).replace(',', '.')) || 0; }

  function parseISODateLocal(str){
    if(!str) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if(!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]), 0, 0, 0, 0);
  }

  function diferencaDiasSeguro(inicioStr, fimStr){
    const s = parseISODateLocal(inicioStr);
    const e = parseISODateLocal(fimStr);
    if(!s || !e) return 0;
    const ms = e.setHours(0,0,0,0) - s.setHours(0,0,0,0);
    return Math.max(Math.floor(ms / 86400000), 1);
  }

  function dateToISO(d){
    if(!d) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const dd= String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`;
  }

  function getTipo($cx){
    const $bloco = $cx.find('[data-bvgn-tipo]').first();
    const tipoRaw = $bloco.attr('data-bvgn-tipo');
    return (tipoRaw || 'diario');
  }

  function getRulesFromVar($cx){
    const $checked = $cx.find('.bvgn-variacao input[type=radio]:checked');
    const minRaw = $checked.attr('data-min-days');
    const maxRaw = $checked.attr('data-max-days');
    let minDays = parseInt(minRaw, 10);
    let maxDays = parseInt(maxRaw, 10);
    if (!Number.isInteger(minDays) || minDays < 1) minDays = 1;
    if (!Number.isInteger(maxDays) || maxDays < minDays) maxDays = minDays;
    if (maxDays > BVGN_MAX_DIAS_ABSOLUTO) maxDays = BVGN_MAX_DIAS_ABSOLUTO;
    return { minDays, maxDays };
  }

  function setMsg($cx, txt){
    const $m = $cx.find('#bv-date-msg');
    if(!$m.length) return;
    if(txt){ $m.html(txt).addClass('on'); }
    else { $m.text('').removeClass('on'); }
  }

  // Expor no namespace
  BVGN.constants = { BVGN_MAX_DIAS_ABSOLUTO };
  BVGN.utils = { numero, parseISODateLocal, diferencaDiasSeguro, dateToISO, getTipo, getRulesFromVar, setMsg };

})(window, jQuery);
