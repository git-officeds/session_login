<?php
/**
 * auth_users.php  ―  管理画面ログインの資格情報
 *
 * ★このファイルは必ず公開ディレクトリの外に置くこと。
 *   配置先のパスは admin/config.php の APP_STAGE_CONFIG[...]['users_file']
 *   と一致させる（XAMPP: C:/xampp/private/auth_users.php、
 *   本番: /home/users/web12/7/6/0282367/private/auth_users.php など）。
 *
 * ユーザー名 => password_hash() で生成したハッシュ、の配列を返す。
 *
 * ハッシュの作り方（コマンドラインで実行）:
 *   php -r "echo password_hash('あなたのパスワード', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * ※ ハッシュ（$2y$ / $argon2 で始まる文字列）以外の値は照合時に必ず失敗する
 *   （平文フォールバックは無し）。
 */
return [
    // 初期値: admin / password（★本番では必ず独自のパスワードに変更する）
    'admin' => '$2y$10$.lG7to.fe6pSkYLJuEH0JOv6vHzD.nevHDT/xwVjqh0o41zjijb3W',
];
