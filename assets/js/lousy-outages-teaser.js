(function(){
  const container = document.getElementById('lousy-outages-teaser');
  if (!container) return;

  const summaryEl = container.querySelector('[data-lo-summary]');
  if (!summaryEl) return;

  const iso = summaryEl.getAttribute('data-incident-start');
  if (!iso) return;

  function formatRelative(date) {
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    if (!isFinite(diff) || diff < 0) {
      return 'just now';
    }
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) {
      return 'just now';
    }
    if (minutes < 60) {
      return minutes + ' minute' + (minutes === 1 ? '' : 's') + ' ago';
    }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
      const rem = minutes % 60;
      if (rem === 0) {
        return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
      }
      return hours + 'h ' + rem + 'm ago';
    }
    const days = Math.floor(hours / 24);
    return days + ' day' + (days === 1 ? '' : 's') + ' ago';
  }

  const startDate = new Date(iso);
  if (isNaN(startDate.getTime())) return;

  function update() {
    summaryEl.setAttribute('data-relative', formatRelative(startDate));
  }

  update();
  setInterval(update, 60000);
})();
