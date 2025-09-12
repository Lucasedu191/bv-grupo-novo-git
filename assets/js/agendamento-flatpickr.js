(function(){
  function parseISODateLocal(str){
    if(!str) return null;
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(str));
    if(!m) return null;
    return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]), 0, 0, 0, 0);
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
          minDate: hoje,
          maxDate: maxG,
          monthSelectorType: 'static'
        });
      }

      var inicioPicker = flatpickr(inicio, {
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
        minDate: hoje,
        maxDate: maxG,
        monthSelectorType: 'static',
        onChange: function(sel){ if (sel.length && fimPicker) fimPicker.set('minDate', sel[0]); }
      });

      // Restaura valores do localStorage (se existirem)
      try {
        var raw = localStorage.getItem('bvgn_agendamento');
        if (raw) {
          var ag = JSON.parse(raw);
          if (ag && ag.inicio && ag.fim) {
            var s = parseISODateLocal(ag.inicio);
            if (s && fimPicker) fimPicker.set('minDate', s);
            // usa setDate para garantir atualização do altInput
            inicioPicker.setDate(ag.inicio, true);
            if (fimPicker && ag.fim) fimPicker.setDate(ag.fim, true);
            // reforço pós-init (alguns temas atrasam o altInput)
            setTimeout(function(){
              try {
                if (inicio && inicio._flatpickr && (!inicio.value || (inicio._flatpickr.altInput && !inicio._flatpickr.altInput.value))) {
                  inicio._flatpickr.setDate(ag.inicio, true);
                }
              } catch(_){}
            }, 180);
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

