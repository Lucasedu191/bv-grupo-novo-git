(function (window, $) {
  'use strict';

  const { parseISODateLocal, dateToISO, diferencaDiasSeguro, getTipo, getRulesFromVar, setMsg } = BVGN.utils;

  function normalizeDatesToRule($cx){
    const tipo = getTipo($cx);
    if(tipo !== 'diario') { setMsg($cx, ''); return false; }

    const { minDays, maxDays } = getRulesFromVar($cx);
    const $s = $cx.find('.bvgn-data-inicio');
    const $e = $cx.find('.bvgn-data-fim');
    const sVal = $s.val(), eVal = $e.val();

    if (!sVal || !eVal) { setMsg($cx, ''); return false; }

    // corrige fim < início
    const s = parseISODateLocal(sVal);
    const e = parseISODateLocal(eVal);
    if(e < s){ $e.val(dateToISO(s)); }

    // (mensagem foi simplificada)
    setMsg($cx, '');
    return false;
  }

  function aplicarRegrasECalcular($cx){
    const tipo = getTipo($cx);
    const $wrapAg = $cx.find('.bvgn-agendamento');
    if(tipo === 'mensal'){
      if($wrapAg.length){ $wrapAg.hide(); setMsg($cx, ''); }
      BVGN.calcular($cx);
      return;
    } else {
      if($wrapAg.length){ $wrapAg.show(); }
      normalizeDatesToRule($cx);
    }
    BVGN.calcular($cx);
  }

  function updateVarDesc($cx){
    const $box = $cx.find('.bvgn-variacao-desc');
    const $txt = $cx.find('.bvgn-variacao-desc-text');
    if(!$box.length || !$txt.length) return;
    const $sel = $cx.find('input[name="bvgn_variacao"]:checked');
    const desc = ($sel.data('desc') || '').trim();
    if(desc){ $txt.text(desc); $box.show(); }
    else { $txt.text(''); $box.hide(); }
  }

  function ligarEventos($cx){
    // Mudanças que afetam total
    $cx.on('change input', '.bvgn-variacao input, .bvgn-taxa input, .bvgn-data-inicio, .bvgn-data-fim', function(e){
      const $t = $(e.target);

      // Destaque visual
      if ($t.is('.bvgn-taxa input')) {
        const isRadio = $t.attr('type') === 'radio';
        const name = $t.attr('name');
        if (isRadio) {
          $cx.find(`input[name="${name}"]`).each(function () {
            $(this).closest('.bvgn-taxa').removeClass('selected');
          });
        }
        if ($t.is(':checked')) $t.closest('.bvgn-taxa').addClass('selected');
        else $t.closest('.bvgn-taxa').removeClass('selected');
      }

      if($t.is('.bvgn-variacao input')){
        aplicarRegrasECalcular($cx);
        updateVarDesc($cx);
        return;
      }

      if($t.is('.bvgn-data-inicio, .bvgn-data-fim')){
        normalizeDatesToRule($cx);
        BVGN.calcular($cx);
        return;
      }

      BVGN.calcular($cx);
      updateVarDesc($cx);
    });

    // Lógica personalizada para produtos do tipo diário
    const tipo = getTipo($cx);
    if (tipo === 'diario') {
      const $variacoes = $cx.find('.bvgn-variacao');
      $variacoes.hide();

      $cx.on('change input', '.bvgn-data-inicio, .bvgn-data-fim', function() {
        const s = $cx.find('.bvgn-data-inicio').val();
        const e = $cx.find('.bvgn-data-fim').val();
        if (!s || !e) return;

        $cx.find('.bvgn-variacao input[type=radio]').prop('checked', false);

        const dias = diferencaDiasSeguro(s, e);

        // Escolhe variação compatível
        const $inputs = $cx.find('.bvgn-variacao input[type=radio]');
        let selecionado = false;
        $inputs.each(function(){
          const min = parseInt($(this).data('min-days') || 1, 10);
          const max = parseInt($(this).data('max-days') || min, 10);
          if (dias >= min && dias <= max) {
            $(this).prop('checked', true).trigger('change');
            selecionado = true;
            return false;
          }
        });

        if (!selecionado) {
          if (dias > 30) {
            setMsg($cx, 'O período máximo para agendamentos diários é de 30 dias. Para prazos maiores, <a href="/planos-mensais">acesse os grupos mensais</a>.');
          } else {
            setMsg($cx, `Nenhum plano cobre ${dias} dias. Tente ajustar o período ou acesse os grupos mensais.`);
          }
        } else {
          setMsg($cx, `Plano selecionado automaticamente para ${dias} dias.`);
        }
      });
    }

    // Botão cotação (whatsapp + modal)
    $cx.on('click', '.bvgn-botao-cotacao', function(e){
      e.preventDefault();

      const tipo = getTipo($cx);
      if (tipo === 'diario') {
        const inicio = $cx.find('.bvgn-data-inicio').val();
        const fim    = $cx.find('.bvgn-data-fim').val();
        if (!inicio || !fim) { setMsg($cx, 'Selecione as datas de início e fim.'); return; }

        const dias = diferencaDiasSeguro(inicio, fim);
        const $inputs = $cx.find('.bvgn-variacao input[type=radio]');
        let varSelecionada = null;
        $inputs.each(function(){
          const min = parseInt($(this).data('min-days') || 1, 10);
          const max = parseInt($(this).data('max-days') || min, 10);
          if (dias >= min && dias <= max) { varSelecionada = $(this); $(this).prop('checked', true); return false; }
        });
        if (!varSelecionada) { setMsg($cx, `Nenhum plano cobre ${dias} dias. Ajuste as datas ou <a href="/planos-mensais">acesse os grupos mensais</a>.`); return; }
      }

      function definirDestinoFixo(botao) {
        let numeroDestino = (botao.getAttribute('data-telefone') || '').replace(/\D/g, '');
        if (!numeroDestino && window.BVGN && BVGN.whatsDestino) {
          numeroDestino = String(BVGN.whatsDestino).replace(/\D/g, '');
        }
        const $hidById   = $('#bvgn_whats_destino');
        const $hidByName = $('input[name="whatsapp_destino"]');
        if ($hidById.length) $hidById.val(numeroDestino);
        else if ($hidByName.length) $hidByName.val(numeroDestino);
      }
      definirDestinoFixo(this);

      const modalEl = document.getElementById('bvgn-cotacao-modal');
      if (modalEl) {
        modalEl.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('bvgn-modal-open');
      }
    });
  }

  // ===== init / document ready =====
  $(function(){
    if (BVGN._bootstrapped) return; // evita bind duplicado
    BVGN._bootstrapped = true;

    // Preenche datas via localStorage
    const agendamentoRaw = localStorage.getItem('bvgn_agendamento');
    if (agendamentoRaw) {
      try {
        const dados = JSON.parse(agendamentoRaw);
        if (dados.inicio && dados.fim) {
          function toISO(dateStr) {
            const parts = dateStr.split('-');
            if (parts[0].length === 4) return dateStr;
            const [d, m, y] = parts;
            return `${y}-${m}-${d}`;
          }
          const isoInicio = toISO(dados.inicio);
          const isoFim = toISO(dados.fim);
          $('.bvgn-data-inicio').val(isoInicio).trigger('change');
          $('.bvgn-data-fim').val(isoFim).trigger('change');
          setTimeout(function(){ $('.bvgn-data-fim').trigger('change'); }, 150);
        }
      } catch (e) { /* no-op */ }
    }

    setTimeout(function(){
      const inicioVal = $('.bvgn-data-inicio').val();
      const fimVal    = $('.bvgn-data-fim').val();
      if (!inicioVal || !fimVal) {
        $('.bvgn-container').each(function(){
          setMsg($(this), 'Selecione as datas de início e fim antes de gerar a cotação.');
        });
      }
    }, 160);

    $('.bvgn-container').each(function(){
      const $cx = $(this);
      ligarEventos($cx);
      // primeiro cálculo/render
      aplicarRegrasECalcular($cx);
      updateVarDesc($cx);
    });
  });

})(window, jQuery);
