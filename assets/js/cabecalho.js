function bvgnParseISO(iso) {
  var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || '').trim());
  if (!m) return null;
  var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  return isNaN(d.getTime()) ? null : d;
}

function bvgnIsSundayDate(d) {
  return !!d && d.getDay && d.getDay() === 0;
}

function bvgnNextNonSunday(dateLike) {
  var d = new Date(dateLike);
  if (isNaN(d.getTime())) return null;
  while (d.getDay() === 0) d.setDate(d.getDate() + 1);
  return d;
}

function bvgnEnsureDateFeedback(inputEl) {
  if (!inputEl) return null;
  var wrap = inputEl.closest('.bvgn-campo') || inputEl.closest('label') || inputEl.parentElement;
  if (!wrap) return null;
  var msg = wrap.querySelector('.bvgn-date-feedback');
  if (!msg) {
    msg = document.createElement('div');
    msg.className = 'bvgn-date-feedback';
    msg.setAttribute('aria-live', 'polite');
    wrap.appendChild(msg);
  }
  return msg;
}

function bvgnShowSundayMsg(inputEl) {
  var msg = bvgnEnsureDateFeedback(inputEl);
  if (!msg) return;
  msg.textContent = 'Domingos não têm atendimento.';
  msg.classList.add('on');
}

function bvgnClearDateMsg(inputEl) {
  var msg = bvgnEnsureDateFeedback(inputEl);
  if (!msg) return;
  msg.textContent = '';
  msg.classList.remove('on');
}

function bvgnClearPicker(fp, inputEl) {
  try { if (fp && typeof fp.clear === 'function') fp.clear(true); } catch(_){}
  if (inputEl) inputEl.value = '';
  if (fp && fp.altInput) fp.altInput.value = '';
  if (fp && fp.mobileInput) fp.mobileInput.value = '';
}

function bvgnValidateSundaySelected(fp, inputEl) {
  if (!fp || !fp.selectedDates || !fp.selectedDates.length) return true;
  if (bvgnIsSundayDate(fp.selectedDates[0])) {
    bvgnClearPicker(fp, inputEl);
    bvgnShowSundayMsg(inputEl);
    return false;
  }
  bvgnClearDateMsg(inputEl);
  return true;
}

function bvgnParseTypedDate(raw, fp) {
  var v = String(raw || '').trim();
  if (!v) return null;
  var d = null;
  if (fp && typeof fp.parseDate === 'function') {
    d = fp.parseDate(v, 'Y-m-d') || fp.parseDate(v, 'd/m/Y');
    if (d && !isNaN(d.getTime())) return d;
  }
  var br = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v);
  if (br) return new Date(Number(br[3]), Number(br[2]) - 1, Number(br[1]));
  return bvgnParseISO(v);
}

function bvgnAttachManualSundayGuard(fp, inputEl) {
  function validateRaw() {
    var values = [];
    if (fp && fp.mobileInput && fp.mobileInput.value) values.push(fp.mobileInput.value);
    if (fp && fp.altInput && fp.altInput.value) values.push(fp.altInput.value);
    if (fp && fp.input && fp.input.value) values.push(fp.input.value);
    if (inputEl && inputEl.value) values.push(inputEl.value);
    for (var i = 0; i < values.length; i++) {
      var d = bvgnParseTypedDate(values[i], fp);
      if (d && bvgnIsSundayDate(d)) {
        bvgnClearPicker(fp, inputEl);
        bvgnShowSundayMsg(inputEl);
        return;
      }
    }
    bvgnClearDateMsg(inputEl);
  }
  [inputEl, fp && fp.altInput, fp && fp.mobileInput].forEach(function(el){
    if (!el || !el.addEventListener) return;
    el.addEventListener('change', validateRaw);
    el.addEventListener('blur', validateRaw);
    el.addEventListener('input', validateRaw);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  console.log('[BVGN] DOM carregado, aguardando campos...');

  const checkFields = setInterval(() => {
    const inicioEl = document.getElementById('bvgn-cabecalho-inicio');
    const fimEl = document.getElementById('bvgn-cabecalho-fim');
    const btn = document.getElementById('bvgn-buscar-grupos');

    if (inicioEl && fimEl && btn) {
      clearInterval(checkFields);
      console.log('[BVGN] Campos encontrados:', { inicioEl, fimEl, btn });

      const hoje = new Date();
      const maxGlobal = new Date();
      // limitar 60 dias a partir de hoje
      maxGlobal.setDate(maxGlobal.getDate() + 60);
      // ISO local (YYYY-MM-DD) para evitar fuso/UTC no minDate
      function toLocalISO(d){
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const dd= String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
      }
      const hojeIso = toLocalISO(hoje);

      // Helper: pega valor ISO (YYYY-MM-DD) mesmo no mobile
      function getISOValue(el){
        if (!el) return '';
        try {
          var fp = el._flatpickr;
          if (fp && fp.selectedDates && fp.selectedDates.length) {
            return fp.formatDate(fp.selectedDates[0], 'Y-m-d');
          }
          // flatpickr mobile cria um input.flatpickr-mobile
          var mob = el.parentElement ? el.parentElement.querySelector('input.flatpickr-mobile') : null;
          if (mob && mob.value) {
            // browsers retornam YYYY-MM-DD nesse input
            return String(mob.value);
          }
          return el.value || '';
        } catch(_) { return el.value || ''; }
      }

      // Força sincronizar o valor nos 3 alvos do flatpickr (input, altInput e mobileInput)
      function syncFlatpickrDisplay(fp, iso){
        try {
          if (!fp) return;
          var fmtBR = null;
          if (iso) {
            var d = fp.parseDate(iso, 'Y-m-d');
            if (d) fmtBR = fp.formatDate(d, 'd/m/Y');
          }
          if (iso) fp.input.value = iso; // input base (hidden/readonly)
          if (fp.altInput && fmtBR) fp.altInput.value = fmtBR; // máscara visível desktop
          if (fp.mobileInput && iso) fp.mobileInput.value = iso; // nativo mobile
        } catch(_){}
      }

      try {
        flatpickr.localize(flatpickr.l10ns.pt);

        const fimPicker = flatpickr(fimEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hojeIso,
          maxDate: maxGlobal,
          disable: [function(date){ return date.getDay() === 0; }],
          // defaultDate: new Date(hoje.getTime() + 86400000)
          placeholder: 'Data...',
          monthSelectorType: 'static',
          onChange: function(selected){
            if (selected && selected.length && bvgnValidateSundaySelected(this, fimEl)) {
              var iso = this.formatDate(selected[0], 'Y-m-d');
              syncFlatpickrDisplay(this, iso);
            }
          },
          onValueUpdate: function(sel){
            if (sel && sel.length && bvgnValidateSundaySelected(this, fimEl)) {
              var iso = this.formatDate(sel[0], 'Y-m-d');
              syncFlatpickrDisplay(this, iso);
            }
          },
          onClose: function(){ bvgnValidateSundaySelected(this, fimEl); }
        });
        console.log('[BVGN] Flatpickr fim inicializado');

        const inicioPicker = flatpickr(inicioEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hojeIso,
          maxDate: maxGlobal,
          disable: [function(date){ return date.getDay() === 0; }],
          // defaultDate: hoje,
          placeholder: 'Data...',
          monthSelectorType: 'static',
          onChange: function (selectedDates) {
            if (selectedDates.length && bvgnValidateSundaySelected(this, inicioEl)) {
              var minFim = bvgnNextNonSunday(selectedDates[0]);
              fimPicker.set('minDate', minFim || selectedDates[0]);
              var iso = this.formatDate(selectedDates[0], 'Y-m-d');
              syncFlatpickrDisplay(this, iso);
              console.log('[BVGN] Data de início alterada:', selectedDates[0]);
            }
          },
          onValueUpdate: function(sel){
            if (sel && sel.length && bvgnValidateSundaySelected(this, inicioEl)) {
              var iso = this.formatDate(sel[0], 'Y-m-d');
              syncFlatpickrDisplay(this, iso);
            }
          },
          onClose: function(){ bvgnValidateSundaySelected(this, inicioEl); }
        });
        bvgnAttachManualSundayGuard(inicioPicker, inicioEl);
        bvgnAttachManualSundayGuard(fimPicker, fimEl);
        console.log('[BVGN] Flatpickr início inicializado');

        // Pré-preenche a partir do localStorage, se existir
        try {
          const agRaw = localStorage.getItem('bvgn_agendamento');
          if (agRaw) {
            const ag = JSON.parse(agRaw);
            if (ag && ag.inicio && ag.fim) {
              // Define minDate do fim de acordo com o início salvo
              const s = bvgnParseISO(ag.inicio);
              const f = bvgnParseISO(ag.fim);
              if (s && !bvgnIsSundayDate(s)) {
                fimPicker.set('minDate', bvgnNextNonSunday(s));
                inicioPicker.setDate(ag.inicio, true);
                syncFlatpickrDisplay(inicioPicker, ag.inicio);
              }
              if (f && !bvgnIsSundayDate(f)) {
                fimPicker.setDate(ag.fim, true);
                syncFlatpickrDisplay(fimPicker, ag.fim);
              }
              console.log('[BVGN] Datas restauradas do storage:', ag);
              // Reforço: alguns temas atrasam o altInput; reaplica se ficar vazio
              setTimeout(() => {
                try {
                  const alt = inicioPicker && inicioPicker.altInput ? inicioPicker.altInput.value : '';
                  if ((!inicioEl.value && !alt) && ag.inicio) {
                    inicioPicker.setDate(ag.inicio, true);
                    syncFlatpickrDisplay(inicioPicker, ag.inicio);
                  }
                } catch(_) {}
              }, 180);
            }
          }
        } catch (err) {
          console.warn('[BVGN] Não foi possível restaurar datas salvas:', err);
        }
      } catch (e) {
        console.error('[BVGN] Erro ao inicializar o Flatpickr:', e);
      }

      btn.addEventListener('click', () => {
        const inicioISO = getISOValue(inicioEl);
        const fimISO = getISOValue(fimEl);
        const inicio = new Date(inicioISO);
        const fim = new Date(fimISO);
        const local = document.getElementById('bvgn-cabecalho-local').textContent || 'BV Locadora, Avenida Brigadeiro Eduardo Gomes, 3571';

        if (!inicioISO || !fimISO) {
          alert('Preencha as duas datas.');
          return;
        }
        if (bvgnIsSundayDate(bvgnParseISO(inicioISO))) {
          bvgnClearPicker(inicioEl._flatpickr, inicioEl);
          bvgnShowSundayMsg(inicioEl);
          return;
        }
        if (bvgnIsSundayDate(bvgnParseISO(fimISO))) {
          bvgnClearPicker(fimEl._flatpickr, fimEl);
          bvgnShowSundayMsg(fimEl);
          return;
        }

        const diffMs = fim - inicio;
        const dias = diffMs / (1000 * 60 * 60 * 24);

        if (dias < 0) {
          alert('A data final deve ser depois da data inicial.');
          return;
        }

        // Sempre persiste as datas antes de redirecionar (inclusive > 30)
        localStorage.setItem('bvgn_agendamento', JSON.stringify({
          inicio: inicioISO,
          fim: fimISO,
          local: local
        }));

        if (dias > 30) {
          window.location.href = '/planos-mensais';
          return;
        }

        window.location.href = '/planos-diarios';
      });
    }
  }, 100);
});

// Inicialização para o bloco MOBILE (IDs com sufixo -mb)
document.addEventListener('DOMContentLoaded', function () {
  const checkFieldsMb = setInterval(() => {
    const inicioEl = document.getElementById('bvgn-cabecalho-inicio-mb');
    const fimEl    = document.getElementById('bvgn-cabecalho-fim-mb');
    const btn      = document.getElementById('bvgn-buscar-grupos-mb');
    const localEl  = document.getElementById('bvgn-cabecalho-local-mb');
    if (inicioEl && fimEl && btn) {
      clearInterval(checkFieldsMb);

      const hoje = new Date();
      const maxGlobal = new Date();
      maxGlobal.setMonth(maxGlobal.getMonth() + 6);

      // Helper: pega valor ISO (YYYY-MM-DD) mesmo no mobile
      function getISOValue(el){
        if (!el) return '';
        try {
          var fp = el._flatpickr;
          if (fp && fp.selectedDates && fp.selectedDates.length) {
            return fp.formatDate(fp.selectedDates[0], 'Y-m-d');
          }
          var mob = el.parentElement ? el.parentElement.querySelector('input.flatpickr-mobile') : null;
          if (mob && mob.value) return String(mob.value);
          return el.value || '';
        } catch(_) { return el.value || ''; }
      }

      try {
        flatpickr.localize(flatpickr.l10ns.pt);

        const fimPicker = flatpickr(fimEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: 'today',
          maxDate: maxGlobal,
          monthSelectorType: 'static',
          disable: [function(date){ return date.getDay() === 0; }],
          onClose: function(){ bvgnValidateSundaySelected(this, fimEl); },
          onValueUpdate: function(){ bvgnValidateSundaySelected(this, fimEl); }
        });

        const inicioPicker = flatpickr(inicioEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: 'today',
          maxDate: maxGlobal,
          disable: [function(date){ return date.getDay() === 0; }],
          monthSelectorType: 'static',
          onChange: function (selectedDates) {
            if (selectedDates.length && bvgnValidateSundaySelected(this, inicioEl)) {
              var minFim = bvgnNextNonSunday(selectedDates[0]);
              fimPicker.set('minDate', minFim || selectedDates[0]);
            }
          },
          onClose: function(){ bvgnValidateSundaySelected(this, inicioEl); },
          onValueUpdate: function(){ bvgnValidateSundaySelected(this, inicioEl); }
        });
        bvgnAttachManualSundayGuard(inicioPicker, inicioEl);
        bvgnAttachManualSundayGuard(fimPicker, fimEl);

        // Restaura datas salvas
        try {
          const agRaw = localStorage.getItem('bvgn_agendamento');
          if (agRaw) {
            const ag = JSON.parse(agRaw);
            if (ag && ag.inicio && ag.fim) {
              const s = bvgnParseISO(ag.inicio);
              const f = bvgnParseISO(ag.fim);
              if (s && !bvgnIsSundayDate(s)) {
                fimPicker.set('minDate', bvgnNextNonSunday(s));
                inicioPicker.setDate(ag.inicio, true);
              }
              if (f && !bvgnIsSundayDate(f)) {
                fimPicker.setDate(ag.fim, true);
              }
              setTimeout(() => {
                try {
                  const alt = inicioPicker && inicioPicker.altInput ? inicioPicker.altInput.value : '';
                  if ((!inicioEl.value && !alt) && ag.inicio) {
                    inicioPicker.setDate(ag.inicio, true);
                  }
                } catch(_) {}
              }, 180);
            }
          }
        } catch (_) {}
      } catch (e) {
        console.error('[BVGN][MB] Erro ao inicializar o Flatpickr:', e);
      }

      btn.addEventListener('click', () => {
        const inicioISO = getISOValue(inicioEl);
        const fimISO = getISOValue(fimEl);
        const inicio = new Date(inicioISO);
        const fim = new Date(fimISO);
        const local = (localEl && localEl.textContent) ? localEl.textContent : 'BV Locadora, Avenida Brigadeiro Eduardo Gomes, 3571';

        if (!inicioISO || !fimISO) { alert('Preencha as duas datas.'); return; }
        if (bvgnIsSundayDate(bvgnParseISO(inicioISO))) {
          bvgnClearPicker(inicioEl._flatpickr, inicioEl);
          bvgnShowSundayMsg(inicioEl);
          return;
        }
        if (bvgnIsSundayDate(bvgnParseISO(fimISO))) {
          bvgnClearPicker(fimEl._flatpickr, fimEl);
          bvgnShowSundayMsg(fimEl);
          return;
        }
        const dias = (fim - inicio) / 86400000;
        if (dias < 0) { alert('A data final deve ser depois da data inicial.'); return; }

        // Persistir SEMPRE antes do redirecionamento
        localStorage.setItem('bvgn_agendamento', JSON.stringify({
          inicio: inicioISO,
          fim: fimISO,
          local: local
        }));

        if (dias > 30) { window.location.href = '/planos-mensais'; return; }

        window.location.href = '/planos-diarios';
      });
    }
  }, 100);
});
