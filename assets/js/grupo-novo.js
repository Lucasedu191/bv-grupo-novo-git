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
  // usa meio‑dia para evitar problemas de DST (23h/25h)
  const sMid = new Date(s.getFullYear(), s.getMonth(), s.getDate(), 12, 0, 0, 0);
  const eMid = new Date(e.getFullYear(), e.getMonth(), e.getDate(), 12, 0, 0, 0);
  const ms = eMid - sMid;
  return Math.max(Math.round(ms / 86400000), 1);
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
  function formatBR(v){
    const n = Number(v) || 0;
    return n.toFixed(2).replace('.', ',');
  }

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
    const grupoProduto = String(($cx.attr('data-produto-grupo') || '')).trim().toUpperCase();

    // quantidade de dias (apenas para diário)
    let qtd = 1;
    if (tipo === 'diario'){
      const s = $cx.find('.bvgn-data-inicio').val();
      const e = $cx.find('.bvgn-data-fim').val();
      if (s && e) qtd = diferencaDiasSeguro(s, e);
    }

    // soma taxas
    let taxas = 0;
    let tarifaExtra = { extra: 0, detalhes: [] };
    let caucaoAviso = 0;
    let caucaoRotulo = '';

    // 1. Proteção (radio) — baseado no atributo data-preco-dia (apenas no diário)
    const $prot = $cx.find('input[name="bvgn_protecao"]:checked');
    if ($prot.length) {
      const preco  = numero($prot.data('preco-dia'));
      const caucao = numero($prot.data('caucao'));
      const nomeProt = String(
        $prot.closest('label').find('.texto').clone().children().remove().end().text()
      ).trim();

      const diasReferencia = (tipo === 'diario') ? qtd : 1;
      const valorProtDias = preco * diasReferencia;

      if (caucao > 0) {
        caucaoAviso = caucao;
        caucaoRotulo = `Caução com proteção: R$ ${formatBR(caucao)}`;
      }

      $prot.attr('data-preco-total', valorProtDias); // usado ao enviar dados

      taxas += valorProtDias;

      const diasLabel = tipo === 'diario'
        ? `${qtd} dia${qtd > 1 ? 's' : ''}`
        : '30 dias';
      const unitBR    = formatBR(preco);
      const totalBR   = formatBR(valorProtDias);
      const rotuloProt = tipo === 'diario'
        ? `${nomeProt} - ${diasLabel} - R$ ${unitBR} - total R$ ${totalBR}`
        : `${nomeProt} - R$ ${unitBR}`;

      if ($cx.find('#bvgn-taxas-itens').length) {
        $cx.find('#bvgn-taxas-itens').append(`<li>${rotuloProt}</li>`);
      }
    }

    // taxas selecionadas (checkbox)
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotuloOriginal = String($(this).data('rotulo') || '');
      const rotulo = rotuloOriginal.toLowerCase();
      const preco  = numero($(this).data('preco'));
      const isDiaria = rotulo.includes('(diaria)');
      const isCaucao = /cau[cç][aã]o/.test(rotulo);

      if (isCaucao) {
        if (caucaoAviso <= 0 && preco > 0) {
          caucaoAviso = preco;
          caucaoRotulo = rotuloOriginal || 'Caução';
        }
        return;
      }

      if (tipo === 'diario') {
        taxas += isDiaria ? (preco * qtd) : preco;
      } else {
        taxas += isDiaria ? (preco * 30) : preco;
      }
    });

    // 3) Taxas fixas (ex.: limpeza obrigat?ria, cau??o)
    $cx.find('.bvgn-taxa-fixa-input').each(function () {
      const $el      = $(this);
      const rotulo   = String($el.data('rotulo') || '').toLowerCase();
      const preco    = numero($el.data('preco'));
      const isCaucao = /cau[cç][aã]o/.test(rotulo) || $el.data('tipo') === 'caucao';

      if (isCaucao) {
        if (caucaoAviso <= 0 && preco > 0) {
          caucaoAviso = preco;
          caucaoRotulo = rotulo || 'Caução';
        }
      } else {
        taxas += preco;
      }
    });

    // 4) SUBTOTAL / TOTAL
    // No mensal: mostrar "30 dias" apenas para exibicao, mas NAO multiplicar o preco.
    const dias = (tipo === 'mensal') ? 30 : qtd;                      // usado so para exibir
    const subtotal = (tipo === 'mensal') ? base : (base * dias);      // mensal = 1x; diario = * dias

    // Tarifa dinamica percorre dia a dia (apenas diario) usando a regra de maior prioridade
    if (tipo === 'diario' && base > 0) {
      const s = $cx.find('.bvgn-data-inicio').val();
      const e = $cx.find('.bvgn-data-fim').val();
      if (s && e && window.BVGN_Dynamic && typeof BVGN_Dynamic.calcularTarifaDinamica === 'function') {
        tarifaExtra = BVGN_Dynamic.calcularTarifaDinamica(base, s, e, qtd, grupoProduto);
      }
    }
    const totalDynamic = Number(tarifaExtra.extra || 0);
    const detalhesDyn  = Array.isArray(tarifaExtra.detalhes) ? tarifaExtra.detalhes : [];
    const totalResumoDyn = detalhesDyn.reduce((acc,d) => d.showResumo ? acc + Number(d.valor || 0) : acc, 0);
    const total    = subtotal + taxas + totalDynamic;      // somar taxas tambem no mensal
    const totalRounded = Math.round(total); // arredonda para inteiro pela primeira casa decimal



    $cx.find('.bvgn-subtotal .valor').text(subtotal.toFixed(2).replace('.', ','));
    $cx.find('.bvgn-total .valor').text(totalRounded.toFixed(2).replace('.', ','));
    // ? Preenchimento do novo template visual ?

    // Exibir o local de retirada
    $cx.find('.bvgn-local').show();
    $cx.find('#bvgn-local-view').text('BV Locadora, Rua Coronel Mota, 629');

    // Exibir o período de dias
    const isMensal = $('.bvgn-variacoes[data-bvgn-tipo="mensal"]').length > 0;
    if (isMensal) qtd = 30;
    
    // Exibir o período de dias (usar 'dias' agora)
    $cx.find('.bvgn-dias').show();
    $cx.find('#bvgn-days-view').text(`${dias} dia${dias > 1 ? 's' : ''}`);   // ← ALTERAÇÃO MÍNIMA
    $cx.find('#bvgn-days-raw').val(dias);                                    // ← ALTERAÇÃO MÍNIMA

    // Exibir valor total das taxas (0 no mensal automaticamente)
    $cx.find('.bvgn-taxas').show();
    $cx.find('#bvgn-taxas').text(taxas.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-taxas-raw').val(taxas);

    // Caução como aviso (não entra no total) — mantém somente no hidden
    $cx.find('#bvgn-caucao-raw').val(caucaoAviso);
    $cx.find('.bvgn-caucao-aviso').hide();
    $cx.find('#bvgn-caucao-view').text('');

    // Tarifa dinamica: exibe só se configurada para resumo
    if (typeof totalDynamic !== 'undefined') {
      if (totalResumoDyn > 0.0001) {
        const visiveis = detalhesDyn.filter(d => d && d.showResumo);
        const labelDyn = (function(){
          if (!visiveis.length) return '';
          const seen = new Set();
          const normalize = (txt) => String(txt || '').replace(/\s+/g, ' ').trim().toLowerCase();
          const partes = [];
          visiveis.forEach(d => {
            const rotRaw  = (d.rotulo || '').trim();
            const descRaw = (d.desc || '').trim();
            const percNum = Number(d.percent || 0);
            const key = `${normalize(rotRaw)}|${normalize(descRaw)}|${percNum.toFixed(4)}`;
            if (seen.has(key)) return;
            seen.add(key);
            if (rotRaw && descRaw) partes.push(`${rotRaw} — ${descRaw}`);
            else if (rotRaw) partes.push(rotRaw);
            else if (descRaw) partes.push(descRaw);
            else if (percNum) partes.push(`+${percNum}%`);
          });
          let txt = partes.slice(0, 2).join(' | ');
          if (partes.length > 2) txt += ' ...';
          return txt;
        })();
        const valorDyn = `R$ ${totalResumoDyn.toFixed(2).replace('.', ',')}`;
        $cx.find('.bvgn-dyn').show();
        $cx.find('#bvgn-dyn-view').text(labelDyn ? `${valorDyn} — ${labelDyn}` : valorDyn);
      } else {
        $cx.find('.bvgn-dyn').hide();
        $cx.find('#bvgn-dyn-view').text('R$ 0,00');
      }
      $cx.find('#bvgn-dynamic-extra-raw').val(totalDynamic.toFixed(2));
    }


    // Subtotal e total (valores crus já estão ali, mas reforçamos)
    $cx.find('#bvgn-subtotal-raw').val(subtotal);
    $cx.find('#bvgn-subtotal-view').text(subtotal.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-total-raw').val(totalRounded);

    $cx.find('#bvgn-total-view').text(totalRounded.toFixed(2).replace('.', ','));

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
    } else {
      $cx.find('.bvgn-taxas-lista').hide();
    }


// Exibir plano selecionado (variacao) / Por dia (diario)
const $var = $cx.find('.bvgn-variacao input[type=radio]:checked');
if ($var.length) {
  const rotuloVar = String($var.data('rotulo') || 'Plano selecionado');
  const precoUnit = numero($var.data('preco') || base);
  $cx.find('.bvgn-var').show();
  if (tipo === 'diario') {
    const diasLabel = `${qtd} dia${qtd > 1 ? 's' : ''}`;
    const totalDiariasBase = (precoUnit * qtd);
    const totalDiariasDyn  = totalDiariasBase + totalDynamic;
    const unitBR = precoUnit.toFixed(2).replace('.', ',');
    const unitDyn = (qtd > 0) ? (totalDiariasDyn / qtd) : precoUnit;
    const totalBR = totalDiariasDyn.toFixed(2).replace('.', ',');
    const unitDynBR = unitDyn.toFixed(2).replace('.', ',');

    const visiveisDyn = Array.isArray(detalhesDyn) ? detalhesDyn.filter(d => d && d.showResumo) : [];
    if (totalDynamic > 0 && visiveisDyn.length === 0) {
      // regra n?o exposta: mostra apenas valor final j? com taxa
      $cx.find('#bvgn-var-view').text(`Por dia: ${diasLabel} - R$ ${unitDynBR} - total R$ ${totalBR}`);
    } else if (totalDynamic > 0) {
      // mostra base e valor ajustado
      $cx.find('#bvgn-var-view').text(`Por dia: ${diasLabel} - R$ ${unitBR} -> R$ ${unitDynBR} c/ tarifa - total R$ ${totalBR}`);
    } else {
      $cx.find('#bvgn-var-view').text(`Por dia: ${diasLabel} - R$ ${unitBR} - total R$ ${totalDiariasBase.toFixed(2).replace('.', ',')}`);
    }
  } else {
    $cx.find('#bvgn-var-view').text(`${rotuloVar} - R$ ${precoUnit.toFixed(2).replace('.', ',')}`);
  }
}
// Preencher proteção no resumo (bloco lateral)
    if (tipo === 'mensal') {
      // Para plano mensal, exibe fixo
      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text('Proteção básica — incluída');
    } else if ($prot.length) {
      const nomeProt = String(
        $prot.closest('label').find('.texto').clone().children().remove().end().text()
      ).trim();
      const precoDia = numero($prot.data('preco-dia'));
      const valorProtDias = precoDia * qtd;
      const diasLabel = `${qtd} dia${qtd > 1 ? 's' : ''}`;
      const unitBR  = precoDia.toFixed(2).replace('.', ',');
      const totalBR = valorProtDias.toFixed(2).replace('.', ',');
      const rotuloProt = `${nomeProt} — ${diasLabel} × R$ ${unitBR} — total R$ ${totalBR}`;
      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text(rotuloProt);
    } else {
      $cx.find('.bvgn-protecao').hide();
      $cx.find('#bvgn-protecao-view').text('');
    }


    // Preencher serviços opcionais no resumo (bloco lateral)
    const opcionais = [];

    // (1) checkbox normais
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco = numero($(this).data('preco'));
      if (/cau[cç][aã]o/i.test(rotulo)) return;
      if (rotulo) {
        // Para plano diário, padronizar exibindo "valor x dias" nos opcionais por dia
        if (tipo === 'diario' && (/(condutor|cadeirinh)/i.test(rotulo) || /\(diaria\)/i.test(rotulo))) {
          const total = preco * qtd;
          opcionais.push(`${rotulo} – R$ ${preco.toFixed(2).replace('.', ',')} x ${qtd} dias`);
        } else {
          opcionais.push(`${rotulo} – R$ ${preco.toFixed(2).replace('.', ',')}`);
        }
      }
    });

    // (2) taxas fixas mensais (obrigatórias)
    $cx.find('.bvgn-taxa-fixa-input').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco = numero($(this).data('preco'));
      if (/cau[cç][aã]o/i.test(rotulo)) return;
      if (rotulo) {
        opcionais.push(`${rotulo} – R$ ${preco.toFixed(2).replace('.', ',')}`);
      }
    });

    if (opcionais.length > 0) {
      $cx.find('.bvgn-opcionais').show();
      $cx.find('#bvgn-opcionais-view').html(opcionais.join('<br/>'));
    } else {
      $cx.find('.bvgn-opcionais').hide();
      $cx.find('#bvgn-opcionais-view').text('');
    }

    $cx.data('bvgnTotais', { base, taxas, dynamicExtra: Number(totalDynamic || 0), dynamicDetalhes: tarifaExtra.detalhes || [], qtd, subtotal, total: totalRounded, tipo, caucao: caucaoAviso, caucaoRotulo });

    // Ajustar nome do rótulo lateral de "Serviços opcionais" no resumo
    // Ajustar títulos conforme o tipo de plano
    if (tipo === 'mensal') {
      $cx.find('.bvgn-opcionais .resumo-label').text('Taxas / Serviços opcionais:');
      $cx.find('.bvgn-servicos-opcionais .bvgn-totais-titulo').text('Taxas e serviços opcionais');
    } else {
      $cx.find('.bvgn-opcionais .resumo-label').text('Serviços opcionais:');
      $cx.find('.bvgn-servicos-opcionais .bvgn-totais-titulo').text('Serviços Opcionais');
    }
}


  // ====== NOVO: aplicar regras quando mudar variação ou datas ======
  function aplicarRegrasECalcular($cx){
    const tipo = getTipo($cx);

    // plano mensal: manter bloco visível (campo de retirada é informativo) e limpar msg
    const $wrapAg = $cx.find('.bvgn-agendamento');
    if(tipo === 'mensal'){
      if($wrapAg.length){ $wrapAg.show(); setMsg($cx, ''); }
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
  $cx.find('.bvgn-taxa input:checked').each(function(){
    $(this).closest('.bvgn-taxa').addClass('selected');
  });

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
        setMsg($cx, 'O período máximo para agendamentos diários é de 30 dias. Para prazos maiores, <a href="/planos-mensais">acesse os grupos mensais</a>.');
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

        // Preenche inputs; se flatpickr estiver anexado, use setDate para refletir no altInput
        document.querySelectorAll('.bvgn-data-inicio').forEach(function(el){
          if (el && el._flatpickr) {
            el._flatpickr.setDate(isoInicio, true);
          } else {
            jQuery(el).val(isoInicio).trigger('change');
          }
        });
        document.querySelectorAll('.bvgn-data-fim').forEach(function(el){
          if (el && el._flatpickr) {
            // ajusta minDate do fim com base no início
            try { el._flatpickr.set('minDate', isoInicio); } catch(_){}
            el._flatpickr.setDate(isoFim, true);
          } else {
            jQuery(el).val(isoFim).trigger('change');
          }
        });

        setTimeout(function(){
          $('.bvgn-data-fim').trigger('change');
        }, 150); // dá um pequeno tempo para DOM renderizar

        console.log('[BVGN] Datas preenchidas a partir do localStorage:', { isoInicio, isoFim });
      } else if (dados.inicio) {
        // fallback: se só a data de início existir, preenche ao menos a retirada
        const parts = String(dados.inicio).split('-');
        const isoInicio = (parts[0] && parts[0].length === 4)
          ? dados.inicio
          : `${parts[2]}-${parts[1]}-${parts[0]}`;
        // idem: preferir setDate quando flatpickr existir
        document.querySelectorAll('.bvgn-data-inicio').forEach(function(el){
          if (el && el._flatpickr) {
            el._flatpickr.setDate(isoInicio, true);
          } else {
            jQuery(el).val(isoInicio).trigger('change');
          }
        });
        console.log('[BVGN] Data de retirada preenchida a partir do localStorage:', { isoInicio });
      }
    } catch (e) {
      console.warn('[BVGN] Erro ao carregar dados do agendamento:', e);
    }
  }
  // Depois do bloco que trata agendamentoRaw ...
  setTimeout(function(){
    const inicioVal = $('.bvgn-data-inicio').val();
    const fimVal    = $('.bvgn-data-fim').val();
    console.log('[BVGN][CHECK-INICIAL]', { inicio: inicioVal, fim: fimVal });

    if (!inicioVal || !fimVal) {
      console.warn('[BVGN] Usuário acessou direto, datas ainda não preenchidas.');
      $('.bvgn-container').each(function(){
        setMsg($(this), 'Selecione as datas de início e fim antes de gerar a cotação.');
      });
    }
  }, 160);
  
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
    // ====== Função utilitária ======
    function definirDestinoFixo(botao) {
      // pega do atributo data-telefone do botão
      let numeroDestino = (botao.getAttribute('data-telefone') || '').replace(/\D/g, '');

      // fallback global (se definido via wp_localize_script)
      if (!numeroDestino && window.BVGN && BVGN.whatsDestino) {
        numeroDestino = String(BVGN.whatsDestino).replace(/\D/g, '');
      }

      // escreve no hidden
      const $hidById   = $('#bvgn_whats_destino');
      const $hidByName = $('input[name="whatsapp_destino"]');

      if ($hidById.length) {
        $hidById.val(numeroDestino);
      } else if ($hidByName.length) {
        $hidByName.val(numeroDestino);
      } else {
        console.warn('[BVGN] Hidden whatsapp_destino não encontrado no DOM.');
      }

      if (!numeroDestino) {
        console.warn('[BVGN] Número de destino não configurado.');
      } else {
        console.log('[BVGN] Destino fixo definido para:', numeroDestino);
      }
    }
    definirDestinoFixo(this);

     // Abre o modal
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
