<?php
/**
 * index.php  ―  ログイン必須ページのサンプル
 *
 * 認証が必要なページの作り方は、この2行を先頭に書くだけ:
 *
 *   require_once __DIR__ . '/auth.php';
 *   require_login();
 *
 * このファイルは admin_login パッケージの動作確認用サンプルです。
 * 実際のアプリでは、この index.php を自分の管理画面トップに置き換えて
 * ください（先頭2行はそのまま）。
 */
require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$loginAt = $_SESSION['auth']['login_at'] ?? time();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>管理画面トップ</title>
<style>
  :root {
    --bg: #f2f4f8; --card: #fff; --fg: #1f2933; --muted: #6b7280;
    --border: #d7dbe0; --primary: #2563eb;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--fg);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                 "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
  }
  header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; background: var(--card); border-bottom: 1px solid var(--border);
  }
  header .title { font-weight: 700; }
  header .right { display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--muted); }
  header .logout {
    color: var(--primary); text-decoration: none; font-weight: 600;
    background: none; border: none; padding: 0; font: inherit; cursor: pointer;
  }
  header .logout-form { margin: 0; display: inline; }
  main { max-width: 880px; margin: 32px auto; padding: 0 20px; }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0f1420; --card: #1a2130; --fg: #e5e9f0; --muted: #9aa5b1; --border: #2c3545;
    }
  }
</style>
</head>
<body>
  <header>
    <div class="title">管理画面</div>
    <div class="right">
      <span><?= e($user) ?> でログイン中</span>
      <form class="logout-form" method="post" action="logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="logout">ログアウト</button>
      </form>
    </div>
  </header>

  <main>
    <p>ようこそ、<strong><?= e($user) ?></strong> さん。
       （<?= e(date('Y-m-d H:i', $loginAt)) ?> ログイン）</p>
    <p>このページはログイン済みのユーザーだけが表示できます。</p>
  </main>
</body>
</html>
