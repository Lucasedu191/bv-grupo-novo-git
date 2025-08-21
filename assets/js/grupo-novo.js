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
  return Math.max(Math.floor(ms / 86400000), 1);
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
    if(txt){ $m.html(txt).addClass('on'); }
    
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

    if (!sVal || !eVal) {
      setMsg($cx, '');
      return false;
    }

    const dias = diferencaDiasSeguro(sVal, eVal);
    // if (dias < minDays || dias > maxDays) {
    //   setMsg($cx, `O período deve ter entre ${minDays} e ${maxDays} dias.`);
    // } else {
    //   setMsg($cx, '');
    // }

  // corrige fim < início
  const s = parseISODateLocal(sVal);
  const e = parseISODateLocal(eVal);
  if(e < s){ $e.val(dateToISO(s)); }

  const daysNow = diferencaDiasSeguro($s.val(), $e.val());

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

    // 1. Proteção (radio) — baseado no atributo data-preco-dia
    const $prot = $cx.find('input[name="bvgn_protecao"]:checked');
    if ($prot.length) {
      const preco = numero($prot.data('preco-dia'));
      const caucao = numero($prot.data('caucao'));
      const nomeProt = String($prot.closest('label').find('.texto').clone().children().remove().end().text()).trim();
      const precoProt = numero($prot.data('preco-dia'));
      
      

      const valorProt = preco; // apenas 1x
      taxas += valorProt;

      // se houver caução, soma também
      if (caucao > 0) {
        taxas += caucao;
      }

      const rotuloProt = `${nomeProt} — R$ ${precoProt.toFixed(2).replace('.', ',')}`;
      // adiciona na lista detalhada (caso esteja mostrando os itens)
      if ($cx.find('#bvgn-taxas-itens').length) {
        $cx.find('#bvgn-taxas-itens').append(`<li>${rotuloProt}</li>`);
      }

      // Exibir resumo
      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text(rotuloProt);
    }

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
    // — Preenchimento do novo template visual —

    // Exibir o local de retirada
    $cx.find('.bvgn-local').show();
    $cx.find('#bvgn-local-view').text('BV Locadora, Rua Coronel Mota, 629');

    // Exibir o período de dias
    const isMensal = $('.bvgn-variacoes[data-bvgn-tipo="mensal"]').length > 0;
    if (isMensal) qtd = 30;
    
    // Exibir o período de dias
    $cx.find('.bvgn-dias').show();
    $cx.find('#bvgn-days-view').text(`${qtd} dia${qtd > 1 ? 's' : ''}`);
    $cx.find('#bvgn-days-raw').val(qtd);

    // Exibir o valor total das taxas
    $cx.find('.bvgn-taxas').show();
    $cx.find('#bvgn-taxas').text(taxas.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-taxas-raw').val(taxas);

    // Subtotal e total (valores crus já estão ali, mas reforçamos)
    $cx.find('#bvgn-subtotal-raw').val(subtotal);
    $cx.find('#bvgn-total-raw').val(total);

    $cx.find('#bvgn-subtotal-view').text(subtotal.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-total-view').text(total.toFixed(2).replace('.', ','));

    // Listar taxas detalhadas (opcional)
    const taxasDetalhadas = [];
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco  = numero($(this).data('preco'));
      taxasDetalhadas.push(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
    });

    const $lista = $cx.find('#bvgn-taxas-itens');
    $lista.empty();
    taxasDetalhadas.forEach(t => {
      $lista.append(`<li>${t}</li>`);
    });
    if (taxasDetalhadas.length) {
      $cx.find('.bvgn-taxas-lista').show();
    }

    // Exibir plano selecionado (variação)
    const $var = $cx.find('.bvgn-variacao input[type=radio]:checked');
    if ($var.length) {
      const rotulo = String($var.data('rotulo') || 'Plano selecionado');
      const preco  = numero($var.data('preco') || base);
      $cx.find('.bvgn-var').show();
      $cx.find('#bvgn-var-view').text(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
    }

    // Preencher proteção no resumo (bloco lateral)
    if ($prot.length) {
      const nomeProt = String($prot.closest('label').find('.texto').clone().children().remove().end().text()).trim();
      const precoProt = numero($prot.data('preco-dia'));
      const caucao = numero($prot.data('caucao'));
      const valorExibir = precoProt + caucao;

      const rotuloProt = `${nomeProt} — R$ ${valorExibir.toFixed(2).replace('.', ',')}`;

      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text(rotuloProt);
    } else {
      $cx.find('.bvgn-protecao').hide();
      $cx.find('#bvgn-protecao-view').text('');
    }

    // Preencher serviços opcionais no resumo (bloco lateral)
    const opcionais = [];
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco = numero($(this).data('preco'));
      if (rotulo) {
        opcionais.push(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
      }
    });

    if (opcionais.length > 0) {
      $cx.find('.bvgn-opcionais').show();
      $cx.find('#bvgn-opcionais-view').html(opcionais.join('<br/>'));
    } else {
      $cx.find('.bvgn-opcionais').hide();
      $cx.find('#bvgn-opcionais-view').text('');
    }

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
    // Destacar visualmente o item selecionado
    if ($t.is('.bvgn-taxa input')) {
      const isRadio = $t.attr('type') === 'radio';
      const name = $t.attr('name');

      if (isRadio) {
        $cx.find(`input[name="${name}"]`).each(function () {
          $(this).closest('.bvgn-taxa').removeClass('selected');
        });
      }

      if ($t.is(':checked')) {
        $t.closest('.bvgn-taxa').addClass('selected');
      } else {
        $t.closest('.bvgn-taxa').removeClass('selected');
      }
    }

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

  // Lógica personalizada para produtos do tipo diário
  const tipo = getTipo($cx);
  if (tipo === 'diario') {
    const $variacoes = $cx.find('.bvgn-variacao');
    $variacoes.hide(); // Oculta o bloco visualmente

    // Monitora alteração de datas para auto selecionar variação
    $cx.on('change input', '.bvgn-data-inicio, .bvgn-data-fim', function() {
      const s = $cx.find('.bvgn-data-inicio').val();
      const e = $cx.find('.bvgn-data-fim').val();
      if (!s || !e) return;

      $cx.find('.bvgn-variacao input[type=radio]').prop('checked', false);

      const dias = diferencaDiasSeguro(s, e);

      console.log('[BVGN][DEBUG] Dias calculados:', dias);
        $cx.find('.bvgn-variacao input[type=radio]').each(function() {
          const min = parseInt($(this).data('min-days') || 1, 10);
          const max = parseInt($(this).data('max-days') || min, 10);
          console.log('[BVGN][DEBUG] Verificando variação:', {
            id: $(this).val(),
            min,
            max,
            rotulo: $(this).closest('label').find('.lbl').text().trim()
          });
        });

      // Tenta encontrar variação compatível com os dias
      const $inputs = $cx.find('.bvgn-variacao input[type=radio]');
      let selecionado = false;

      $inputs.each(function(){
        const min = parseInt($(this).data('min-days') || 1, 10);
        const max = parseInt($(this).data('max-days') || min, 10);
        if (dias >= min && dias <= max) {
          $(this).prop('checked', true).trigger('change');
          selecionado = true;
          console.log(`[BVGN] Variação selecionada automaticamente para ${dias} dias.`);
          return false; // break
        }
      });

      if (!selecionado) {
      if (dias > 30) {
        setMsg($cx, 'O período máximo para agendamentos diários é de 30 dias. Para prazos maiores, <a href="/grupos-mensais">acesse os grupos mensais</a>.');
      } else {
        setMsg($cx, `Nenhum plano cobre ${dias} dias. Tente ajustar o período ou acesse os grupos mensais.`);
      }
    } else {
      setMsg($cx, `Plano selecionado automaticamente para ${dias} dias.`);
    }

    });
  }

  // inicial
  aplicarRegrasECalcular($cx);
  updateVarDesc($cx); 
}

  $(function(){
  // pegar valores do calendário do cabeçalho  
  const agendamentoRaw = localStorage.getItem('bvgn_agendamento');
  if (agendamentoRaw) {
    try {
      const dados = JSON.parse(agendamentoRaw);
      if (dados.inicio && dados.fim) {
        // converte DD-MM-YYYY para YYYY-MM-DD
        function toISO(dateStr) {
          const parts = dateStr.split('-');
          // Se já está em ISO (YYYY-MM-DD), retorna como está
          if (parts[0].length === 4) {
            return dateStr;
          }
          // Se está em DD-MM-YYYY, converte
          const [d, m, y] = parts;
          return `${y}-${m}-${d}`;
        }

        const isoInicio = toISO(dados.inicio);
        const isoFim = toISO(dados.fim);

        
        

        $('.bvgn-data-inicio').val(isoInicio).trigger('change');
        $('.bvgn-data-fim').val(isoFim).trigger('change');

        setTimeout(function(){
          $('.bvgn-data-fim').trigger('change');
        }, 150); // dá um pequeno tempo para DOM renderizar

        console.log('[BVGN] Datas preenchidas a partir do localStorage:', { isoInicio, isoFim });
      }
    } catch (e) {
      console.warn('[BVGN] Erro ao carregar dados do agendamento:', e);
    }
  }
  
  $('.bvgn-container').each(function(){
     const $cx = $(this);
    ligarEventos($cx);   

    // Botão de cotação — fluxo de modal
    $cx.on('click', '.bvgn-botao-cotacao', function(e){
    e.preventDefault();

    console.log('[BVGN] Clique no botão .bvgn-botao-cotacao');

    const tipo = getTipo($cx);
    console.log('[BVGN] Tipo selecionado:', tipo);

    
    if (tipo === 'diario') {
      const inicio = $cx.find('.bvgn-data-inicio').val();
      const fim    = $cx.find('.bvgn-data-fim').val();

      if (!inicio || !fim) {
        setMsg($cx, 'Selecione as datas de início e fim.');
        return;
      }

      const dias = diferencaDiasSeguro(inicio, fim);
      const $inputs = $cx.find('.bvgn-variacao input[type=radio]');
      let varSelecionada = null;

      $inputs.each(function(){
        const min = parseInt($(this).data('min-days') || 1, 10);
        const max = parseInt($(this).data('max-days') || min, 10);
        if (dias >= min && dias <= max) {
          varSelecionada = $(this);
          $(this).prop('checked', true); // força marcação
          return false;
        }
      });

      if (!varSelecionada) {
        setMsg($cx, `Nenhum plano cobre ${dias} dias. Ajuste as datas ou <a href="/planos-mensais">acesse os grupos mensais</a>.`);
        return;
      }
    }


    if (tipo === 'diario') {
    const inicio = $cx.find('.bvgn-data-inicio').val();
    const fim = $cx.find('.bvgn-data-fim').val();
    console.log('[BVGN] Datas selecionadas:', { inicio, fim });

    if (!inicio || !fim) {
      alert('Selecione as datas de início e fim.');
      console.warn('[BVGN] Datas incompletas.');
      return;
    }

    const dias = diferencaDiasSeguro(inicio, fim);
    const $inputs = $cx.find('.bvgn-variacao input[type=radio]');
    let varSelecionada = null;

    $inputs.each(function(){
      const min = parseInt($(this).data('min-days') || 1, 10);
      const max = parseInt($(this).data('max-days') || min, 10);
      if (dias >= min && dias <= max) {
        varSelecionada = $(this);
        return false; // break
      }
    });

    if (!varSelecionada || !varSelecionada.prop('checked')) {
      alert('Nenhuma opção de carro está disponível para esse período.');
      console.warn('[BVGN] Nenhuma variação compatível ou selecionada.');
      return;
    }
  }

    console.log('[BVGN] Abrindo modal...');
    const modalEl = document.getElementById('bvgn-cotacao-modal');

    if (modalEl) {
      modalEl.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('bvgn-modal-open');
      console.log('[BVGN] Modal aberto com sucesso!');
    } else {
      console.error('[BVGN] Modal não encontrado no DOM.');
    }
  });

  });
});

})(jQuery);
