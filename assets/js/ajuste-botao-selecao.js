document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.bvgn-taxa input[type="radio"], .bvgn-taxa input[type="checkbox"]').forEach(input => {
    input.addEventListener('change', () => {
      const isRadio = input.type === 'radio';
      const name = input.name;

      // para radio: limpa todos os botões
      if (isRadio) {
        document.querySelectorAll(`input[name="${name}"]`).forEach(i => {
          const fake = i.closest('.bvgn-taxa')?.querySelector('.botao-fake');
          if (fake) fake.textContent = 'Selecionar';
        });
      }

      // altera o botão atual
      const fakeBtn = input.closest('.bvgn-taxa')?.querySelector('.botao-fake');
      if (fakeBtn) {
        fakeBtn.textContent = input.checked ? 'Selecionado' : 'Selecionar';
      }
    });
  });
});
