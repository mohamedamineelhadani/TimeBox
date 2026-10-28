<div class="app auth-shell">
  <section class="auth-card">
    <div class="wordmark"><span class="box-glyph"></span>TimeBox</div>
    <h1 class="auth-title">Log in</h1>
    <p class="auth-hint">plan the day in boxes, not lists</p>

    <?php if (!empty($notice)): ?>
      <div class="form-notice" role="status"><?= $e($notice) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="form-error" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= $e($url('/login')) ?>">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

      <label class="field">
        <span>Username</span>
        <input type="text" name="username" value="<?= $e($username ?? '') ?>"
               required maxlength="50" autocomplete="username" autofocus>
      </label>

      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password">
      </label>

      <button type="submit" class="add-btn">Log in</button>
    </form>
  </section>
</div>
