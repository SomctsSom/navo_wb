<?php
declare(strict_types=1);

if (wa_user()) {
    wa_redirect('/dashboard');
}

$error = '';
if (request_is_post()) {
    $login = trim((string) ($_POST['login'] ?? $_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $res = wa_login($login, $password);
    if (wa_api_ok($res)) {
        wa_redirect('/dashboard');
    }
    $error = (string) ($res['message'] ?? 'Login failed');
}

$brandRes = wa_api('GET', '/api/public/branding');
$brand = wa_api_ok($brandRes) ? ($brandRes['data'] ?? []) : [];
$companyName = trim((string) ($brand['company_name'] ?? ''));
$tagline = trim((string) ($brand['tagline'] ?? ''));
$logoUrl = trim((string) ($brand['logo_url'] ?? ''));
if ($companyName === '') {
    $companyName = 'Website Admin';
}

ob_start();
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-brand">
      <?php if ($logoUrl !== ''): ?>
        <img class="auth-logo" src="<?= wa_e($logoUrl) ?>" alt="<?= wa_e($companyName) ?>">
      <?php endif; ?>
      <div class="auth-brand-text">
        <p class="auth-company"><?= wa_e($companyName) ?></p>
        <?php if ($tagline !== ''): ?>
          <p class="auth-tagline"><?= wa_e($tagline) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <h1>Sign in</h1>
    <p class="muted">Access the website administration portal</p>
    <?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid" style="margin-top:1rem" autocomplete="on">
      <label class="full">Name or email
        <input type="text" name="login" required autocomplete="username" value="<?= wa_e($_POST['login'] ?? $_POST['email'] ?? '') ?>" placeholder="Super Admin or you@example.com">
      </label>
      <label class="full">Password
        <div class="password-field">
          <input type="password" name="password" id="login-password" required autocomplete="current-password">
          <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false" title="Show password">Show</button>
        </div>
      </label>
      <div class="actions full">
        <button class="btn" type="submit">Login</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  var input = document.getElementById('login-password');
  var btn = document.getElementById('password-toggle');
  if (!input || !btn) return;
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.title = show ? 'Hide password' : 'Show password';
  });
})();
</script>
<?php
$content = ob_get_clean();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · <?= wa_e($companyName) ?></title>
  <link rel="stylesheet" href="<?= wa_e(wa_url('/assets/css/admin.css')) ?>">
  <?php if ($logoUrl !== ''): ?>
    <link rel="icon" href="<?= wa_e($logoUrl) ?>">
  <?php endif; ?>
</head>
<body><?= $content ?></body>
</html>
<?php
exit;

function request_is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
