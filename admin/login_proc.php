<?php
/**
 * login_proc.php  ―  ログイン認証・パスワード照合処理
 *
 * login.php のフォームから POST される。
 * 認証結果に応じて以下へリダイレクトする:
 *   成功 → redirect パラメータ（安全な場合のみ）または index.php
 *   失敗 → login.php （エラーメッセージをフラッシュ表示）
 */
require_once __DIR__ . '/auth.php';

/* --- POST 以外は弾く ---------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    auth_redirect(AUTH_LOGIN_PAGE);
}

/* --- 入力取得 --------------------------------------------------------- */
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$redirect = (string)($_POST['redirect'] ?? '');

/* --- CSRF 検証 ------------------------------------------------------- */
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'セッションの有効期限が切れました。もう一度お試しください。';
    auth_redirect(AUTH_LOGIN_PAGE);
}

/* --- ブルートフォース: ロック中なら即座に弾く ---------------------- */
if ($username !== '' && auth_is_locked($username)) {
    usleep(300000);

    $_SESSION['flash_error']    = '試行回数が多すぎます。しばらくしてから再度お試しください。';
    $_SESSION['flash_old_user'] = $username;

    $back = AUTH_LOGIN_PAGE;
    if ($redirect !== '') {
        $back .= '?redirect=' . rawurlencode($redirect);
    }
    auth_redirect($back);
}

/* --- 認証 ----------------------------------------------------------- */
if ($username === '' || $password === '' || !verify_credentials($username, $password)) {
    if ($username !== '') {
        auth_record_fail($username);
    }

    // ブルートフォース対策の簡易ウェイト
    usleep(300000);

    $_SESSION['flash_error']    = 'ユーザー名またはパスワードが正しくありません。';
    $_SESSION['flash_old_user'] = $username;

    $back = AUTH_LOGIN_PAGE;
    if ($redirect !== '') {
        $back .= '?redirect=' . rawurlencode($redirect);
    }
    auth_redirect($back);
}

/* --- 認証成功 ------------------------------------------------------- */
auth_clear_fails($username);   // 失敗記録をリセット
login_user($username);

// 使い終わった CSRF トークンを更新
unset($_SESSION['csrf_token']);

/* --- 遷移先の決定（オープンリダイレクト対策） ---------------------- */
$dest = AUTH_HOME_PAGE;
if (is_safe_redirect($redirect)) {
    $dest = $redirect;
}
auth_redirect($dest);


/**
 * 同一サイト内の相対パスだけを許可する。
 * "//evil.com" や "https://evil.com" のような外部 URL を拒否。
 */
function is_safe_redirect(string $url): bool
{
    if ($url === '' || $url[0] !== '/') {
        return false;
    }
    // "//host" と "/\host"（バックスラッシュ）は外部URL扱いで拒否
    if (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\')) {
        return false;
    }
    // 制御文字・改行の混入を拒否（ヘッダーインジェクション対策）
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return false;
    }
    // scheme / host 付き（絶対URL）を拒否。パスのみ許可
    if ($url !== '' && (parse_url($url, PHP_URL_HOST) !== null || parse_url($url, PHP_URL_SCHEME) !== null)) {
        return false;
    }
    // login/logout へのループを避ける
    if (strpos($url, AUTH_LOGIN_PAGE) !== false || strpos($url, AUTH_LOGOUT_PAGE) !== false) {
        return false;
    }
    return true;
}
