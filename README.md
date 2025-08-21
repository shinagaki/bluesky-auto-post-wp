# Bluesky Auto Post

WordPress記事の投稿時に自動的にBlueskyにも投稿するプラグインです。リンクカード表示にも対応。

## 機能

- **WordPress記事投稿時の自動Bluesky投稿**
- **リンクカード表示対応** - タイトル、説明、サムネイル画像付き
- **改行対応の投稿フォーマット** - テキストエリアで自由にフォーマット設定
- **画像アップロード機能** - OG画像を自動取得してBlueskyにアップロード
- **管理画面での簡単設定**
- **接続テスト機能** - 設定の動作確認
- **手動投稿テスト機能** - デバッグ用の手動投稿
- **重複投稿防止機能**
- **App Password対応** - セキュアな認証
- **手動制御機能** - 投稿ごとに個別にBluesky投稿を制御可能 (v1.1.0)

## インストール方法

### 方法1: GitHubからダウンロード（推奨）
1. [リリースページ](../../releases) または「Code → Download ZIP」からダウンロード
2. WordPress管理画面 → プラグイン → 新規追加 → プラグインのアップロード
3. ZIPファイルをアップロードしてインストール
4. プラグインを有効化

### 方法2: 手動アップロード
1. `bluesky-auto-post-wp` フォルダを `wp-content/plugins/` にアップロード
2. WordPress管理画面の「プラグイン」ページでプラグインを有効化

### 設定
「設定」→「Bluesky Auto Post」で設定を行う

## 設定方法

### 1. Bluesky認証情報の設定

1. WordPress管理画面で「設定」→「Bluesky Auto Post」を開く
2. Blueskyのハンドル名（例: `yourname.bsky.social`）を入力
3. BlueskyのパスワードまたはApp Passwordを入力
4. 「接続をテスト」ボタンで認証が正常に動作することを確認

### 2. App Passwordの作成（推奨）

セキュリティのため、メインパスワードではなくApp Passwordの使用を推奨します：

1. BlueskyアプリまたはWebで「Settings」→「Privacy and Security」を開く
2. 「App Passwords」セクションで新しいApp Passwordを作成
3. 作成されたApp Passwordをプラグイン設定に入力

### 3. 投稿フォーマットの設定

投稿内容をカスタマイズできます。テキストエリアで改行を含む自由な形式で設定可能です。

**使用可能なプレースホルダー：**
- `{title}` - 記事のタイトル
- `{url}` - 記事のURL（リンクカード表示対応）
- `{excerpt}` - 記事の抜粋

**フォーマット例：**
```
{title}

{url}
```

デフォルト: タイトルの後に空行を入れてURL表示

### 4. 自動投稿の有効化

「自動投稿を有効にする」チェックボックスをオンにして設定を保存します。

## 使用方法

1. 設定完了後、通常通りWordPress記事を投稿
2. 記事が公開されると自動的にBlueskyに投稿される
3. 投稿済みの記事は重複投稿されない

## トラブルシューティング

### 投稿が自動で送信されない場合

1. プラグイン設定で「自動投稿を有効にする」がオンになっているか確認
2. 「接続をテスト」で認証情報が正しいか確認
3. WordPressのエラーログを確認（`wp-content/debug.log`）

### よくあるエラー

- **認証エラー**: ユーザー名とパスワード（App Password）を確認
- **API制限エラー**: 短時間での大量投稿を避ける
- **文字数制限**: Blueskyの文字数制限（300文字）を確認

## セキュリティについて

- パスワードはWordPressのオプションテーブルに保存されます
- 可能な限りApp Passwordを使用してください
- 本番環境では適切なファイルパーミッションを設定してください

## 開発者向け情報

### フィルターフック

カスタマイズのためのフィルターフックが利用可能です：

```php
// 投稿内容をカスタマイズ
add_filter('bluesky_auto_post_content', function($content, $post_id, $post) {
    // カスタム処理
    return $content;
}, 10, 3);

// 投稿条件をカスタマイズ
add_filter('bluesky_auto_post_should_post', function($should_post, $post_id, $post) {
    // 特定の条件で投稿を制御
    return $should_post;
}, 10, 3);
```

### ログ

プラグインの動作ログはWordPressのエラーログに記録されます。デバッグ時は以下を `wp-config.php` に追加：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## ライセンス

GPL v2 or later

## サポート

問題や機能要望がございましたら、GitHubのIssuesページでお知らせください。