const pad2 = (n) => String(n).padStart(2, '0');

/** Full-screen countdown for one box. Counts up in coral once the time is over. */
export function createFocus({ onComplete }) {
  const overlay = document.getElementById('focusOverlay');
  const nameEl = document.getElementById('focusName');
  const clockEl = document.getElementById('focusClock');
  const toggleBtn = document.getElementById('focusToggle');

  let task = null;
  let timer = null;
  let running = false;
  let elapsedMs = 0;
  let lastTick = 0;

  function paint() {
    const remaining = task.duration * 60 - Math.floor(elapsedMs / 1000);
    const over = remaining < 0;
    const abs = Math.abs(remaining);

    clockEl.textContent = `${over ? '+' : ''}${pad2(Math.floor(abs / 60))}:${pad2(abs % 60)}`;
    clockEl.classList.toggle('over', over);
    clockEl.classList.toggle('paused', !running);
  }

  function tick() {
    const now = Date.now();
    if (running) elapsedMs += now - lastTick;
    lastTick = now;
    paint();
  }

  function open(next) {
    task = next;
    elapsedMs = 0;
    running = true;
    lastTick = Date.now();
    nameEl.textContent = task.name;
    toggleBtn.textContent = 'Pause';
    overlay.hidden = false;
    paint();
    clearInterval(timer);
    timer = setInterval(tick, 250);
    toggleBtn.focus();
  }

  function close() {
    clearInterval(timer);
    timer = null;
    overlay.hidden = true;
    task = null;
  }

  toggleBtn.addEventListener('click', () => {
    running = !running;
    lastTick = Date.now();
    toggleBtn.textContent = running ? 'Pause' : 'Resume';
    paint();
  });

  document.getElementById('focusComplete').addEventListener('click', () => {
    const finished = task;
    close();
    if (finished) onComplete(finished);
  });

  document.getElementById('focusExit').addEventListener('click', close);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !overlay.hidden) close();
  });

  return { open, close };
}
