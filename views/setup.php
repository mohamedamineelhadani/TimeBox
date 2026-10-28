<div class="app auth-shell">
  <section class="auth-card">
    <div class="wordmark"><span class="box-glyph"></span>TimeBox</div>
    <h1 class="auth-title">Create the admin account</h1>
    <p class="auth-hint">There’s no admin yet. This account will be the only one that can use TimeBox.</p>

    <?php if (!empty($error)): ?>
      <div class="form-error" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= $e($url('/setup')) ?>">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

      <label class="field">
        <span>Username</span>
        <input type="text" name="username" value="<?= $e($username ?? '') ?>"
               required minlength="3" maxlength="50" autocomplete="username" autofocus>
      </label>

      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>

      <label class="field">
        <span>Confirm password</span>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
      </label>

      <button type="submit" class="add-btn">Create admin</button>
    </form>
  </section>
</div>
