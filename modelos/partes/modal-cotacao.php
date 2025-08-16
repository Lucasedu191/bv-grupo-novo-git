<?php
/**
 * Modal de cotação — HTML acessível
 * $modal_data: produto_id, produto_titulo, nonce
 */
$produto_id     = isset($modal_data['produto_id']) ? (int)$modal_data['produto_id'] : 0;
$produto_titulo = isset($modal_data['produto_titulo']) ? $modal_data['produto_titulo'] : '';
$nonce          = isset($modal_data['nonce']) ? $modal_data['nonce'] : '';
?>
<div class="bvgn-modal" id="bvgn-cotacao-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="bvgn-cotacao-titulo">
  <div class="bvgn-modal__backdrop js-bvgn-close-modal" data-bvgn-modal="bvgn-cotacao-modal" tabindex="-1"></div>

  <div class="bvgn-modal__dialog" role="document">
    <button type="button" class="bvgn-modal__close js-bvgn-close-modal" data-bvgn-modal="bvgn-cotacao-modal" aria-label="Fechar">×</button>

    <h3 id="bvgn-cotacao-titulo" class="bvgn-modal__title">Gerar cotação</h3>
    <?php if ($produto_titulo): ?>
      <p class="bvgn-modal__subtitle"><?php echo esc_html($produto_titulo); ?></p>
    <?php endif; ?>

    <form class="bvgn-form" id="bvgn-form-cotacao" novalidate>
      <input type="hidden" name="produto_id" value="<?php echo esc_attr($produto_id); ?>">
      <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

      <div class="bvgn-form__group">
        <label for="bvgn_nome">Nome</label>
        <input type="text" id="bvgn_nome" name="nome" required>
      </div>

      <div class="bvgn-form__group">
        <label for="bvgn_whats">WhatsApp</label>
        <input type="tel" id="bvgn_whats" name="whatsapp" inputmode="tel" required>
      </div>

      <div class="bvgn-form__group">
        <label for="bvgn_msg">Mensagem</label>
        <textarea id="bvgn_msg" name="mensagem" rows="3" placeholder="Detalhes da cotação..."></textarea>
      </div>

      <div class="bvgn-form__actions">
        <button type="submit" class="bvgn-btn bvgn-btn--primario">Enviar</button>
        <button type="button" class="bvgn-btn bvgn-btn--texto js-bvgn-close-modal" data-bvgn-modal="bvgn-cotacao-modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>
