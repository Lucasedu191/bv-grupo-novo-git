(function(){
  function qs(s, c){ return (c||document).querySelector(s); }
  function qsa(s, c){ return Array.prototype.slice.call((c||document).querySelectorAll(s)); }

  function openModal(id){
    var el = qs('#'+id);
    if(!el) return;
    el.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('bvgn-modal-open');
  }
  function closeModal(id){
    var el = qs('#'+id);
    if(!el) return;
    el.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('bvgn-modal-open');
  }

  qsa('.js-bvgn-open-modal').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var id = btn.getAttribute('data-bvgn-modal');
      if(id) openModal(id);
    });
  });

  qsa('.js-bvgn-close-modal').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var id = btn.getAttribute('data-bvgn-modal');
      if(id) closeModal(id);
    });
  });

  var form = qs('#bvgn-form-cotacao');
  if(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();

      var data = new FormData(form);
      var payload = {
        produto_id:       data.get('produto_id') || null,
        nome:             (data.get('nome')||'').trim(),
        whatsapp:         (data.get('whatsapp')||'').trim(),            // número do cliente
        whatsapp_destino: (data.get('whatsapp_destino')||'').trim(),    // número fixo da BV
        mensagem:         (data.get('mensagem')||'').trim(),
        nonce:            data.get('nonce') || ''
      };

      // Normalização (somente dígitos)
      var numeroCliente = (payload.whatsapp || '').replace(/\D/g,'');
      var numeroDestino = (payload.whatsapp_destino || '').replace(/\D/g,'');

      // Fallback do destino a partir do global, se precisar
      if (!numeroDestino && window.BVGN && BVGN.whatsDestino) {
        numeroDestino = String(BVGN.whatsDestino).replace(/\D/g,'');
      }

      // Remover zeros à esquerda (ex: "0095..." → "95...")
      numeroCliente = numeroCliente.replace(/^0+/, '');
      numeroDestino = numeroDestino.replace(/^0+/, '');

      // Validações
      if(!payload.nome){
        alert('Informe seu nome.');
        return;
      }
      if (!numeroCliente) {
        alert('Informe um número de WhatsApp válido.');
        return;
      }
      if (!numeroDestino) {
        alert('Número de destino do WhatsApp não configurado.');
        return;
      }

      // Formato internacional (apenas para envio/links)
      var numeroClienteIntl = (numeroCliente.length <= 11) ? ('55' + numeroCliente) : numeroCliente;
      var numeroDestinoIntl = (numeroDestino.length <= 11) ? ('55' + numeroDestino) : numeroDestino;
      
      

      // 1) Notifica outros listeners (se houver)
      window.dispatchEvent(new CustomEvent('bvgn:cotacaoModalSubmit', { detail: payload }));

      // ===== Coletar dados da página (variações, datas, taxas, totais, observações) =====
      var cx = document.querySelector('.bvgn-container'); // 1 produto por página
      var variacaoRotulo = '';
      var datas = { inicio:'', fim:'' };
      var taxasSel = [];
      var totais = null;
      var infoCliente = '';

      if (cx) {
        var vChecked = cx.querySelector('.bvgn-variacao input[type=radio]:checked');
        if (vChecked) variacaoRotulo = (vChecked.dataset.rotulo || '').trim();

        var di = cx.querySelector('.bvgn-data-inicio');
        var df = cx.querySelector('.bvgn-data-fim');
        datas.inicio = di ? (di.value || '') : '';
        datas.fim    = df ? (df.value || '') : '';

        cx.querySelectorAll('.bvgn-taxa input[type=checkbox]:checked').forEach(function(chk){
          taxasSel.push({
            rotulo: (chk.dataset.rotulo || '').trim(),
            preco:  (chk.dataset.preco  || '').trim()
          });
        });

        // Proteção (radio) — incluir na lista de taxas selecionadas
        var prot = cx.querySelector('input[name="bvgn_protecao"]:checked');
        if (prot) {
          var precoProt = prot.dataset.precoDia || '0';
          var rotuloProt = (prot.closest('label')?.querySelector('.lbl')?.textContent || 'Proteção').trim();

          taxasSel.push({
            rotulo: rotuloProt,
            preco: precoProt
          });
        }

        try { totais = jQuery(cx).data('bvgnTotais') || null; } catch(_){}
        var inf = cx.querySelector('.bvgn-informacoes');
        infoCliente = inf ? (inf.value || '') : '';
      }

      // ===== Se não houver BVGN.ajaxUrl, faz fallback direto para o WhatsApp (sem PDF) =====
      if (!window.BVGN || !BVGN.ajaxUrl) {
        var linhasFallback = [];
        linhasFallback.push('Olá! Quero uma cotação.');
        if (payload.nome) linhasFallback.push('Nome: ' + payload.nome);
        linhasFallback.push('Whats do cliente: +' + numeroClienteIntl);
        // Nome do produto (em vez do ID)
        var produtoNome = '';
        var cx = document.querySelector('.bvgn-container');
        if (cx) {
          var t = cx.querySelector('h1.product_title, .product_title, .entry-title, h1') ||
                  document.querySelector('h1.product_title, .product_title, .entry-title, h1');
          if (t && t.textContent) produtoNome = t.textContent.trim();
          if (!produtoNome) {
            var dataNome = cx.getAttribute('data-produto-nome');
            if (dataNome) produtoNome = String(dataNome).trim();
          }
        }
        if (produtoNome) linhasFallback.push('Produto: ' + produtoNome);

        if (variacaoRotulo) linhasFallback.push('Variação: ' + variacaoRotulo);
        if (datas.inicio || datas.fim) linhasFallback.push('Período: ' + (datas.inicio || '—') + ' até ' + (datas.fim || '—'));
        if (infoCliente) linhasFallback.push('Observações: ' + infoCliente);
        if (payload.mensagem) linhasFallback.push('Mensagem: ' + payload.mensagem);

        var textoFallback = linhasFallback.join('\n');
        window.location.href = 'https://wa.me/' + numeroDestinoIntl + '?text=' + encodeURIComponent(textoFallback);
        closeModal('bvgn-cotacao-modal');
        return;
      }

      // ===== Monta carga p/ gerar PDF (mesmo formato do botão antigo) =====
      var carga = {
        action:       'bvgn_gerar_arquivo',
        _wpnonce:     (BVGN.nonce || ''),
        produtoId:    payload.produto_id,
        informacoes:  infoCliente,
        variacaoRotulo: variacaoRotulo,
        datas:        datas,
        taxas:        taxasSel,
        totais:       totais,
        formato:      'pdf',
        telefone:     numeroDestinoIntl,   // destino fixo (BV)
        bvgn_nome:    payload.nome,
        bvgn_whats:   numeroClienteIntl,   // whatsapp do cliente
      };

      // ===== Chama backend, pega URL do PDF e abre o WhatsApp com o link =====
      var $ = window.jQuery;
      var btnSubmit = form.querySelector('button[type="submit"]');
      if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.textContent = 'Gerando...'; }

      $.post(BVGN.ajaxUrl, carga, function(r){
      var linhas = [];
      linhas.push('Olá vim do site e gostaria de fazer uma cotação');
      // No futuro você pode querer incluir dados aqui:
      // linhas.push('Olá! Quero uma cotação.');
      // if (payload.nome) linhas.push('Nome: ' + payload.nome);
      // linhas.push('Whats do cliente: +' + numeroClienteIntl);
      // if (payload.produto_id) linhas.push('Produto ID: ' + payload.produto_id);
      // if (variacaoRotulo) linhas.push('Variação: ' + variacaoRotulo);
      // if (datas.inicio || datas.fim) linhas.push('Período: ' + (datas.inicio || '—') + ' até ' + (datas.fim || '—'));
      // if (infoCliente) linhas.push('Observações: ' + infoCliente);
      // if (payload.mensagem) linhas.push('Mensagem: ' + payload.mensagem);

      // Anexa link do PDF se o backend retornou
      var pdfUrl = (r && r.success && r.data && r.data.url) ? r.data.url : '';
      if (pdfUrl) linhas.push('PDF da cotação: ' + pdfUrl);

      var texto = linhas.join('\n');
      var waLink = 'https://wa.me/' + numeroDestinoIntl + '?text=' + encodeURIComponent(texto);
      window.open(waLink, '_blank');
    })
    .fail(function(){
      alert('Não foi possível gerar o PDF agora. Vou abrir o WhatsApp sem o link do arquivo.');
      var linhas = [];
      linhas.push('Olá! Quero uma cotação.');
      if (payload.nome) linhas.push('Nome: ' + payload.nome);
      linhas.push('Whats do cliente: +' + numeroClienteIntl);
      
      var produtoNome = '';
      if (cx) {
        var t =
          cx.querySelector('h1.product_title, .product_title, .entry-title, h1') ||
          document.querySelector('h1.product_title, .product_title, .entry-title, h1');
        if (t && t.textContent) {
          produtoNome = t.textContent.trim();
        }
      }
      if (produtoNome) {
        linhas.push('Produto: ' + produtoNome);
      }
      if (variacaoRotulo) linhas.push('Variação: ' + variacaoRotulo);
      if (datas.inicio || datas.fim) linhas.push('Período: ' + (datas.inicio || '—') + ' até ' + (datas.fim || '—'));

      var texto = linhas.join('\n');
      window.location.href = 'https://wa.me/' + numeroDestinoIntl + '?text=' + encodeURIComponent(texto);
    })
    .always(function(){
      if (btnSubmit) { btnSubmit.disabled = false; btnSubmit.textContent = 'Enviar'; }
      if (window.BVGN && typeof window.BVGN.onModalSubmit === 'function') {
        try { window.BVGN.onModalSubmit(payload); } catch(e){}
      }
      closeModal('bvgn-cotacao-modal');
      localStorage.removeItem('bvgn_agendamento');
    });

    });
  }
})();
