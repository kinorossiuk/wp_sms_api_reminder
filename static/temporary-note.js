(() => {
  'use strict';
  const root = document.querySelector('[data-temporary-note-tool]');
  if (!root) return;
  const storageKey = 'rossi-tools-temporary-notes-v1';
  const input = root.querySelector('#temporary-note-input');
  const saveButton = root.querySelector('#temporary-note-save');
  const clearButton = root.querySelector('#temporary-note-clear');
  const items = root.querySelector('#temporary-note-items');
  const count = root.querySelector('#temporary-note-count');
  const expiry = root.querySelector('#temporary-note-expiry');
  const status = root.querySelector('#temporary-note-status');
  const formatter = new Intl.DateTimeFormat('ko-KR', { timeZone: 'Asia/Seoul', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  const kstParts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Seoul', year: 'numeric', month: '2-digit', day: '2-digit' });
  const setStatus = (message, isError = false) => { status.textContent = message; status.classList.toggle('is-error', isError); };
  const nextKstMidnight = () => {
    const parts = Object.fromEntries(kstParts.formatToParts(new Date()).map(({ type, value }) => [type, value]));
    return Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day) + 1, -9, 0, 0);
  };
  const readNotes = () => {
    try {
      const parsed = JSON.parse(localStorage.getItem(storageKey) ?? '[]');
      return Array.isArray(parsed) ? parsed.filter((note) => note && typeof note.text === 'string' && Number.isFinite(note.expiresAt)) : [];
    } catch { return []; }
  };
  const writeNotes = (notes) => localStorage.setItem(storageKey, JSON.stringify(notes));
  const purgeExpired = () => { const notes = readNotes().filter((note) => note.expiresAt > Date.now()); writeNotes(notes); return notes; };
  const updateCount = () => { count.textContent = `${input.value.length.toLocaleString('ko-KR')} / 5,000`; };
  const render = () => {
    const notes = purgeExpired().sort((left, right) => right.createdAt - left.createdAt);
    items.replaceChildren(); clearButton.disabled = notes.length === 0;
    expiry.textContent = `다음 자동 삭제: ${formatter.format(new Date(nextKstMidnight()))} KST`;
    if (notes.length === 0) { const empty = document.createElement('p'); empty.className = 'temporary-note-empty'; empty.textContent = '저장된 임시 메모가 없습니다.'; items.append(empty); return; }
    notes.forEach((note) => {
      const item = document.createElement('article'); item.className = 'temporary-note-item';
      const body = document.createElement('div'); const text = document.createElement('p'); text.textContent = note.text;
      const time = document.createElement('time'); time.dateTime = new Date(note.createdAt).toISOString(); time.textContent = `${formatter.format(new Date(note.createdAt))} 저장`; body.append(text, time);
      const remove = document.createElement('button'); remove.className = 'ghost'; remove.type = 'button'; remove.textContent = '삭제';
      remove.addEventListener('click', () => { writeNotes(readNotes().filter((entry) => entry.id !== note.id)); render(); setStatus('메모를 삭제했습니다.'); });
      item.append(body, remove); items.append(item);
    });
  };
  saveButton.addEventListener('click', () => {
    const text = input.value.trim();
    if (!text) { setStatus('저장할 메모를 입력해 주세요.', true); input.focus(); return; }
    try {
      const notes = purgeExpired(); notes.push({ id: crypto.randomUUID(), text, createdAt: Date.now(), expiresAt: nextKstMidnight() }); writeNotes(notes);
      input.value = ''; updateCount(); render(); setStatus('현재 브라우저에 임시 메모를 저장했습니다.');
    } catch { setStatus('브라우저 저장 공간을 사용할 수 없습니다.', true); }
  });
  clearButton.addEventListener('click', () => { localStorage.removeItem(storageKey); render(); setStatus('모든 임시 메모를 삭제했습니다.'); });
  input.addEventListener('input', updateCount);
  input.addEventListener('keydown', (event) => { if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); saveButton.click(); } });
  updateCount(); render();
  window.setTimeout(() => { render(); setStatus('자정이 지나 만료된 메모를 정리했습니다.'); }, Math.max(1_000, nextKstMidnight() - Date.now() + 1_000));
})();
