
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
      maxGlobal.setMonth(maxGlobal.getMonth() + 6);

      try {
        flatpickr.localize(flatpickr.l10ns.pt);

        const fimPicker = flatpickr(fimEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hoje,
          maxDate: maxGlobal,
          defaultDate: new Date(hoje.getTime() + 86400000)
        });
        console.log('[BVGN] Flatpickr fim inicializado');

        flatpickr(inicioEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hoje,
          maxDate: maxGlobal,
          defaultDate: hoje,
          onChange: function (selectedDates) {
            if (selectedDates.length) {
              fimPicker.set('minDate', selectedDates[0]);
              console.log('[BVGN] Data de início alterada:', selectedDates[0]);
            }
          }
        });
        console.log('[BVGN] Flatpickr início inicializado');
      } catch (e) {
        console.error('[BVGN] Erro ao inicializar o Flatpickr:', e);
      }

      btn.addEventListener('click', () => {
        const inicio = new Date(inicioEl.value);
        const fim = new Date(fimEl.value);
        const local = document.getElementById('bvgn-cabecalho-local').textContent || 'Sede';

        if (!inicioEl.value || !fimEl.value) {
          alert('Preencha as duas datas.');
          return;
        }

        const diffMs = fim - inicio;
        const dias = diffMs / (1000 * 60 * 60 * 24);

        if (dias < 0) {
          alert('A data final deve ser depois da data inicial.');
          return;
        }

        if (dias > 30) {
          window.location.href = '/planos-mensais';
          return;
        }

        localStorage.setItem('bvgn_agendamento', JSON.stringify({
          inicio: inicioEl.value,
          fim: fimEl.value,
          local: local
        }));

        window.location.href = '/planos-diarios';
      });
    }
  }, 100);
});
