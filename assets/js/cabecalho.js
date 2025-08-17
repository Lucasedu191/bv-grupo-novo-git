document.addEventListener('DOMContentLoaded', function () {
  const inicioEl = document.getElementById('bvgn-inicio');
  const fimEl = document.getElementById('bvgn-fim');
  const btn = document.getElementById('bvgn-buscar-grupos');

  if (!btn || !inicioEl || !fimEl) return;

  const hoje = new Date();
  const max = new Date();
  max.setDate(hoje.getDate() + 30);

  // Inicializa primeiro o fim (porque o início depende dele)
const fimPicker = flatpickr(fimEl, {
  dateFormat: 'Y-m-d',
  minDate: hoje,
  maxDate: max,
  defaultDate: new Date(hoje.getTime() + 86400000), // amanhã
});

// Agora inicializa o início e usa o fimPicker corretamente
flatpickr(inicioEl, {
  dateFormat: 'Y-m-d',
  minDate: hoje,
  maxDate: max,
  defaultDate: hoje,
  onChange: function (selectedDates) {
    if (selectedDates.length) {
      fimPicker.set('minDate', selectedDates[0]);
    }
  }
});


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
