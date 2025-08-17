(function($){
  // ====== utils existentes ======
  // ====== limite absoluto para diário ======
  const BVGN_MAX_DIAS_ABSOLUTO = 30;
  
  // Parse seguro (ignora fuso/UTC)
function parseISODateLocal(str){
  if(!str) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
  if(!m) return null;
  return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]), 0, 0, 0, 0);
}

// Diferença em dias (inclusivo) sem fuso
function diferencaDiasSeguro(inicioStr, fimStr){
  const s = parseISODateLocal(inicioStr);
  const e = parseISODateLocal(fimStr);
  if(!s || !e) return 0;
  const ms = e.setHours(0,0,0,0) - s.setHours(0,0,0,0);
  return Math.max(Math.floor(ms / 86400000) + 1, 1);
}

// helper ISO
function dateToISO(d){
  if(!d) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const dd= String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${dd}`;
}
  function numero(v){ return Number(String(v).replace(',', '.')) || 0; }

  // ====== NOVO: helpers de regras/datas ======
  function getTipo($cx){
    const $bloco = $cx.find('[data-bvgn-tipo]').first();
    const tipoRaw = $bloco.attr('data-bvgn-tipo');
  return (tipoRaw || 'diario');
}
  function getRulesFromVar($cx){
    const $checked = $cx.find('.bvgn-variacao input[type=radio]:checked');

    const minRaw = $checked.attr('data-min-days');
    const maxRaw = $checked.attr('data-max-days');

    let minDays = parseInt(minRaw, 10);
    let maxDays = parseInt(maxRaw, 10);

    // saneamento básico
    if (!Number.isInteger(minDays) || minDays < 1) minDays = 1;
    if (!Number.isInteger(maxDays) || maxDays < minDays) maxDays = minDays;

    // aplica limite absoluto de dias (definido no topo do arquivo)
    if (maxDays > BVGN_MAX_DIAS_ABSOLUTO) {
      maxDays = BVGN_MAX_DIAS_ABSOLUTO;
    }

  return { minDays, maxDays };
}
  
  
  function setMsg($cx, txt){
    const $m = $cx.find('#bv-date-msg');
    if(!$m.length) return;
    if(txt){ $m.text(txt).addClass('on'); }
    else { $m.text('').removeClass('on'); }
  }

  // Normaliza datas conforme min/max; retorna true se ajustou algo
  function normalizeDatesToRule($cx){
  const tipo = getTipo($cx);
  if(tipo !== 'diario') { setMsg($cx, ''); return false; }

  const { minDays, maxDays } = getRulesFromVar($cx);
  const $s = $cx.find('.bvgn-data-inicio');
  const $e = $cx.find('.bvgn-data-fim');
  const sVal = $s.val(), eVal = $e.val();

  if(!sVal){ setMsg($cx, ''); return false; }

  // se não há devolução, define baseada no mínimo
  if(!eVal){
    const base = parseISODateLocal(sVal);
    const forced = new Date(base.getTime());
    forced.setDate(base.getDate() + (minDays - 1));
    $e.val(dateToISO(forced));
    setMsg($cx, (minDays===1 && maxDays===1)
      ? 'Esta variação permite apenas 1 dia.'
      : `Mínimo de ${minDays} dias. Ajustamos a devolução.`);
    return true;
  }

  // corrige fim < início
  const s = parseISODateLocal(sVal);
  const e = parseISODateLocal(eVal);
  if(e < s){ $e.val(dateToISO(s)); }

  const daysNow = diferencaDiasSeguro($s.val(), $e.val());

  if(minDays === 1 && maxDays === 1){
    $e.val($s.val());
    setMsg($cx, 'Esta variação permite apenas 1 dia. Ajustamos a devolução.');
    return true;
  }
  if(daysNow < minDays){
    const base = parseISODateLocal($s.val());
    const forced = new Date(base.getTime());
    forced.setDate(base.getDate() + (minDays - 1));
    $e.val(dateToISO(forced));
    setMsg($cx, `Mínimo de ${minDays} dias. Ajustamos a devolução.`);
    return true;
  }
  if(daysNow > maxDays){
    const base = parseISODateLocal($s.val());
    const forced = new Date(base.getTime());
    forced.setDate(base.getDate() + (maxDays - 1));
    $e.val(dateToISO(forced));
    setMsg($cx, `Máximo de ${maxDays} dias. Ajustamos a devolução.`);
    return true;
  }

  setMsg($cx, '');
  return false;
}

  // ====== cálculo (seu original, inalterado) ======
  function calcular($cx){
  const tipo = getTipo($cx);
  const base = numero($cx.find('.bvgn-variacao input[type=radio]:checked').data('preco'));

  // quantidade de dias (apenas para diário)
  let qtd = 1;
  if (tipo === 'diario'){
    const s = $cx.find('.bvgn-data-inicio').val();
    const e = $cx.find('.bvgn-data-fim').val();
    if (s && e) qtd = diferencaDiasSeguro(s, e);
  }

  // soma taxas
  let taxas = 0;

  // taxas selecionadas (checkbox)
  $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
    const rotulo = String($(this).data('rotulo') || '').toLowerCase();
    const preco  = numero($(this).data('preco'));

    // Se for taxa diária e tipo diário → multiplicar pelos dias
    if (rotulo.includes('(diaria)') && tipo === 'diario'){
      taxas += preco * qtd;
    } else {
      taxas += preco;
    }
  });

  // taxa fixa mensal (se existir) — não multiplica
  $cx.find('.bvgn-taxa-fixa-input').each(function(){
    taxas += numero($(this).data('preco'));
  });

  const subtotal = base * qtd;
  const total = subtotal + taxas;

  $cx.find('.bvgn-subtotal .valor').text(subtotal.toFixed(2).replace('.', ','));
  $cx.find('.bvgn-total .valor').text(total.toFixed(2).replace('.', ','));

  $cx.data('bvgnTotais', { base, taxas, qtd, subtotal, total, tipo });
}


  // ====== NOVO: aplicar regras quando mudar variação ou datas ======
  function aplicarRegrasECalcular($cx){
    const tipo = getTipo($cx);

    // plano mensal: esconder bloco de datas (se existir) e limpar msg
    const $wrapAg = $cx.find('.bvgn-agendamento');
    if(tipo === 'mensal'){
      if($wrapAg.length){ $wrapAg.hide(); setMsg($cx, ''); }
      calcular($cx);
      return;
    } else {
      if($wrapAg.length){ $wrapAg.show(); }
      normalizeDatesToRule($cx);
    }

    calcular($cx);
  }

  function updateVarDesc($cx){
  const $box = $cx.find('.bvgn-variacao-desc');
  const $txt = $cx.find('.bvgn-variacao-desc-text');
  if(!$box.length || !$txt.length) return;

  const $sel = $cx.find('input[name="bvgn_variacao"]:checked');
  const desc = ($sel.data('desc') || '').trim();

  if(desc){
    $txt.text(desc); // seguro
    $box.show();
  }else{
    $txt.text('');
    $box.hide();
  }
}

  // ====== eventos ======
  function ligarEventos($cx){
    // qualquer mudança que afete o total
    $cx.on('change input', '.bvgn-variacao input, .bvgn-taxa input, .bvgn-data-inicio, .bvgn-data-fim', function(e){
      const $t = $(e.target);

      // se mudou variação → aplicar regras de dias
      if($t.is('.bvgn-variacao input')){
        aplicarRegrasECalcular($cx);
        updateVarDesc($cx); 
        return;
      }

      // se mudou data → normaliza e recalcula
      if($t.is('.bvgn-data-inicio, .bvgn-data-fim')){
        normalizeDatesToRule($cx);
        calcular($cx);
        return;
      }

      // demais (taxas etc.)
      calcular($cx);
      updateVarDesc($cx);
    });

    // inicial
    aplicarRegrasECalcular($cx);
    updateVarDesc($cx); 

    // botão de cotação (seu original)
    $cx.on('click', '.bvgn-botao-cotacao', function(e){
      e.preventDefault();
      const carga = {
        action: 'bvgn_gerar_arquivo',
        _wpnonce: BVGN.nonce,
        produtoId: $cx.data('produto-id'),
        informacoes: $cx.find('.bvgn-informacoes').val() || '',
        variacaoRotulo: $cx.find('.bvgn-variacao input:checked').data('rotulo') || '',
        datas: {
          inicio: $cx.find('.bvgn-data-inicio').val() || '',
          fim:    $cx.find('.bvgn-data-fim').val() || ''
        },
        taxas: $cx.find('.bvgn-taxa input:checked').map(function(){
          return { rotulo: $(this).data('rotulo'), preco: $(this).data('preco') };
        }).get(),
        totais: $cx.data('bvgnTotais'),
        formato: $(this).data('formato'),
        
      };

      $.post(BVGN.ajaxUrl, carga, function(r){
        if(!r || !r.success){ alert('Erro ao gerar cotacao.'); return; }
        const url = r.data.url;
        const tel = ($('#bvgn_whats').val() || '').replace(/\D/g, '');
        if (!tel) {
          alert('Preencha seu número de WhatsApp.');
          return;
        }
        const msg = encodeURIComponent('Ola! Segue minha cotacao: ' + url);
        const wa  = 'https://wa.me/'+tel+'?text='+msg;
        window.open(wa, '_blank');
      });
    });
    

  }

  $(function(){
  $('.bvgn-container').each(function(){
    const $cx = $(this);
    ligarEventos($cx);

    // Botão de cotação — fluxo de modal
    $cx.on('click', '.bvgn-botao-cotacao', function(e){
      e.preventDefault();

      const tipo = getTipo($cx);
      if(tipo === 'diario'){
        if(!$cx.find('.bvgn-data-inicio').val() || !$cx.find('.bvgn-data-fim').val()){
          alert('Selecione as datas de início e fim.');
          return;
        }
      }
      const $var = $cx.find('.bvgn-variacao input:checked');
      if(!$var.length){
        alert('Selecione uma variação.');
        return;
      }

      // abre o modal (ajuste o seletor se necessário)
      document.querySelector('[data-bvgn-modal="bvgn-cotacao-modal"]')
              ?.dispatchEvent(new Event('click', { bubbles: true }));
    });
  });
});

})(jQuery);
