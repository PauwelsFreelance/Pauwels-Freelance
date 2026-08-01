document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Submissions page: live "New total" recalculation ---------- */
  var inputs = document.querySelectorAll('.price-line');
  var totalDisplay = document.getElementById('newTotalDisplay');
  var hiddenInput = document.getElementById('finalPriceInput');

  if (inputs.length && totalDisplay && hiddenInput) {
    var recalc = function () {
      var sum = 0;
      inputs.forEach(function (el) {
        sum += parseInt(el.value, 10) || 0;
      });
      totalDisplay.textContent = sum.toLocaleString('cs-CZ') + ' Kč';
      hiddenInput.value = sum;
    };
    inputs.forEach(function (el) {
      el.addEventListener('input', recalc);
    });
  }

});
