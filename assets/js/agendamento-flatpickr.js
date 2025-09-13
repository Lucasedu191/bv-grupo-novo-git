(function(){
  function toLocalISO(d){
    var y = d.getFullYear();
    var m = String(d.getMonth()+1).padStart(2,'0');
    var dd= String(d.getDate()).padStart(2,'0');
    return y+'-'+m+'-'+dd;
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
    maxG.setMonth(maxG.getMonth() + 6);
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
          monthSelectorType: 'static',
          onChange: function(sel){ if (sel && sel.length) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); },
          onValueUpdate: function(sel){ if (sel && sel.length) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); }
        });
      }

      var inicioPicker = flatpickr(inicio, {
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
        minDate: hojeIso,
        maxDate: maxG,
        monthSelectorType: 'static',
        onChange: function(sel){
          if (sel.length && fimPicker) fimPicker.set('minDate', sel[0]);
          if (sel && sel.length) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d'));
        },
        onValueUpdate: function(sel){ if (sel && sel.length) syncFlatpickrDisplay(this, this.formatDate(sel[0],'Y-m-d')); }
      });

      // Restaura valores do localStorage (se existirem)
      try {
        var raw = localStorage.getItem('bvgn_agendamento');
        if (raw) {
          var ag = JSON.parse(raw);
          if (ag && ag.inicio && ag.fim) {
            if (fimPicker) fimPicker.set('minDate', ag.inicio);
            inicioPicker.setDate(ag.inicio, true);
            if (fimPicker && ag.fim) fimPicker.setDate(ag.fim, true);
            syncFlatpickrDisplay(inicioPicker, ag.inicio);
            if (fimPicker && ag.fim) syncFlatpickrDisplay(fimPicker, ag.fim);
      }
        }
      } catch(_){}

      inicio.classList.add('fp-inited');
      fim.classList.add('fp-inited');
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

