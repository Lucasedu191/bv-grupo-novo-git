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
        produto_id: data.get('produto_id') || null,
        nome:       (data.get('nome')||'').trim(),
        whatsapp:   (data.get('whatsapp')||'').trim(),  // destino
        mensagem:   (data.get('mensagem')||'').trim(),
        nonce:      data.get('nonce') || ''
      };
      

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

      // valida campos obrigatórios do modal
        if(!payload.nome){
        alert('Informe seu nome.');
        return;
        }
        var numero = (payload.whatsapp || '').replace(/\D/g,'');
        if (!numero) {
        alert('Informe um número de WhatsApp válido.');
        return;
        }
        if (numero.length <= 11) numero = '55' + numero;


      // ===== Se não houver BVGN.ajaxUrl, faz fallback direto para o WhatsApp (sem PDF) =====
      if (!window.BVGN || !BVGN.ajaxUrl) {
        var linhasFallback = [];
        linhasFallback.push('Olá! Quero uma cotação.');
        if (payload.produto_id) linhasFallback.push('Produto ID: ' + payload.produto_id);
        if (variacaoRotulo)     linhasFallback.push('Variação: ' + variacaoRotulo);
        if (datas.inicio || datas.fim) linhasFallback.push('Período: ' + (datas.inicio || '—') + ' até ' + (datas.fim || '—'));
        if (infoCliente) linhasFallback.push('Observações: ' + infoCliente);
        if (payload.nome) linhasFallback.push('Nome: ' + payload.nome);
        if (payload.mensagem) linhasFallback.push('Mensagem: ' + payload.mensagem);
        var textoFallback = linhasFallback.join('\n');
        window.location.href = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(textoFallback);
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
        telefone:     numero   // opcional: servidor pode registrar destino
      };

      // ===== Chama backend, pega URL do PDF e abre o WhatsApp com o link =====
      var $ = window.jQuery;
      var btnSubmit = form.querySelector('button[type="submit"]');
      if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.textContent = 'Gerando...'; }

      $.post(BVGN.ajaxUrl, carga, function(r){

        var texto = 'Olá vim do site e gostaria de fazer uma cotação';

        var pdfUrl = (r && r.success && r.data && r.data.url) ? r.data.url : '';
        
        if (pdfUrl) {
          texto += '\n' + pdfUrl
        };

        
        var waLink = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(texto);
        // window.location.href = waLink;
        window.open(waLink, '_blank');
      })
      .fail(function(){
        alert('Não foi possível gerar o PDF agora. Vou abrir o WhatsApp sem o link do arquivo.');
        var linhas = [];
        linhas.push('Olá! Quero uma cotação.');
        if (payload.produto_id) linhas.push('Produto ID: ' + payload.produto_id);
        if (variacaoRotulo)     linhas.push('Variação: ' + variacaoRotulo);
        if (datas.inicio || datas.fim) linhas.push('Período: ' + (datas.inicio || '—') + ' até ' + (datas.fim || '—'));
        var texto = linhas.join('\n');
        window.location.href = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(texto);
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
