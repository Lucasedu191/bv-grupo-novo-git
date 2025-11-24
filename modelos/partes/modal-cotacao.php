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
    <div class="bvgn-modal__content">
    
      <div style="text-align:center; margin-bottom:20px;">
        <img src="https://bvlocadora.com.br/wp-content/uploads/2025/07/transp.png" alt="BV Locadora" style="max-width: 160px; height: auto;">
      </div>

      <!-- Mensagem em destaque -->
      <div class="bvgn-highlight" style="margin:15px 0; padding:12px; background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; text-align:center; font-weight:600; color:#92400e;">
        Gere a sua cotação e fale com o consultor.
      </div>

      <button type="button" class="bvgn-modal__close js-bvgn-close-modal" data-bvgn-modal="bvgn-cotacao-modal" aria-label="Fechar">×</button>
      <h3 id="bvgn-cotacao-titulo" class="bvgn-modal__title">Gerar cotação</h3>
      <?php if ($produto_titulo): ?>
        <p class="bvgn-modal__subtitle"><?php echo esc_html($produto_titulo); ?></p>
      <?php endif; ?>

      <form class="bvgn-form" id="bvgn-form-cotacao" novalidate>
        <input type="hidden" name="produto_id" value="<?php echo esc_attr($produto_id); ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('bvgn_nonce')); ?>">
        <input type="hidden" name="whatsapp_destino" id="bvgn_whats_destino" value="">

        <div class="bvgn-form__group">
          <label for="bvgn_nome">Nome</label>
          <input type="text" id="bvgn_nome" name="nome" required>
        </div>

        <!-- <div class="bvgn-form__group">
          <label for="bvgn_telefone">Telefone</label>
          <input type="tel" id="bvgn_telefone" name="telefone" inputmode="tel">
        </div> -->

        <div class="bvgn-form__group">
          <label for="bvgn_whats">WhatsApp</label>
          <input type="tel" id="bvgn_whats" name="whatsapp" inputmode="tel">
        </div>

        <div class="bvgn-form__group">
          <label for="mensagem">Mensagem <small>(opcional)</small></label>
          <textarea id="bvgn_msg" name="mensagem" maxlength="200" rows="3" placeholder="Detalhes da cotação..."></textarea>
        </div>

        <div class="bvgn-form__actions">
          <button type="submit" class="bvgn-btn bvgn-btn--primario">Enviar</button>
          <button type="button" class="bvgn-btn bvgn-btn--texto js-bvgn-close-modal" data-bvgn-modal="bvgn-cotacao-modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
