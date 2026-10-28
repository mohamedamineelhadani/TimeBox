<div class="app">
  <header class="topbar">
    <div>
      <div class="wordmark"><span class="box-glyph"></span>TimeBox</div>
      <div class="tagline">plan the day in boxes, not lists</div>
    </div>
    <div class="stats">
      <span class="date" id="dateLabel"></span>
      <span><span class="stat-value" id="statBoxed">0h</span> boxed</span>
      <span><span class="stat-value" id="statDone">0</span> done</span>
      <span><span class="stat-value" id="statBacklog">0</span> unscheduled</span>
      <form class="logout-form" method="post" action="<?= $e($url('/logout')) ?>">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <button type="submit" class="link-btn">Log out</button>
      </form>
    </div>
  </header>

  <div class="layout" id="app"
       data-base="<?= $e($url('')) ?>"
       data-csrf="<?= $e($csrf) ?>"
       data-day-start="<?= (int) $dayStart ?>"
       data-day-end="<?= (int) $dayEnd ?>"
       data-snap="<?= (int) $snap ?>"
       data-tasks="<?= $e(json_encode($tasks, JSON_THROW_ON_ERROR)) ?>">
    <aside>
      <div class="panel-label">New task</div>
      <form class="add-form" id="addForm">
        <input type="text" id="taskName" placeholder="What needs boxing?" maxlength="60" autocomplete="off" required>
        <div class="chip-row" id="chipRow">
          <button type="button" class="chip" data-min="15">15m</button>
          <button type="button" class="chip active" data-min="25">25m</button>
          <button type="button" class="chip" data-min="45">45m</button>
          <button type="button" class="chip" data-min="60">1h</button>
          <button type="button" class="chip" data-min="90">1.5h</button>
        </div>
        <button type="submit" class="add-btn" id="addButton">+ Add to day</button>
      </form>

      <div class="panel-label">Unscheduled</div>
      <div class="backlog-list" id="backlogList"></div>
      <div class="backlog-empty" id="backlogEmpty" hidden>Everything is boxed. Add a task to plan more.</div>

      <div class="action-bar" id="actionBar">
        <div class="ab-title" id="abTaskName"></div>
        <button type="button" class="ab-btn primary" id="abFocus">▸ Start focus</button>
        <button type="button" class="ab-btn" id="abDone">✓ Mark done</button>
        <button type="button" class="ab-btn danger" id="abDelete">✕ Delete</button>
      </div>
    </aside>

    <section class="timeline-wrap">
      <div class="timeline-scroll" id="timelineScroll">
        <div class="timeline" id="timeline">
          <div class="ticks" id="ticks"></div>
          <div class="blocks-col" id="blocksCol">
            <div class="now-line" id="nowLine"><span id="nowLabel"></span></div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<div class="focus-overlay" id="focusOverlay" hidden>
  <div class="focus-label">Focus box</div>
  <div class="focus-name" id="focusName"></div>
  <div class="focus-clock" id="focusClock">25:00</div>
  <div class="focus-actions">
    <button type="button" class="focus-btn primary" id="focusToggle">Pause</button>
    <button type="button" class="focus-btn done" id="focusComplete">✓ Complete box</button>
    <button type="button" class="focus-btn" id="focusExit">Exit</button>
  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
