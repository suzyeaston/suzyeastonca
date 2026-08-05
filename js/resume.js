(function () {
  var printBtn = document.querySelector('[data-resume-print]');
  if (!printBtn) {
    return;
  }

  printBtn.addEventListener('click', function () {
    window.print();
  });
})();
