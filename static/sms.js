(() => {
  const form = document.querySelector('[data-sms-status-refresh]');
  if (!form) return;

  window.setInterval(() => {
    if (!document.hidden) form.requestSubmit();
  }, 60_000);
})();
