(function(w,d){
  w.BVGNProtecao = {
    getValores: function(dias){
      var sel = d.querySelector('input[name="bvgn_protecao"]:checked');
      if (!sel) return {protecao: 0, caucao: 0, tipo: 'sem'};

      var precoDia = Number(sel.dataset.precoDia || 0);
      var caucao   = Number(sel.dataset.caucao || 0);
      var tipo     = sel.value || 'sem';

      if (tipo !== 'sem') caucao = 0;

      return {
        protecao: precoDia * Math.max(1, dias || 1),
        caucao: caucao,
        tipo: tipo
      };
    }
  };

  d.addEventListener('change', function(e){
    if (e.target?.name === 'bvgn_protecao') {
      if (typeof w.bvgn_recalcularTotais === 'function') {
        w.bvgn_recalcularTotais();
      }
    }
  });
})(window, document);
