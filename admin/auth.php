<?php
/**
 * auth.php  ―  【汎用パーツ】セッション管理・認証チェック・リダイレクト処理
 *
 * 認証が必要なページの先頭で読み込んで使います。
 *
 *   require_once __DIR__ . '/auth.php';
 *   require_login();            // 未ログインなら login.php へリダイレクト
 *
 * ログイン処理側 (login_proc.php) では:
 *
 *   require_once __DIR__ . '/auth.php';
 *   login_user($username);      // 認証成功時にセッションを確立
 *
 * ---------------------------------------------------------------------------
 * このファイルは「設定」と「関数」だけを持ち、画面出力は行いません。
 * 環境（XAMPP／本番）によって変わる値は config.php にまとめてあるので、
 * デプロイ時に触るのは基本的に config.php だけでよい。
 */

require_once __DIR__ . '/config.php';   // 環境別設定（APP_STAGE で切替）

/* =========================================================================
 *  0. 実行環境・エラー表示  ―  本番では詳細を画面に出さない
 * ========================================================================= */

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    if (AUTH_ERROR_LOG !== '') {
        ini_set('error_log', AUTH_ERROR_LOG);
    }
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// 想定外の例外・エラーで詳細（パスやスタックトレース）を画面に出さない
set_exception_handler(function ($e) {
    error_log('[parts_admin] ' . $e);
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo 'エラーが発生しました。時間をおいて再度お試しください。';
    exit;
});


/* =========================================================================
 *  1. 設定  ―  環境に依存しない項目だけをここに置く
 *              （XAMPP／本番で値が変わる項目は config.php を参照）
 * ========================================================================= */

/**
 * ログインを許可するユーザー。
 *   'ユーザー名' => 'password_hash() で生成したハッシュ（$2y$ / $argon2 で始まる文字列）'
 *
 * AUTH_USERS_FILE（config.php で環境ごとに定義。★公開ディレクトリの外）に
 * 次の内容のファイルを置くと優先的に読み込む:
 *
 *     <?php
 *     return [
 *         'admin' => '$2y$12$............（下記コマンドで生成したハッシュ）',
 *     ];
 *
 *   ハッシュの作り方（コマンドラインで実行）:
 *     php -r "echo password_hash('あなたのパスワード', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * ※ ハッシュ以外（平文）の値は照合時に常に失敗します（平文フォールバックは廃止）。
 * ※ 外部ファイルが無い場合のデフォルトは admin / password（ハッシュ化済み）。
 *    本番では必ず外部ファイルを作成し、独自のパスワードに変更してください。
 */
define('AUTH_USERS', (static function (): array {
    if (is_file(AUTH_USERS_FILE)) {
        $u = require AUTH_USERS_FILE;
        if (is_array($u) && $u !== []) {
            return $u;
        }
    }
    // フォールバック: admin / password をハッシュ化したもの（本番では使用しないこと）
    return [
        'admin' => '$2y$10$.lG7to.fe6pSkYLJuEH0JOv6vHzD.nevHDT/xwVjqh0o41zjijb3W',
    ];
})());

/** ログインフォームのパス（このディレクトリからの相対 URL） */
const AUTH_LOGIN_PAGE  = 'login.php';
/** ログイン後のデフォルト遷移先 */
const AUTH_HOME_PAGE   = 'index.php';
/** ログアウト処理のパス */
const AUTH_LOGOUT_PAGE = 'logout.php';

/** 無操作でログアウトするまでの秒数（0 で無効）。既定 30 分 */
const AUTH_IDLE_TIMEOUT = 1800;

/** セッション Cookie 名（他アプリと分離するため固有名にする） */
const AUTH_SESSION_NAME = 'PARTSADMINSESID';

/* --- ブルートフォース対策（ファイルベースの簡易スロットリング） --- */
/** この回数連続で失敗するとロック */
const AUTH_MAX_FAILS    = 5;
/** ロック時間（秒） */
const AUTH_LOCK_SECONDS = 900;
/** 失敗回数をカウントする時間窓（秒） */
const AUTH_FAIL_WINDOW  = 900;

// AUTH_THROTTLE_DIR（失敗回数を記録するディレクトリ。★公開ディレクトリ外）は
// config.php で環境ごとに定義済み。


/* =========================================================================
 *  2. セッション開始  ―  読み込むだけで安全な設定でセッションを開始
 * ========================================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');   // 未初期化セッションIDを拒否（固定攻撃の追加防御）
    ini_set('session.use_only_cookies', '1');  // URL 埋め込みセッションID禁止
    ini_set('session.cookie_httponly', '1');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');

    // サーバー既定のセッション保存先が書き込み不可な場合に備え、
    // アカウント配下の書き込み可能なディレクトリを明示的に指定する。
    if (AUTH_SESSION_SAVE_PATH !== '') {
        if (!is_dir(AUTH_SESSION_SAVE_PATH)) {
            @mkdir(AUTH_SESSION_SAVE_PATH, 0700, true);
        }
        session_save_path(AUTH_SESSION_SAVE_PATH);
    }

    session_name(AUTH_SESSION_NAME);

    $secure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/* --- 共通セキュリティヘッダー ------------------------------------------- */
auth_send_common_headers();

/* --- アイドルタイムアウト ------------------------------------------------- */
if (AUTH_IDLE_TIMEOUT > 0 && !empty($_SESSION['auth']['last_activity'])) {
    if (time() - $_SESSION['auth']['last_activity'] > AUTH_IDLE_TIMEOUT) {
        auth_clear_session();
    }
}
if (!empty($_SESSION['auth']['user'])) {
    $_SESSION['auth']['last_activity'] = time();
}


/* =========================================================================
 *  3. 認証系関数
 * ========================================================================= */

/**
 * ログイン済みかどうか。
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['auth']['user']);
}

/**
 * ログイン中のユーザー名を返す（未ログインなら null）。
 */
function current_user(): ?string
{
    return $_SESSION['auth']['user'] ?? null;
}

/**
 * ユーザー名・パスワードを照合する。成功なら true。
 * （実際のセッション確立は login_user() で行う）
 */
function verify_credentials(string $username, string $password): bool
{
    $stored = AUTH_USERS[$username] ?? null;

    // ユーザー未登録、またはハッシュが未設定（平文など）の場合は常に失敗。
    // 平文フォールバックは廃止。タイミング差を減らすためダミー検証だけ実施する。
    if ($stored === null || !preg_match('/^\$(2[aby]|argon2)/', $stored)) {
        password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');
        return false;
    }

    return password_verify($password, $stored);
}

/**
 * 認証成功後に呼び出してセッションを確立する。
 * セッション固定攻撃対策として ID を再生成する。
 */
function login_user(string $username): void
{
    session_regenerate_id(true);

    $_SESSION['auth'] = [
        'user'          => $username,
        'login_at'      => time(),
        'last_activity' => time(),
        'ua_hash'       => auth_ua_hash(),
    ];
}

/**
 * ログアウト。セッションを完全に破棄する。
 */
function logout_user(): void
{
    auth_clear_session();

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }

    session_destroy();
}

/**
 * 認証が必要なページの先頭で呼ぶ。
 * 未ログイン、またはセッション不整合ならログイン画面へリダイレクト。
 *
 * @param string|null $redirectBack ログイン後に戻す URL（省略時は現在の URL）
 */
function require_login(?string $redirectBack = null): void
{
    $valid = is_logged_in()
        && hash_equals($_SESSION['auth']['ua_hash'] ?? '', auth_ua_hash());

    if ($valid) {
        return;
    }

    auth_clear_session();

    $back = $redirectBack ?? ($_SERVER['REQUEST_URI'] ?? AUTH_HOME_PAGE);
    $url  = AUTH_LOGIN_PAGE;
    if ($back !== '' && strpos($back, AUTH_LOGIN_PAGE) === false) {
        $url .= '?redirect=' . rawurlencode($back);
    }

    auth_redirect($url);
}

/**
 * 既にログイン済みなら管理画面へ飛ばす（login.php の先頭で使う）。
 */
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        auth_redirect(AUTH_HOME_PAGE);
    }
}


/* =========================================================================
 *  4. CSRF トークン
 * ========================================================================= */

/**
 * CSRF トークンを取得（無ければ生成）。
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * <input type="hidden"> 形式で CSRF トークンを出力。
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * POST された CSRF トークンを検証。
 */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}


/* =========================================================================
 *  5. 内部ヘルパー
 * ========================================================================= */

/** セッションの認証情報だけを消す（Cookie は残す） */
function auth_clear_session(): void
{
    unset($_SESSION['auth']);
}

/** User-Agent のハッシュ（軽度のセッションハイジャック検知用） */
function auth_ua_hash(): string
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/** ヘッダーリダイレクトして必ず終了する */
function auth_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** 全ページ共通のセキュリティヘッダーを送出する */
function auth_send_common_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');                       // クリックジャッキング対策
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    // 認証系画面はブラウザ／プロキシにキャッシュさせない
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}


/* =========================================================================
 *  6. ブルートフォース対策（ファイルベースの簡易スロットリング）
 * ========================================================================= */

/** ユーザー名 + 接続元IP でロック単位を決めるキー */
function auth_throttle_key(string $username): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', strtolower($username) . '|' . $ip);
}

/** 現在の失敗記録を返す（fails / first / file） */
function auth_throttle_state(string $username): array
{
    $file = AUTH_THROTTLE_DIR . '/' . auth_throttle_key($username) . '.json';
    if (!is_file($file)) {
        return ['fails' => 0, 'first' => 0, 'file' => $file];
    }
    $d = json_decode((string)@file_get_contents($file), true) ?: [];
    return [
        'fails' => (int)($d['fails'] ?? 0),
        'first' => (int)($d['first'] ?? 0),
        'file'  => $file,
    ];
}

/** 現在ロック中かどうか */
function auth_is_locked(string $username): bool
{
    $s = auth_throttle_state($username);
    return $s['fails'] >= AUTH_MAX_FAILS
        && (time() - $s['first']) < AUTH_LOCK_SECONDS;
}

/** ログイン失敗を記録する */
function auth_record_fail(string $username): void
{
    if (!is_dir(AUTH_THROTTLE_DIR)) {
        @mkdir(AUTH_THROTTLE_DIR, 0700, true);
    }
    $s = auth_throttle_state($username);
    if ($s['first'] === 0 || (time() - $s['first']) > AUTH_FAIL_WINDOW) {
        $s['first'] = time();
        $s['fails'] = 0;
    }
    $s['fails']++;
    @file_put_contents(
        $s['file'],
        json_encode(['fails' => $s['fails'], 'first' => $s['first']]),
        LOCK_EX
    );
}

/** ログイン成功時に失敗記録をクリアする */
function auth_clear_fails(string $username): void
{
    $s = auth_throttle_state($username);
    if (is_file($s['file'])) {
        @unlink($s['file']);
    }
}

/** テンプレート用の短縮エスケープ関数 */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
