
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
              // Reforço: alguns temas atrasam o altInput; reaplica se ficar vazio
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
        const local = document.getElementById('bvgn-cabecalho-local').textContent || 'BV Locadora, Rua Coronel Mota, 629';

        if (!inicioISO || !fimISO) {
          alert('Preencha as duas datas.');
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
        const local = (localEl && localEl.textContent) ? localEl.textContent : 'BV Locadora, Rua Coronel Mota, 629';

        if (!inicioISO || !fimISO) { alert('Preencha as duas datas.'); return; }
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
