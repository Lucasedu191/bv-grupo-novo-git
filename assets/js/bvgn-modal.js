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

      // Pré-abre aba/janela dentro do clique do usuário (escapa de bloqueios)
      var waWin = null;
      try {
        waWin = window.open('about:blank', 'bvgn_whats');
         if (waWin && !waWin.closed) {
          waWin.document.write(
            '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<title>Redirecionando…</title>' +
            '<style>' +
              'html,body{height:100%;margin:0}' +
              'body{display:grid;place-items:center;font:16px system-ui,-apple-system,Segoe UI,Roboto,Inter,sans-serif;color:#0f172a;background:#fff}' +
              '.wrap{display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center}' +
              '.logo{height:56px;width:auto;display:block}' +
              '.spin{width:42px;height:42px;border-radius:50%;border:4px solid #ddd;border-top-color:#25d366;animation:spin 1s linear infinite}' +
              '@keyframes spin{to{transform:rotate(360deg)}}' +
            '</style></head><body>' +
              '<div class="wrap">' +
                '<img class="logo" src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png" alt="BV Locadora">' +
                '<div class="spin"></div>' +
                '<p>Redirecionando para o WhatsApp…</p>' +
              '</div>' +
            '</body></html>'
          );
          waWin.document.close();
        }
      } catch(e) {
        waWin = null; // se bloqueou
      }

      var data = new FormData(form);
      var payload = {
        produto_id:       data.get('produto_id') || null,
        nome:             (data.get('nome')||'').trim(),
        whatsapp:         (data.get('whatsapp')||'').trim(),            // número do cliente
        whatsapp_destino: (data.get('whatsapp_destino')||'').trim(),    // número fixo da BV
        mensagem:         (data.get('mensagem')||'').trim(),
        nonce:            data.get('nonce') || ''
      };

      function abrirWhats(waUrl){
      if (waWin && !waWin.closed) {
        // navega a MESMA aba aberta no clique
        waWin.location.href = waUrl;   // (pode usar .replace também)
        return;
      }
      // Se a aba foi bloqueada, tenta abrir agora em nova aba (degrada com dignidade)
      window.open(waUrl, '_blank', 'noopener');
    }

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
      // var cx = document.querySelector('.bvgn-container');
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
      var waUrl = 'https://api.whatsapp.com/send?phone=' + numeroDestinoIntl + '&text=' + encodeURIComponent(textoFallback);

      // redireciona usando a aba pré‑aberta (anti-popup)
      abrirWhats(waUrl);

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
        bvgn_local:   $('#bvgn-local-view').text() || '',
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
      // var waLink = 'https://wa.me/' + numeroDestinoIntl + '?text=' + encodeURIComponent(texto);
      var waUrl = 'https://api.whatsapp.com/send?phone=' + numeroDestinoIntl + '&text=' + encodeURIComponent(texto);

 
      abrirWhats(waUrl);
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
      var waUrl = 'https://api.whatsapp.com/send?phone=' + numeroDestinoIntl + '&text=' + encodeURIComponent(texto);

      abrirWhats(waUrl);
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
