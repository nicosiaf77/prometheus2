document.addEventListener('DOMContentLoaded', () => {
  const eventSelect = document.querySelector('[data-has-event]');
  const eventName = document.querySelector('[data-event-name]');

  if (!eventSelect || !eventName) {
    return;
  }

  eventSelect.addEventListener('change', () => {
    eventName.hidden = eventSelect.value !== '1';
  });
});
