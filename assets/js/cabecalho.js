
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
          // defaultDate: new Date(hoje.getTime() + 86400000)
          placeholder: 'Data...',
          monthSelectorType: 'static'
        });
        console.log('[BVGN] Flatpickr fim inicializado');

        const inicioPicker = flatpickr(inicioEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hoje,
          maxDate: maxGlobal,
          // defaultDate: hoje,
          placeholder: 'Data...',
          monthSelectorType: 'static',
          onChange: function (selectedDates) {
            if (selectedDates.length) {
              fimPicker.set('minDate', selectedDates[0]);
              console.log('[BVGN] Data de início alterada:', selectedDates[0]);
            }
          }
        });
        console.log('[BVGN] Flatpickr início inicializado');

        // Pré-preenche a partir do localStorage, se existir
        try {
          const agRaw = localStorage.getItem('bvgn_agendamento');
          if (agRaw) {
            const ag = JSON.parse(agRaw);
            if (ag && ag.inicio && ag.fim) {
              // Define minDate do fim de acordo com o início salvo
              const s = new Date(ag.inicio);
              if (!isNaN(s)) fimPicker.set('minDate', s);
              // Aplica datas salvas
              inicioPicker.setDate(ag.inicio, true);
              fimPicker.setDate(ag.fim, true);
              console.log('[BVGN] Datas restauradas do storage:', ag);
            }
          }
        } catch (err) {
          console.warn('[BVGN] Não foi possível restaurar datas salvas:', err);
        }
      } catch (e) {
        console.error('[BVGN] Erro ao inicializar o Flatpickr:', e);
      }

      btn.addEventListener('click', () => {
        const inicio = new Date(inicioEl.value);
        const fim = new Date(fimEl.value);
        const local = document.getElementById('bvgn-cabecalho-local').textContent || 'BV Locadora, Rua Coronel Mota, 629';

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

      try {
        flatpickr.localize(flatpickr.l10ns.pt);

        const fimPicker = flatpickr(fimEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hoje,
          maxDate: maxGlobal,
          monthSelectorType: 'static'
        });

        const inicioPicker = flatpickr(inicioEl, {
          altInput: true,
          altFormat: 'd/m/Y',
          dateFormat: 'Y-m-d',
          minDate: hoje,
          maxDate: maxGlobal,
          monthSelectorType: 'static',
          onChange: function (selectedDates) {
            if (selectedDates.length) fimPicker.set('minDate', selectedDates[0]);
          }
        });

        // Restaura datas salvas
        try {
          const agRaw = localStorage.getItem('bvgn_agendamento');
          if (agRaw) {
            const ag = JSON.parse(agRaw);
            if (ag && ag.inicio && ag.fim) {
              const s = new Date(ag.inicio);
              if (!isNaN(s)) fimPicker.set('minDate', s);
              inicioPicker.setDate(ag.inicio, true);
              fimPicker.setDate(ag.fim, true);
            }
          }
        } catch (_) {}
      } catch (e) {
        console.error('[BVGN][MB] Erro ao inicializar o Flatpickr:', e);
      }

      btn.addEventListener('click', () => {
        const inicio = new Date(inicioEl.value);
        const fim = new Date(fimEl.value);
        const local = (localEl && localEl.textContent) ? localEl.textContent : 'BV Locadora, Rua Coronel Mota, 629';

        if (!inicioEl.value || !fimEl.value) { alert('Preencha as duas datas.'); return; }
        const dias = (fim - inicio) / 86400000;
        if (dias < 0) { alert('A data final deve ser depois da data inicial.'); return; }

        if (dias > 30) { window.location.href = '/planos-mensais'; return; }

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
