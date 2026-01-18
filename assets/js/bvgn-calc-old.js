(function (window, $) {
  'use strict';

  const { numero, diferencaDiasSeguro, getTipo } = BVGN.utils;

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

    // 1) Proteção (radio) — data-preco-dia; no mensal soma 0; caução entra só no diário
    const $prot = $cx.find('input[name="bvgn_protecao"]:checked');
    if ($prot.length) {
      const preco = numero($prot.data('preco-dia'));
      const caucao = numero($prot.data('caucao'));
      const nomeProt = String($prot.closest('label').find('.texto').clone().children().remove().end().text()).trim();
      const precoProt = numero($prot.data('preco-dia'));

      const valorProt = (tipo === 'diario') ? (preco * qtd) : 0;
      taxas += valorProt;

      if (tipo === 'diario' && caucao > 0) {
        taxas += caucao;
      }

      const rotuloProt = `${nomeProt} — R$ ${precoProt.toFixed(2).replace('.', ',')}`;
      if ($cx.find('#bvgn-taxas-itens').length) {
        $cx.find('#bvgn-taxas-itens').append(`<li>${rotuloProt}</li>`);
      }

      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text(rotuloProt);
    }

    // 2) taxas selecionadas (checkbox): mensal = diárias *30; limpeza 1x; caucão não soma
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotulo = String($(this).data('rotulo') || '').toLowerCase();
      const preco  = numero($(this).data('preco'));
      const isDiaria = rotulo.includes('(diaria)');
      const isCaucao = /cau[cç][aã]o/.test(rotulo);

      if (tipo === 'diario') {
        taxas += isDiaria ? (preco * qtd) : preco;
      } else {
        if (!isCaucao) taxas += isDiaria ? (preco * 30) : preco;
      }
    });

    // 3) Taxas fixas (ex.: limpeza obrigatória, caução): caução não entra no mensal
    $cx.find('.bvgn-taxa-fixa-input').each(function () {
      const $el      = $(this);
      const rotulo   = String($el.data('rotulo') || '').toLowerCase();
      const preco    = numero($el.data('preco'));
      const isCaucao = /cau[cç][aã]o/.test(rotulo) || $el.data('tipo') === 'caucao';

      if (isCaucao) {
        if (tipo === 'diario') taxas += preco; // mensal: exibe mas não soma
      } else {
        taxas += preco;                        // limpeza etc.: 1x em ambos
      }
    });

    // 4) SUBTOTAL / TOTAL — mensal = plano 1x; diário = * dias; sempre soma taxas
    const dias = (tipo === 'mensal') ? 30 : qtd;   // apenas exibição
    const subtotal = (tipo === 'mensal') ? base : (base * dias);
    const total    = subtotal + taxas;

    $cx.find('.bvgn-subtotal .valor').text(subtotal.toFixed(2).replace('.', ','));
    $cx.find('.bvgn-total .valor').text(total.toFixed(2).replace('.', ','));

    // — Preenchimento do novo template visual —
    $cx.find('.bvgn-local').show();
    $cx.find('#bvgn-local-view').text('BV Locadora, Avenida Brigadeiro Eduardo Gomes, 3571');

    const isMensal = $('.bvgn-variacoes[data-bvgn-tipo="mensal"]').length > 0;
    if (isMensal) qtd = 30;

    $cx.find('.bvgn-dias').show();
    $cx.find('#bvgn-days-view').text(`${dias} dia${dias > 1 ? 's' : ''}`);
    $cx.find('#bvgn-days-raw').val(dias);

    $cx.find('.bvgn-taxas').show();
    $cx.find('#bvgn-taxas').text(taxas.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-taxas-raw').val(taxas);

    $cx.find('#bvgn-subtotal-raw').val(subtotal);
    $cx.find('#bvgn-total-raw').val(total);

    $cx.find('#bvgn-subtotal-view').text(subtotal.toFixed(2).replace('.', ','));
    $cx.find('#bvgn-total-view').text(total.toFixed(2).replace('.', ','));

    // Lista de opcionais (somente exibição)
    const opcionais = [];
    $cx.find('.bvgn-taxa input[type=checkbox]:checked').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco  = numero($(this).data('preco'));
      opcionais.push(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
    });
    $cx.find('.bvgn-taxa-fixa-input').each(function(){
      const rotulo = String($(this).data('rotulo') || '').trim();
      const preco  = numero($(this).data('preco'));
      if (rotulo) opcionais.push(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
    });
    const $lista = $cx.find('#bvgn-taxas-itens');
    $lista.empty();
    opcionais.forEach(t => $lista.append(`<li>${t}</li>`));
    if (opcionais.length) $cx.find('.bvgn-taxas-lista').show();

    // Exibir variação
    const $var = $cx.find('.bvgn-variacao input[type=radio]:checked');
    if ($var.length) {
      const rotulo = String($var.data('rotulo') || 'Plano selecionado');
      const preco  = numero($var.data('preco') || base);
      $cx.find('.bvgn-var').show();
      $cx.find('#bvgn-var-view').text(`${rotulo} — R$ ${preco.toFixed(2).replace('.', ',')}`);
    }

    // Proteção no resumo (mensal exibe "incluída")
    if (tipo === 'mensal') {
      $cx.find('.bvgn-protecao').show();
      $cx.find('#bvgn-protecao-view').text('Proteção básica — incluída');
    } else if ($prot.length) {
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

    // Guarda totais
    $cx.data('bvgnTotais', { base, taxas, qtd, subtotal, total, tipo });
    // Ajuste de títulos
    if (tipo === 'mensal') {
      $cx.find('.bvgn-opcionais .resumo-label').text('Taxas / Serviços opcionais:');
      $cx.find('.bvgn-servicos-opcionais .bvgn-totais-titulo').text('Taxas e serviços opcionais');
    } else {
      $cx.find('.bvgn-opcionais .resumo-label').text('Serviços opcionais:');
      $cx.find('.bvgn-servicos-opcionais .bvgn-totais-titulo').text('Serviços Opcionais');
    }
  }

  // Expor
  BVGN.calcular = calcular;

})(window, jQuery);
