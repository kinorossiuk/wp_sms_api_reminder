(() => {
  const scrollKey = 'rossi-sms-scroll-y';
  const savedScroll = sessionStorage.getItem(scrollKey);
  if (savedScroll !== null) {
    sessionStorage.removeItem(scrollKey);
    window.requestAnimationFrame(() => window.scrollTo(0, Number(savedScroll)));
  }

  const refreshForm = document.querySelector('[data-sms-status-refresh]');
  if (refreshForm) {
    const saveScroll = () => sessionStorage.setItem(scrollKey, String(window.scrollY));
    refreshForm.addEventListener('submit', saveScroll);
    window.setInterval(() => {
      if (!document.hidden) {
        saveScroll();
        refreshForm.requestSubmit();
      }
    }, 60_000);
  }

  const form = document.querySelector('[data-sms-schedule-form]');
  if (!form) return;

  const dateInput = form.querySelector('[data-schedule-date]');
  const timeInput = form.querySelector('[data-schedule-time]');
  const valueInput = form.querySelector('[data-schedule-value]');
  const summary = form.querySelector('[data-schedule-summary]');
  const pad = (value) => String(value).padStart(2, '0');
  const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
  const minimum = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() + 1, 0, 0);
    return date;
  };
  const selected = () => new Date(`${dateInput.value}T${timeInput.value}:00`);
  const sync = () => {
    valueInput.value = `${dateInput.value}T${timeInput.value}`;
    const date = selected();
    if (!Number.isNaN(date.getTime())) {
      summary.textContent = `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일 ${formatTime(date)}에 발송됩니다.`;
    }
  };
  const setDateTime = (date) => {
    const rounded = new Date(date);
    rounded.setSeconds(0, 0);
    dateInput.value = formatDate(rounded);
    timeInput.value = formatTime(rounded);
    sync();
  };

  dateInput.addEventListener('change', sync);
  timeInput.addEventListener('change', sync);
  form.querySelectorAll('[data-schedule-day]').forEach((button) => button.addEventListener('click', () => {
    const date = selected();
    const base = Number.isNaN(date.getTime()) ? minimum() : date;
    if (button.dataset.scheduleDay === 'today') {
      const now = minimum();
      setDateTime(new Date(now.getFullYear(), now.getMonth(), now.getDate(), base.getHours(), base.getMinutes()));
    } else {
      setDateTime(new Date(base.getFullYear(), base.getMonth(), base.getDate() + 1, base.getHours(), base.getMinutes()));
    }
  }));
  form.querySelectorAll('[data-schedule-offset]').forEach((button) => button.addEventListener('click', () => {
    const date = minimum();
    date.setMinutes(date.getMinutes() + Number(button.dataset.scheduleOffset));
    setDateTime(date);
  }));
  form.querySelectorAll('[data-schedule-time-preset]').forEach((button) => button.addEventListener('click', () => {
    timeInput.value = button.dataset.scheduleTimePreset;
    sync();
  }));
  form.addEventListener('submit', (event) => {
    sync();
    const date = selected();
    if (Number.isNaN(date.getTime()) || date < minimum()) {
      event.preventDefault();
      summary.textContent = '예약 시각은 현재부터 1분 이후로 설정해 주세요.';
      dateInput.focus();
    }
  });
  sync();
})();
