(function(){
  function initFor(container){
    var inicio = container.querySelector('.bvgn-data-inicio');
    var fim    = container.querySelector('.bvgn-data-fim');
    if (!inicio || !fim || !window.flatpickr) return false;

    // Evita reinicializar
    if (inicio.classList.contains('fp-inited') || fim.classList.contains('fp-inited')) return true;

    var hoje = new Date();
    var maxG = new Date();
    maxG.setMonth(maxG.getMonth() + 6);

    try {
      if (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.pt) {
        flatpickr.localize(flatpickr.l10ns.pt);
      }

      var fimPicker = flatpickr(fim, {
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
        minDate: hoje,
        maxDate: maxG,
        monthSelectorType: 'static'
      });

      var inicioPicker = flatpickr(inicio, {
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
        minDate: hoje,
        maxDate: maxG,
        monthSelectorType: 'static',
        onChange: function(sel){ if (sel.length) fimPicker.set('minDate', sel[0]); }
      });

      // Restaura valores do localStorage (se existirem)
      try {
        var raw = localStorage.getItem('bvgn_agendamento');
        if (raw) {
          var ag = JSON.parse(raw);
          if (ag && ag.inicio && ag.fim) {
            var s = new Date(ag.inicio);
            if (!isNaN(s)) fimPicker.set('minDate', s);
            inicioPicker.setDate(ag.inicio, true);
            fimPicker.setDate(ag.fim, true);
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

