(function(){
  function toLocalISO(d){
    var y = d.getFullYear();
    var m = String(d.getMonth()+1).padStart(2,'0');
    var dd= String(d.getDate()).padStart(2,'0');
    return y+'-'+m+'-'+dd;
  }
  function isSundayDate(d){
    return !!d && d.getDay && d.getDay() === 0;
  }
  function nextNonSunday(dateLike){
    var d = new Date(dateLike);
    if (isNaN(d.getTime())) return null;
    while (d.getDay() === 0) d.setDate(d.getDate() + 1);
    return d;
  }
  function parseISO(iso){
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || '').trim());
    if (!m) return null;
    var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return isNaN(d.getTime()) ? null : d;
  }
  function ensureDateFeedback(inputEl){
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
  function showSundayMsg(inputEl){
    var msg = ensureDateFeedback(inputEl);
    if (!msg) return;
    msg.textContent = 'Domingos não têm atendimento.';
    msg.classList.add('on');
  }
  function clearDateMsg(inputEl){
    var msg = ensureDateFeedback(inputEl);
    if (!msg) return;
    msg.textContent = '';
    msg.classList.remove('on');
  }
  function clearPicker(fp, inputEl){
    try { if (fp && typeof fp.clear === 'function') fp.clear(true); } catch(_){}
    if (inputEl) inputEl.value = '';
    if (fp && fp.altInput) fp.altInput.value = '';
    if (fp && fp.mobileInput) fp.mobileInput.value = '';
  }
  function validateSundaySelected(fp, inputEl){
    if (!fp || !fp.selectedDates || !fp.selectedDates.length) return true;
    if (isSundayDate(fp.selectedDates[0])) {
      clearPicker(fp, inputEl);
      showSundayMsg(inputEl);
      return false;
    }
    clearDateMsg(inputEl);
    return true;
  }
  function parseTypedDate(raw, fp){
    var v = String(raw || '').trim();
    if (!v) return null;
    var d = null;
    if (fp && typeof fp.parseDate === 'function') {
      d = fp.parseDate(v, 'Y-m-d') || fp.parseDate(v, 'd/m/Y');
      if (d && !isNaN(d.getTime())) return d;
    }
    var br = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v);
    if (br) return new Date(Number(br[3]), Number(br[2]) - 1, Number(br[1]));
    return parseISO(v);
  }
  function attachManualSundayGuard(fp, inputEl){
    function validateRaw(){
      var values = [];
      if (fp && fp.mobileInput && fp.mobileInput.value) values.push(fp.mobileInput.value);
      if (fp && fp.altInput && fp.altInput.value) values.push(fp.altInput.value);
      if (fp && fp.input && fp.input.value) values.push(fp.input.value);
      if (inputEl && inputEl.value) values.push(inputEl.value);
      for (var i = 0; i < values.length; i++) {
        var d = parseTypedDate(values[i], fp);
        if (d && isSundayDate(d)) {
          clearPicker(fp, inputEl);
          showSundayMsg(inputEl);
          return;
        }
      }
      clearDateMsg(inputEl);
    }
    [inputEl, fp && fp.altInput, fp && fp.mobileInput].forEach(function(el){
      if (!el || !el.addEventListener) return;
      el.addEventListener('change', validateRaw);
      el.addEventListener('blur', validateRaw);
      el.addEventListener('input', validateRaw);
    });
  }
  function syncFlatpickrDisplay(fp, iso){
    try {
      if (!fp) return;
      var fmtBR = null;
      if (iso) {
        var d = fp.parseDate(iso, 'Y-m-d');
        if (d) fmtBR = fp.formatDate(d, 'd/m/Y');
      }
      if (iso) fp.input.value = iso;
      if (fp.altInput && fmtBR) fp.altInput.value = fmtBR;
      if (fp.mobileInput && iso) fp.mobileInput.value = iso;
    } catch(_){}
  }

  function initFor(container){
    var inicio = container.querySelector('.bvgn-data-inicio');
    var fim    = container.querySelector('.bvgn-data-fim');
    if (!inicio || !window.flatpickr) return false; // permite containers sem 'fim' (mensal)

    // Evita reinicializar
    if (inicio.classList.contains('fp-inited') || fim.classList.contains('fp-inited')) return true;

    var hoje = new Date();
    var maxG = new Date();
    // limitar para 60 dias a partir de hoje
    maxG.setDate(maxG.getDate() + 60);
    var hojeIso = toLocalISO(hoje);

    try {
      if (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.pt) {
        flatpickr.localize(flatpickr.l10ns.pt);
      }

      var fimPicker = null;
      if (fim) {
        fimPicker = flatpickr(fim, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hojeIso,
          maxDate: maxG,
          disable: [function(date){ return date.getDay() === 0; }],
          monthSelectorType: 'static',
          onChange: function(sel){ if (sel && sel.length && validateSundaySelected(this, fim)) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); },
          onValueUpdate: function(sel){ if (sel && sel.length && validateSundaySelected(this, fim)) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); },
          onClose: function(){ validateSundaySelected(this, fim); }
        });
        attachManualSundayGuard(fimPicker, fim);
      }

      var inicioPicker = flatpickr(inicio, {
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
        minDate: hojeIso,
        maxDate: maxG,
        disable: [function(date){ return date.getDay() === 0; }],
        monthSelectorType: 'static',
        onChange: function(sel){
          if (sel.length && validateSundaySelected(this, inicio) && fimPicker) {
            var minFim = nextNonSunday(sel[0]);
            fimPicker.set('minDate', minFim || sel[0]);
          }
          if (sel && sel.length && validateSundaySelected(this, inicio)) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d'));
        },
        onValueUpdate: function(sel){ if (sel && sel.length && validateSundaySelected(this, inicio)) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); },
        onClose: function(){ validateSundaySelected(this, inicio); }
      });
      attachManualSundayGuard(inicioPicker, inicio);

      // Restaura valores do localStorage (se existirem)
      try {
        var raw = localStorage.getItem('bvgn_agendamento');
        if (raw) {
          var ag = JSON.parse(raw);
          if (ag && ag.inicio && ag.fim) {
            var s = parseISO(ag.inicio);
            var f = parseISO(ag.fim);
            if (s && !isSundayDate(s)) {
              if (fimPicker) fimPicker.set('minDate', nextNonSunday(s));
              inicioPicker.setDate(ag.inicio, true);
              syncFlatpickrDisplay(inicioPicker, ag.inicio);
            }
            if (fimPicker && f && !isSundayDate(f)) {
              fimPicker.setDate(ag.fim, true);
              syncFlatpickrDisplay(fimPicker, ag.fim);
            }
      }
        }
      } catch(_){}

      inicio.classList.add('fp-inited');
      if (fim) fim.classList.add('fp-inited');
      return true;
    } catch(e){
      console.warn('[BVGN] Falha ao iniciar Flatpickr no agendamento da página.', e);
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    var timer = setInterval(function(){
      var ok = false;
      document.querySelectorAll('.bvgn-agendamento').forEach(function(cx){
        if (initFor(cx)) ok = true;
      });
      if (ok) clearInterval(timer);
    }, 120);
  });
})();
