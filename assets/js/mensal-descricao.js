;(function($){
  function formatBR(v){ return Number(v).toFixed(2).replace('.', ','); }

  function initMensalDesc($root){
    $root = $root || $(document);

    $root.find('.bvgn-variacoes[data-bvgn-tipo="mensal"]').each(function(){
      const $wrap  = $(this);
      const $lista = $wrap.find('.bvgn-variacoes-opcoes');

      // cria o card uma vez
      let $card = $wrap.find('.bvgn-desc-card');
      if (!$card.length){
        $card = $('<div class="bvgn-desc-card" aria-live="polite" />').insertAfter($lista);
      }

      function atualizarCard(){
        const $sel = $wrap.find('.bvgn-variacao input[type="radio"]:checked').first(); // <-- CORRIGIDO
        if (!$sel.length){ $card.hide(); return; }

        const rotulo   = $sel.attr('data-rotulo') || '';
        const desc     = ($sel.attr('data-desc') || '').trim();
        const precoDia = $sel.attr('data-preco-dia');

        let msg = '';
        if (desc){
          msg = desc;
        } else if (precoDia && !isNaN(precoDia)){
          msg = 'Nesse valor o preço por dia é de R$ ' + formatBR(precoDia);
        }

        if (msg){
          const badge = rotulo ? `<span class="badge">${rotulo}</span>` : '';
          $card.html(`<strong>Detalhes da seleção</strong> ${badge}<br>${msg}`).show();
        } else {
          $card.hide();
        }
      }

      // inicial
      atualizarCard();

      // eventos (delegados)
      $wrap.on('change', '.bvgn-variacao input[type="radio"]', atualizarCard); // <-- CORRIGIDO
      $wrap.on('click',  '.bvgn-variacao .botao-fake', function(e){
        e.preventDefault();
        const $inp = $(this).closest('.bvgn-variacao').find('input[type="radio"]').first(); // <-- CORRIGIDO
        if ($inp.length){ $inp.prop('checked', true).trigger('change'); }
      });

      // clique no card inteiro também seleciona
      $wrap.on('click', '.bvgn-variacao', function(e){
        if (e.target.tagName !== 'INPUT'){
          const $inp = $(this).find('input[type="radio"]').first();
          if ($inp.length){ $inp.prop('checked', true).trigger('change'); }
        }
      });
    });
  }

  $(function(){ initMensalDesc(); });
  window.BVGN_initMensalDesc = initMensalDesc;
})(jQuery);
