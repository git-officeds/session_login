<?php
/**
 * logout.php  ―  ログアウト処理（セッション破棄）
 *
 * リンク（GET）でもフォーム（POST）でも呼べる。
 * CSRF を厳密にしたい場合は、下の判定を有効化して POST のみ許可にする。
 */
require_once __DIR__ . '/auth.php';

// --- CSRF 対策: POST + 有効な CSRF トークンのみ受け付ける ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
    || !csrf_verify($_POST['csrf_token'] ?? null)) {
    auth_redirect(is_logged_in() ? AUTH_HOME_PAGE : AUTH_LOGIN_PAGE);
}

logout_user();   // セッション変数のクリア・Cookie 失効・session_destroy まで実施

auth_redirect(AUTH_LOGIN_PAGE);
