<?php
/**
 * login.php  ―  ログインフォーム画面
 *
 * 認証処理そのものは login_proc.php が行う。
 * このファイルは「画面表示」と「エラーメッセージの表示」だけを担当する。
 */
require_once __DIR__ . '/auth.php';

// すでにログイン済みなら管理画面へ
redirect_if_logged_in();

// login_proc.php から渡されるフラッシュメッセージ
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// 直前に入力したユーザー名（パスワードは復元しない）
$oldUser = $_SESSION['flash_old_user'] ?? '';
unset($_SESSION['flash_old_user']);

// ログイン後の戻り先
$redirect = $_GET['redirect'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ログイン | 管理画面</title>
<style>
  :root {
    --bg: #f2f4f8;
    --card: #ffffff;
    --fg: #1f2933;
    --muted: #6b7280;
    --border: #d7dbe0;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --danger-bg: #fdecea;
    --danger-fg: #b3261e;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg);
    color: var(--fg);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Kaku Gothic ProN",
                 "Yu Gothic", Meiryo, sans-serif;
  }
  .login-card {
    width: 100%;
    max-width: 360px;
    margin: 24px;
    padding: 32px 28px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
  }
  .login-card h1 {
    margin: 0 0 4px;
    font-size: 20px;
    text-align: center;
  }
  .login-card .subtitle {
    margin: 0 0 24px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
  }
  .field { margin-bottom: 16px; }
  .field label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
  }
  .field input {
    width: 100%;
    padding: 10px 12px;
    font-size: 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
  }
  .field input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
  }
  .btn {
    width: 100%;
    padding: 11px 12px;
    font-size: 15px;
    font-weight: 600;
    color: #fff;
    background: var(--primary);
    border: none;
    border-radius: 8px;
    cursor: pointer;
  }
  .btn:hover { background: var(--primary-hover); }
  .alert {
    margin-bottom: 20px;
    padding: 10px 12px;
    font-size: 13px;
    border-radius: 8px;
    background: var(--danger-bg);
    color: var(--danger-fg);
  }
  .checkbox-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
    font-size: 13px;
    color: var(--muted);
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0f1420;
      --card: #1a2130;
      --fg: #e5e9f0;
      --muted: #9aa5b1;
      --border: #2c3545;
      --danger-bg: #3a1f1d;
      --danger-fg: #f2b8b5;
    }
    .field input { background: #131925; color: var(--fg); }
  }
</style>
</head>
<body>
  <form class="login-card" method="post" action="login_proc.php" autocomplete="on">
    <h1>管理画面</h1>
    <p class="subtitle">ログインしてください</p>

    <?php if ($error !== ''): ?>
      <div class="alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

    <div class="field">
      <label for="username">ユーザー名</label>
      <input type="text" id="username" name="username"
             value="<?= e($oldUser) ?>"
             autocomplete="username" required autofocus>
    </div>

    <div class="field">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password"
             autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn">ログイン</button>
  </form>
</body>
</html>
