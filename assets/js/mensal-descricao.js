;(function($){
  function formatBR(v){ return Number(v).toFixed(2).replace('.', ','); }

  function initMensalDesc($root){
    $root = $root || $(document);
    $root.find('.bvgn-variacoes[data-bvgn-tipo="mensal"]').each(function(){
      const $wrap  = $(this);
      const $lista = $wrap.find('.bvgn-variacoes-opcoes');

      // Cria o card uma vez
      let $card = $wrap.find('.bvgn-desc-card');
      if (!$card.length){
        $card = $('<div class="bvgn-desc-card" />').insertAfter($lista);
      }

      function atualizarCard(){
        const $sel = $wrap.find('.bvgn-variacao input[type=radio]:checked').first();
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

      // Atualiza agora e nos próximos changes
      atualizarCard();
      $wrap.on('change', '.bvgn-variacao input[type=radio"]', atualizarCard);

      // Caso o botão-fake não dispare o change
      $wrap.on('click', '.bvgn-variacao .botao-fake', function(){
        const $inp = $(this).closest('.bvgn-variacao').find('input[type=radio"]').first();
        if ($inp.length){ $inp.prop('checked', true).trigger('change'); }
      });
    });
  }

  // DOM pronto
  $(function(){ initMensalDesc(); });

  // Se seu tema recarregar a seção via AJAX, exponha a função:
  window.BVGN_initMensalDesc = initMensalDesc;
})(jQuery);
