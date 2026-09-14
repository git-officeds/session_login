<?php
/**
 * config.php  ―  環境別パラメーター設定（XAMPP／本番切り替え）
 *
 * デプロイ時に確認・変更するのは基本的にこのファイルだけでよい。
 * auth.php 側のロジックは環境が変わっても触らずに済むようにしてある。
 *
 * ---------------------------------------------------------------------------
 * 使い方
 *   1. APP_STAGE を 'xampp' か 'production' に設定する。
 *   2. 該当する環境の users_file / throttle_dir が
 *      「../private フォルダ（admin フォルダの外側＝公開領域の外）」を
 *      指しているか確認する。
 *   3. 新しい本番サーバーへ移設する場合は、APP_STAGE_CONFIG に
 *      新しいキーを追加して APP_STAGE を切り替えるだけでよい。
 */

/** ★ここを切り替える: 'xampp'（ローカル開発） / 'production'（本番サーバー） */
const APP_STAGE = 'xampp';

/**
 * 環境ごとの設定値。
 *   env          : 'development'（エラー画面表示）/ 'production'（エラー非表示）
 *   users_file   : 資格情報ファイルの絶対パス   ★private フォルダ（公開領域の外）
 *   throttle_dir : ログイン失敗記録の保存先     ★private フォルダ（公開領域の外）
 *   error_log    : PHPエラーログの出力先（空文字ならサーバー既定のログを使う）
 */
const APP_STAGE_CONFIG = [

    // ローカル開発（XAMPP）
    // private フォルダを C:/xampp/private/ に配置する想定
    // （C:/xampp/htdocs/ の外なので Web からは到達できない）
    'xampp' => [
        'env'          => 'development',
        'users_file'   => 'C:/xampp/private/auth_users.php',
        'throttle_dir' => 'C:/xampp/private/parts_admin_throttle',
        'error_log'    => '',
    ],

    // 本番サーバー（www.wakobussan.jp）
    // ドキュメントルート: /home/users/web12/7/6/0282367/www.wakobussan.jp
    // → その1つ上の階層（アカウントのホームディレクトリ直下）に
    //   private フォルダを設置する
    'production' => [
        'env'          => 'production',
        'users_file'   => '/home/users/web12/7/6/0282367/private/auth_users.php',
        'throttle_dir' => '/home/users/web12/7/6/0282367/private/parts_admin_throttle',
        'error_log'    => '/home/users/web12/7/6/0282367/private/logs/parts_admin_error.log',
    ],

];

/* --- 上記設定を実際の定数として展開する（通常は編集不要） --- */
$__stage = APP_STAGE_CONFIG[APP_STAGE] ?? APP_STAGE_CONFIG['xampp'];

define('APP_ENV', $__stage['env']);
define('AUTH_USERS_FILE', $__stage['users_file']);
define('AUTH_THROTTLE_DIR', $__stage['throttle_dir']);
define('AUTH_ERROR_LOG', $__stage['error_log']);

unset($__stage);
