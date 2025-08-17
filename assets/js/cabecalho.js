document.addEventListener('DOMContentLoaded', function(){
  const inicioEl = document.getElementById('bvgn-inicio');
  const fimEl = document.getElementById('bvgn-fim');
  const btn = document.getElementById('bvgn-buscar-grupos');

  if (!btn || !inicioEl || !fimEl) return;

  const hoje = new Date();
  const max = new Date();
  max.setDate(hoje.getDate() + 30);

  const hojeStr = hoje.toISOString().split('T')[0];
  const maxStr = max.toISOString().split('T')[0];

  // Limites nos inputs
  inicioEl.setAttribute('min', hojeStr);
  fimEl.setAttribute('min', hojeStr);
  inicioEl.setAttribute('max', maxStr);
  fimEl.setAttribute('max', maxStr);

  btn.addEventListener('click', () => {
    const inicio = new Date(inicioEl.value);
    const fim = new Date(fimEl.value);
    const local = document.getElementById('bvgn-local').value;

    if (!inicioEl.value || !fimEl.value) {
      alert('Preencha as duas datas.');
      return;
    }

    const diffMs = fim - inicio;
    const dias = diffMs / (1000 * 60 * 60 * 24);

    if (dias < 0) {
      alert('A data final deve ser depois da data inicial.');
      return;
    }

    if (dias > 30) {
      // Redirecionar para planos mensais
      window.location.href = '/planos-mensais'; // ajuste se necessário
      return;
    }

    // Salvar e redirecionar
    localStorage.setItem('bvgn_agendamento', JSON.stringify({
      inicio: inicioEl.value,
      fim: fimEl.value,
      local: local
    }));

    window.location.href = '/planos-diarios'; // ajuste se necessário
  });
});
