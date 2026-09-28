// Vaasal Villa IMS — shared back-office behaviour
(function () {
  // Confirmation for dangerous actions: <form data-confirm="..."> or <button data-confirm="...">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    var btn = e.submitter;
    if (btn && btn.getAttribute('data-confirm')) msg = btn.getAttribute('data-confirm');
    if (msg && !confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-confirm]');
    if (a && !confirm(a.getAttribute('data-confirm'))) e.preventDefault();
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar,.menu-toggle')) {
      document.body.classList.remove('nav-open');
    }
  });
  // Fade out success alerts
  document.querySelectorAll('.alert-success').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 600); }, 5000);
  });
  // Live nights/total calculation on booking forms
  var form = document.querySelector('[data-booking-form]');
  if (form) {
    var calc = function () {
      var i = form.querySelector('[name=check_in]').value, o = form.querySelector('[name=check_out]').value;
      var sel = form.querySelector('[name=room_type_id]');
      var rateInput = form.querySelector('[name=rate_per_night]');
      var base = sel && sel.selectedOptions[0] ? parseFloat(sel.selectedOptions[0].dataset.rate || 0) : 0;
      var rate = rateInput && rateInput.value !== '' ? parseFloat(rateInput.value) : base;
      if (rateInput) rateInput.placeholder = base.toFixed(2);
      var n = (i && o) ? Math.round((new Date(o) - new Date(i)) / 86400000) : 0;
      var out = form.querySelector('[data-total]');
      if (out) out.textContent = n > 0 ? n + ' night(s) × ' + rate.toFixed(2) + ' = ' + (n * rate).toFixed(2) : 'Select dates';
    };
    form.addEventListener('input', calc); form.addEventListener('change', calc); calc();
  }
})();
