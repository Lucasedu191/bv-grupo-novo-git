document.addEventListener('DOMContentLoaded', function () {
  console.log('[BVGN] DOM carregado');

  const inicioEl = document.getElementById('bvgn-inicio');
  const fimEl = document.getElementById('bvgn-fim');
  const btn = document.getElementById('bvgn-buscar-grupos');

  console.log('[BVGN] Campos encontrados:', { inicioEl, fimEl, btn });

  if (!btn || !inicioEl || !fimEl) {
    console.warn('[BVGN] Algum campo obrigatório não foi encontrado.');
    return;
  }

  const hoje = new Date();
  const max = new Date();
  max.setDate(hoje.getDate() + 30);

  try {
    // Inicializa fim primeiro
    const fimPicker = flatpickr(fimEl, {
      dateFormat: 'Y-m-d',
      minDate: hoje,
      maxDate: max,
      defaultDate: new Date(hoje.getTime() + 86400000),
    });
    console.log('[BVGN] Flatpickr fim inicializado');

    // Depois o início
    flatpickr(inicioEl, {
      dateFormat: 'Y-m-d',
      minDate: hoje,
      maxDate: max,
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
    const local = document.getElementById('bvgn-local').value;

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
});
