==============================================================================
 session_login ―― ログイン機能パッケージ（PHP・依存ライブラリなし）
==============================================================================

「ログイン機能」だけを抜き出した単体パッケージです。DB 不要・Composer 不要、
素の PHP 8 だけで動きます。

このパッケージは公開範囲の異なる2つのフォルダで構成されています。
デプロイ時は、この2つを別々の場所に配置してください。

  admin/    公開ディレクトリ（Web からアクセスされる場所）に置くもの
  private/  公開ディレクトリの外（Web から直接アクセスできない場所）に置くもの


------------------------------------------------------------------------------
1. フォルダ構成
------------------------------------------------------------------------------

  session_login/
  ├── admin/                      ★公開ディレクトリに配置
  │   ├── config.php              環境別設定（XAMPP／本番の切替はここだけ）
  │   ├── auth.php                認証の中核。セッション・CSRF・
  │   │                           ブルートフォース対策・共通セキュリティヘッダー
  │   ├── login.php               ログインフォーム画面
  │   ├── login_proc.php          認証処理（POST を受けてリダイレクト）
  │   ├── logout.php              ログアウト（POST + CSRF 必須）
  │   ├── index.php               ログイン必須ページのサンプル（動作確認用）
  │   └── .htaccess               ドキュメント類・ドットファイルの直接アクセス禁止
  │
  ├── private/                    ★公開ディレクトリの外に配置
  │   ├── auth_users.php          資格情報（ユーザー名・パスワードハッシュ）
  │   ├── parts_admin_throttle/   ログイン失敗回数の記録先（中身は自動生成）
  │   └── .htaccess               誤配置時の保険（全アクセス拒否）
  │
  └── README.md                   このファイル


------------------------------------------------------------------------------
2. 配置方法（XAMPP・本番共通の考え方）
------------------------------------------------------------------------------

(1) admin/ フォルダの中身を、Web から見えるディレクトリにコピーする。
    例:  https://example.com/admin/ → 公開フォルダ/admin/ に展開

(2) private/ フォルダの中身を、Web から見えない場所にコピーする。
    ドキュメントルートの「外側」（1つ上の階層など）に置くのが基本。

(3) admin/config.php の APP_STAGE を、配置した環境に合わせて設定する。
      const APP_STAGE = 'xampp';       // ローカル開発
      const APP_STAGE = 'production';  // 本番サーバー

    APP_STAGE_CONFIG 内の users_file / throttle_dir が、(2) で private/ を
    実際に置いた絶対パスと一致しているか必ず確認する。

(4) 資格情報ファイル private/auth_users.php の中身を、実際のユーザー名・
    パスワードハッシュに書き換える（3章）。書き換えない場合は初期値の
    admin / password でログインできてしまうので、本番公開前に必ず変更する。

(5) 認証させたいページの先頭に2行を書く。

      <?php
      require_once __DIR__ . '/auth.php';
      require_login();          // 未ログインなら login.php へリダイレクト

    フォーム内で CSRF トークンを出したいとき:

      <?= csrf_field() ?>       // <input type="hidden" name="csrf_token" ...>

    ログイン中のユーザー名:

      <?= e(current_user()) ?>

（6）ユーザー名情報やログアウトボタンを配置

    // ログイン成功したら、ログインしているユーザー情報を取得
    $user = current_user();
    $loginAt = $_SESSION['auth']['login_at'] ?? time();

    // ユーザー名取得
    <?= e($user) ?>
    使用例：<span><?= e($user) ?> でログイン中</span>

    // ログアウトボタン
    <form class="logout-form" method="post" action="logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="logout">ログアウト</button>
    </form>


------------------------------------------------------------------------------
2-A. XAMPP（ローカル開発）での配置例
------------------------------------------------------------------------------

  admin/   → C:/xampp/htdocs/(お好きなフォルダ名)/
  private/ → C:/xampp/private/

  admin/config.php:
      const APP_STAGE = 'xampp';
      'xampp' => [
          'users_file'   => 'C:/xampp/private/auth_users.php',
          'throttle_dir' => 'C:/xampp/private/parts_admin_throttle',
          'session_path' => '',   // 空文字ならPHPの既定値（XAMPPはそのままでOK）
      ],

  C:/xampp/private/ は C:/xampp/htdocs/ の外なので、Web からは到達できない。


------------------------------------------------------------------------------
2-B. 本番サーバー（レンタルサーバー等）での配置例
------------------------------------------------------------------------------

  例: ドキュメントルートが
      /home/users/web12/7/6/0282367/www.wakobussan.jp
  の場合、その1つ上の階層（アカウントのホームディレクトリ直下）に
  private フォルダを設置する。

  admin/   → /home/users/web12/7/6/0282367/www.wakobussan.jp/(お好きなフォルダ名)/
  private/ → /home/users/web12/7/6/0282367/private/

  admin/config.php:
      const APP_STAGE = 'production';
      'production' => [
          'users_file'   => '/home/users/web12/7/6/0282367/private/auth_users.php',
          'throttle_dir' => '/home/users/web12/7/6/0282367/private/parts_admin_throttle',
          'session_path' => '/home/users/web12/7/6/0282367/private/sessions',
      ],

  ★共有サーバーでは PHP から読み書きできるパスが open_basedir で
    制限されている場合がある。ドキュメントルート配下しか許可されていない
    場合は private フォルダをその方式では設置できないため、事前に
    ホスティング側の設定（phpinfo() の open_basedir の値など）を確認する。

  ★private/auth_users.php・parts_admin_throttle/ のパーミッションは
    700（所有者のみ）を推奨。throttle 側は PHP の実行ユーザーから
    書き込み可能であることを確認する。

  ★session_path について:
    共用サーバーはサーバー既定の session.save_path（例: /home/var/php/...）が
    このアカウントから書き込めない、または存在しないことがあり、その場合
    ログイン時に session_regenerate_id() が失敗して「ログインできない」
    「ログイン後に何度もログイン画面へ戻される」といった事故になる。
    session_path をアカウント配下の書き込み可能なディレクトリに指定して
    おくと、auth.php が session_start() 前に自動でフォルダを作成し
    session_save_path() で明示的に切り替えるため、この事故を回避できる。
    （フォルダが自動作成できない場合は、FTP 等で 700 権限で手動作成する）


------------------------------------------------------------------------------
3. 資格情報（ユーザー名・パスワード）の設定
------------------------------------------------------------------------------

private/auth_users.php に、次の内容で作成する（★Web 公開フォルダの外）。

  <?php
  return [
      'admin' => '$2y$12$............（下記コマンドで生成したハッシュ）',
  ];

パスワードハッシュの生成:

  # XAMPP
  C:\xampp\php\php.exe -r "echo password_hash('あなたのパスワード', PASSWORD_DEFAULT), PHP_EOL;"
  # Linux サーバー（SSH が使える場合）
  php -r "echo password_hash('あなたのパスワード', PASSWORD_DEFAULT), PHP_EOL;"

  ※ bcrypt ハッシュは環境間で互換なので、ローカルで生成した文字列を
    そのまま本番の auth_users.php にコピーしても構わない。

※ ハッシュ（$2y$ / $argon2 で始まる文字列）以外の値は照合時に必ず失敗する
   （平文フォールバックは無し）。
※ ファイルが無い場合のみ admin / password（ハッシュ化済み）で動く。
   本番では必ずこのファイルを作成し、独自パスワードに変更すること。


------------------------------------------------------------------------------
4. 環境別パラメーター（admin/config.php）
------------------------------------------------------------------------------

  const APP_STAGE = 'xampp' | 'production';   // ★切替はここだけ

  APP_STAGE_CONFIG[APP_STAGE] の内容:
    env          'development' | 'production'（エラー表示の切替）
    users_file   資格情報ファイルの絶対パス（★公開ディレクトリ外）
    throttle_dir ログイン失敗記録の保存先（★公開ディレクトリ外）
    error_log    PHPエラーログの出力先（空文字ならサーバー既定を使用）
    session_path セッション保存先（★公開ディレクトリ外。空文字ならPHPの既定値）

  新しい本番サーバーを追加する場合は、APP_STAGE_CONFIG にキーを
  追加して APP_STAGE を切り替えるだけでよい。


------------------------------------------------------------------------------
5. 本番前に見直す設定（admin/auth.php・config.php）
------------------------------------------------------------------------------

  config.php:
    APP_STAGE                                       // 'production' に変更
    APP_STAGE_CONFIG['production']['users_file']    // 公開フォルダ外の絶対パス
    APP_STAGE_CONFIG['production']['throttle_dir']  // 公開フォルダ外の絶対パス
    APP_STAGE_CONFIG['production']['session_path']  // 公開フォルダ外の絶対パス

  auth.php:
    const AUTH_LOGIN_PAGE   = 'login.php';        // フォームのパス（相対 URL）
    const AUTH_HOME_PAGE    = 'index.php';        // ログイン後の遷移先
    const AUTH_LOGOUT_PAGE  = 'logout.php';
    const AUTH_IDLE_TIMEOUT = 1800;               // 無操作ログアウト秒数（0 で無効）
    const AUTH_SESSION_NAME = 'PARTSADMINSESID';  // 他アプリと分けるため固有名に
    const AUTH_MAX_FAILS    = 5;                  // 連続失敗でロック
    const AUTH_LOCK_SECONDS = 900;                // ロック時間
    const AUTH_FAIL_WINDOW  = 900;                // 失敗をカウントする時間窓

  別プロジェクトで使うときは AUTH_SESSION_NAME を、そのアプリ固有の
  名前に変更する（throttle_dir・users_file の分離は config.php 側で対応済み）。


------------------------------------------------------------------------------
6. 組み込みのセキュリティ対策
------------------------------------------------------------------------------

  ・パスワードは password_hash / password_verify（ハッシュ照合のみ）
  ・セッション: use_strict_mode / use_only_cookies / httponly / SameSite=Lax
    ・HTTPS 検出時は Cookie secure 属性を自動付与
  ・ログイン成功時に session_regenerate_id（セッション固定攻撃対策）
  ・User-Agent ハッシュ照合（軽度のセッションハイジャック検知）
  ・無操作アイドルタイムアウト
  ・CSRF トークン（login / logout で検証）
  ・ブルートフォース対策（ユーザー名 + IP 単位のファイルベース・スロットリング）
  ・ログイン失敗時の一定ウェイト＋タイミング差を抑えるダミー検証
  ・オープンリダイレクト対策（redirect は同一サイトの相対パスのみ許可）
  ・共通セキュリティヘッダー（X-Frame-Options / CSP frame-ancestors /
    X-Content-Type-Options / Referrer-Policy / COOP / no-store / HSTS）
  ・資格情報・失敗記録を公開ディレクトリ外（private/）に分離
  ・共用サーバーのセッション保存先問題への対策（session_path で明示指定。
    PHP 8.4 の sid_length 等 Deprecated 警告も抑止済み）

前提: 本番は必ずサイト全体を HTTPS 化すること（Cookie secure が有効になる）。


------------------------------------------------------------------------------
7. 提供される関数（admin/auth.php）
------------------------------------------------------------------------------

  require_login(?string $redirectBack = null)  未ログインなら login へ飛ばす
  redirect_if_logged_in()                      ログイン済みなら home へ飛ばす
  is_logged_in(): bool
  current_user(): ?string
  verify_credentials(string $user, string $pass): bool
  login_user(string $user): void               認証成功後にセッション確立
  logout_user(): void
  csrf_token(): string  /  csrf_field(): string  /  csrf_verify(?string): bool
  e(?string): string                           htmlspecialchars の短縮

==============================================================================
