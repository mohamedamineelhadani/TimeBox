import { createApi } from './api.js';
import { createFocus } from './focus.js';

/* ============================================================
   Config (the server is the single source of truth for the day's limits)
   ============================================================ */
const root = document.getElementById('app');

const CFG = Object.freeze({
  base: root.dataset.base,
  csrf: root.dataset.csrf,
  dayStart: Number(root.dataset.dayStart),
  dayEnd: Number(root.dataset.dayEnd),
  snapStep: Number(root.dataset.snap),
  hourHeight: 68, // px per hour
});
const PX_PER_MIN = CFG.hourHeight / 60;
document.documentElement.style.setProperty('--hour-h', `${CFG.hourHeight}px`);

/* ============================================================
   Small helpers
   ============================================================ */
const $ = (id) => document.getElementById(id);
const pad2 = (n) => String(n).padStart(2, '0');
const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
const snap = (m) => Math.round(m / CFG.snapStep) * CFG.snapStep;
const pxFromMin = (m) => (m - CFG.dayStart) * PX_PER_MIN;
const minFromPx = (px) => CFG.dayStart + px / PX_PER_MIN;
const minToClock = (m) => `${pad2(Math.floor(m / 60) % 24)}:${pad2(Math.round(m % 60))}`;
const nowMinutes = () => {
  const d = new Date();
  return d.getHours() * 60 + d.getMinutes();
};

const els = {
  timelineScroll: $('timelineScroll'),
  ticks: $('ticks'),
  blocksCol: $('blocksCol'),
  nowLine: $('nowLine'),
  nowLabel: $('nowLabel'),
  backlogList: $('backlogList'),
  backlogEmpty: $('backlogEmpty'),
  actionBar: $('actionBar'),
  abTaskName: $('abTaskName'),
  abFocus: $('abFocus'),
  abDone: $('abDone'),
  abDelete: $('abDelete'),
  addForm: $('addForm'),
  addButton: $('addButton'),
  taskName: $('taskName'),
  chipRow: $('chipRow'),
  toast: $('toast'),
  statBoxed: $('statBoxed'),
  statDone: $('statDone'),
  statBacklog: $('statBacklog'),
  dateLabel: $('dateLabel'),
};

/* ============================================================
   State
   ============================================================ */
const api = createApi(CFG);
const focus = createFocus({ onComplete: (task) => saveTask(task, { status: 'done' }) });

const state = {
  tasks: JSON.parse(root.dataset.tasks),
  selectedId: null,
  chosenDuration: 25,
};

const byId = (id) => state.tasks.find((t) => t.id === id);
const isBoxed = (t) => t.start !== null;

/** Would a box of `duration` starting at `start` fit without overlapping another box? */
function fits(start, duration, ignoreId) {
  if (start < CFG.dayStart || start + duration > CFG.dayEnd) return false;
  return state.tasks
    .filter((t) => isBoxed(t) && t.id !== ignoreId)
    .every((t) => start + duration <= t.start || start >= t.start + t.duration);
}

/* ============================================================
   Toast
   ============================================================ */
let toastTimer = null;
function notify(message, kind = 'error') {
  els.toast.textContent = message;
  els.toast.className = `toast show ${kind}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => els.toast.classList.remove('show'), 3800);
}

/* ============================================================
   Server actions (optimistic update, rolled back if the server says no)
   ============================================================ */
async function saveTask(task, patch) {
  const before = { ...task };
  Object.assign(task, patch);
  render();

  try {
    Object.assign(task, await api.update(task.id, patch));
  } catch (error) {
    Object.assign(task, before);
    notify(error.message);
  }
  render();
}

async function deleteTask(id) {
  try {
    await api.remove(id);
  } catch (error) {
    notify(error.message);
    return;
  }
  state.tasks = state.tasks.filter((t) => t.id !== id);
  if (state.selectedId === id) state.selectedId = null;
  render();
}

async function addTask(name) {
  els.addButton.disabled = true;
  try {
    const task = await api.create({
      name,
      duration: state.chosenDuration,
      from_minute: snap(Math.max(CFG.dayStart, nowMinutes())),
    });
    state.tasks.push(task);
    els.taskName.value = '';
    render();
    if (!isBoxed(task)) notify('No free slot left today, so it went to Unscheduled.', 'info');
  } catch (error) {
    notify(error.message);
  } finally {
    els.addButton.disabled = false;
    els.taskName.focus();
  }
}

/* ============================================================
   Rendering (elements are kept by task id, so CSS can animate changes)
   ============================================================ */
let firstRender = true;
const blockEls = new Map();
const backlogEls = new Map();

function render() {
  renderNowLine();
  syncElements(blockEls, state.tasks.filter(isBoxed), {
    parent: els.blocksCol,
    create: createBlock,
    update: updateBlock,
  });
  const waiting = state.tasks.filter((t) => !isBoxed(t));
  syncElements(backlogEls, waiting, {
    parent: els.backlogList,
    create: createBacklogItem,
    update: updateBacklogItem,
  });
  els.backlogEmpty.hidden = waiting.length > 0;
  renderStats();
  renderActionBar();
  firstRender = false;
}

/** Create missing elements, update existing ones, animate away the ones that are gone. */
function syncElements(registry, tasks, { parent, create, update }) {
  const live = new Set(tasks.map((t) => t.id));

  tasks.forEach((task, index) => {
    let el = registry.get(task.id);
    if (!el) {
      el = create(task);
      // On page load the boxes stamp in one after another; later ones appear instantly.
      el.style.animationDelay = firstRender ? `${index * 45}ms` : '0ms';
      registry.set(task.id, el);
      parent.appendChild(el);
    }
    update(el, task);
  });

  for (const [id, el] of registry) {
    if (live.has(id)) continue;
    registry.delete(id);
    el.classList.remove('entering');
    el.classList.add('leaving');
    setTimeout(() => el.remove(), 240);
  }
}

function makeSelectable(el, id) {
  el.tabIndex = 0;
  el.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      selectTask(id);
    }
  });
}

/* ---------- timeline blocks ---------- */
function createBlock(task) {
  const el = document.createElement('div');
  el.className = 'block entering';
  el.innerHTML =
    '<div class="corner tl"></div><div class="corner tr"></div>' +
    '<div class="b-name"></div><div class="b-time mono"></div>' +
    '<div class="resize-handle"></div>';
  makeSelectable(el, task.id);
  attachBlockDrag(el, task.id);
  attachResize(el.querySelector('.resize-handle'), el, task.id);
  return el;
}

function updateBlock(el, task) {
  el.classList.toggle('done', task.status === 'done');
  el.classList.toggle('selected', task.id === state.selectedId);
  if (!el.classList.contains('dragging')) {
    el.style.top = `${pxFromMin(task.start)}px`;
    el.style.height = `${Math.max(20, task.duration * PX_PER_MIN - 3)}px`;
  }
  el.querySelector('.b-name').textContent = task.name;
  el.querySelector('.b-time').textContent =
    `${minToClock(task.start)}–${minToClock(task.start + task.duration)}`;
}

/* ---------- backlog items ---------- */
function createBacklogItem(task) {
  const el = document.createElement('div');
  el.className = 'backlog-item entering';
  el.innerHTML = '<div class="bi-name"></div><div class="bi-meta"></div>';
  makeSelectable(el, task.id);
  attachBacklogDrag(el, task.id);
  return el;
}

function updateBacklogItem(el, task) {
  el.classList.toggle('selected', task.id === state.selectedId);
  el.querySelector('.bi-name').textContent = task.name;
  el.querySelector('.bi-meta').textContent = `${task.duration} min · drag onto the timeline`;
}

/* ---------- now line, stats, action bar ---------- */
function renderNowLine() {
  const now = nowMinutes();
  const visible = now >= CFG.dayStart && now <= CFG.dayEnd;
  els.nowLine.style.display = visible ? 'block' : 'none';
  if (!visible) return;
  els.nowLine.style.top = `${pxFromMin(now)}px`;
  els.nowLabel.textContent = minToClock(now);
}

function setStat(el, text) {
  if (el.textContent === text) return;
  el.textContent = text;
  el.classList.remove('bump');
  void el.offsetWidth; // restart the animation
  el.classList.add('bump');
}

function renderStats() {
  const boxedMinutes = state.tasks.filter(isBoxed).reduce((sum, t) => sum + t.duration, 0);
  setStat(els.statBoxed, `${(boxedMinutes / 60).toFixed(1).replace(/\.0$/, '')}h`);
  setStat(els.statDone, String(state.tasks.filter((t) => t.status === 'done').length));
  setStat(els.statBacklog, String(state.tasks.filter((t) => !isBoxed(t)).length));
  els.dateLabel.textContent = new Date().toLocaleDateString(undefined, {
    weekday: 'short', month: 'short', day: 'numeric',
  });
}

function renderActionBar() {
  const task = byId(state.selectedId);
  els.actionBar.classList.toggle('visible', Boolean(task));
  if (!task) return;

  const unscheduled = !isBoxed(task);
  els.abTaskName.textContent = task.name;
  els.abFocus.hidden = task.status === 'done';
  els.abFocus.disabled = unscheduled;
  els.abDone.disabled = unscheduled;
  els.abDone.textContent = task.status === 'done' ? '↺ Mark not done' : '✓ Mark done';
}

function selectTask(id) {
  state.selectedId = state.selectedId === id ? null : id;
  render();
}

/* ============================================================
   Dragging
   ============================================================ */

/** Scrolls the timeline while the pointer rests near its top or bottom edge. */
function createAutoScroller(onScroll) {
  const EDGE = 56;
  const SPEED = 12;
  let direction = 0;
  let frame = 0;

  function loop() {
    if (!direction) {
      frame = 0;
      return;
    }
    els.timelineScroll.scrollTop += direction * SPEED;
    onScroll();
    frame = requestAnimationFrame(loop);
  }

  return {
    update(clientY) {
      const rect = els.timelineScroll.getBoundingClientRect();
      direction = clientY < rect.top + EDGE ? -1 : clientY > rect.bottom - EDGE ? 1 : 0;
      if (direction && !frame) frame = requestAnimationFrame(loop);
    },
    stop() {
      direction = 0;
    },
  };
}

/**
 * One pointer gesture from pointerdown to pointerup.
 * onBegin() runs once the pointer moved past `threshold`; onMove/onEnd get the
 * pointer position and `dy`, the vertical distance dragged (scrolling included).
 */
function startDrag(down, { onBegin, onMove, onEnd, threshold = 3 }) {
  const origin = { x: down.clientX, y: down.clientY, scroll: els.timelineScroll.scrollTop };
  const pointer = { x: down.clientX, y: down.clientY };
  let moved = false;

  const info = () => ({
    clientX: pointer.x,
    clientY: pointer.y,
    dy: pointer.y - origin.y + (els.timelineScroll.scrollTop - origin.scroll),
  });
  const scroller = createAutoScroller(() => moved && onMove(info()));

  function handleMove(event) {
    pointer.x = event.clientX;
    pointer.y = event.clientY;
    if (!moved) {
      if (Math.hypot(pointer.x - origin.x, pointer.y - origin.y) <= threshold) return;
      moved = true;
      onBegin?.();
    }
    onMove(info());
    scroller.update(pointer.y);
  }

  function handleEnd() {
    window.removeEventListener('pointermove', handleMove);
    window.removeEventListener('pointerup', handleEnd);
    window.removeEventListener('pointercancel', handleEnd);
    scroller.stop();
    onEnd(moved, info());
  }

  window.addEventListener('pointermove', handleMove);
  window.addEventListener('pointerup', handleEnd);
  window.addEventListener('pointercancel', handleEnd);
}

/** Backlog item → drop on the timeline to schedule it. */
function attachBacklogDrag(el, id) {
  el.addEventListener('pointerdown', (down) => {
    if (down.button !== 0) return;
    const task = byId(id);
    let ghost = null;
    let dropAt = null;

    startDrag(down, {
      onBegin() {
        ghost = document.createElement('div');
        ghost.className = 'ghost-block';
        ghost.style.height = `${task.duration * PX_PER_MIN}px`;
        els.blocksCol.appendChild(ghost);
        el.classList.add('dragging-source');
      },
      onMove({ clientX, clientY }) {
        const area = els.timelineScroll.getBoundingClientRect();
        const inside = clientX >= area.left && clientX <= area.right
          && clientY >= area.top && clientY <= area.bottom;
        ghost.hidden = !inside;
        if (!inside) {
          dropAt = null;
          return;
        }

        const columnTop = els.blocksCol.getBoundingClientRect().top;
        const start = clamp(
          snap(minFromPx(clientY - columnTop - (task.duration * PX_PER_MIN) / 2)),
          CFG.dayStart,
          CFG.dayEnd - task.duration,
        );
        const ok = fits(start, task.duration, id);
        ghost.style.top = `${pxFromMin(start)}px`;
        ghost.classList.toggle('invalid', !ok);
        dropAt = ok ? start : null;
      },
      onEnd(moved) {
        el.classList.remove('dragging-source');
        ghost?.remove();
        if (!moved) {
          selectTask(id);
        } else if (dropAt !== null) {
          saveTask(task, { start: dropAt, status: 'scheduled' });
        }
      },
    });
  });
}

/** Timeline block → drag up/down to move it. */
function attachBlockDrag(el, id) {
  el.addEventListener('pointerdown', (down) => {
    if (down.button !== 0 || down.target.closest('.resize-handle')) return;
    const task = byId(id);
    if (!task) return;
    if (task.status === 'done') {
      selectTask(id);
      return;
    }

    const originalStart = task.start;
    let candidate = originalStart;
    let ok = true;

    startDrag(down, {
      onBegin: () => el.classList.add('dragging'),
      onMove({ dy }) {
        candidate = snap(clamp(originalStart + dy / PX_PER_MIN, CFG.dayStart, CFG.dayEnd - task.duration));
        ok = fits(candidate, task.duration, id);
        el.style.top = `${pxFromMin(candidate)}px`;
        el.classList.toggle('invalid', !ok);
      },
      onEnd(moved) {
        el.classList.remove('dragging', 'invalid');
        if (!moved) {
          selectTask(id);
        } else if (ok && candidate !== originalStart) {
          saveTask(task, { start: candidate });
        } else {
          render(); // slide back to where it was
        }
      },
    });
  });
}

/** Bottom handle of a block → drag to change its duration. */
function attachResize(handle, el, id) {
  handle.addEventListener('pointerdown', (down) => {
    if (down.button !== 0) return;
    down.stopPropagation();
    const task = byId(id);
    if (!task) return;

    const originalDuration = task.duration;
    let candidate = originalDuration;
    let ok = true;

    startDrag(down, {
      threshold: 0,
      onBegin: () => el.classList.add('dragging'),
      onMove({ dy }) {
        candidate = clamp(snap(originalDuration + dy / PX_PER_MIN), CFG.snapStep, CFG.dayEnd - task.start);
        ok = fits(task.start, candidate, id);
        el.style.height = `${Math.max(20, candidate * PX_PER_MIN - 3)}px`;
        el.classList.toggle('invalid', !ok);
      },
      onEnd(moved) {
        el.classList.remove('dragging', 'invalid');
        if (moved && ok && candidate !== originalDuration) {
          saveTask(task, { duration: candidate });
        } else {
          render();
        }
      },
    });
  });
}

/* ============================================================
   UI events
   ============================================================ */
els.chipRow.addEventListener('click', (event) => {
  const chip = event.target.closest('.chip');
  if (!chip) return;
  els.chipRow.querySelectorAll('.chip').forEach((c) => c.classList.remove('active'));
  chip.classList.add('active');
  state.chosenDuration = Number(chip.dataset.min);
});

els.addForm.addEventListener('submit', (event) => {
  event.preventDefault();
  const name = els.taskName.value.trim();
  if (name) addTask(name);
});

els.abDone.addEventListener('click', () => {
  const task = byId(state.selectedId);
  if (task) saveTask(task, { status: task.status === 'done' ? 'scheduled' : 'done' });
});

els.abDelete.addEventListener('click', () => {
  if (state.selectedId !== null) deleteTask(state.selectedId);
});

els.abFocus.addEventListener('click', () => {
  const task = byId(state.selectedId);
  if (task && isBoxed(task)) focus.open(task);
});

/* ============================================================
   Start
   ============================================================ */
(function buildTicks() {
  const totalMinutes = CFG.dayEnd - CFG.dayStart;
  els.ticks.style.height = `${totalMinutes * PX_PER_MIN}px`;
  els.blocksCol.style.height = `${totalMinutes * PX_PER_MIN}px`;

  for (let m = CFG.dayStart; m <= CFG.dayEnd; m += 60) {
    const tick = document.createElement('div');
    tick.className = 'tick';
    tick.style.top = `${pxFromMin(m)}px`;
    tick.textContent = minToClock(m);
    els.ticks.appendChild(tick);
  }
})();

render();

const target = pxFromMin(clamp(nowMinutes(), CFG.dayStart, CFG.dayEnd)) - els.timelineScroll.clientHeight * 0.35;
els.timelineScroll.scrollTop = Math.max(0, target);

setInterval(renderNowLine, 15000);
