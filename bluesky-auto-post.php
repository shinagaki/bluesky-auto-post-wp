<?php
/**
 * Plugin Name: BlueSky Auto Post
 * Plugin URI: https://github.com/shinagaki/bluesky-auto-post-wp
 * Description: WordPressの記事投稿時に自動的にBlueSkyにも投稿するプラグイン。リンクカード表示にも対応
 * Version: 1.1.1
 * Author: Shintaro Inagaki
 * Author URI: https://creco.net/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bluesky-auto-post
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package BlueSky_Auto_Post
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// プラグインの定数を定義
define( 'BLUESKY_AUTO_POST_VERSION', '1.1.1' );
define( 'BLUESKY_AUTO_POST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUESKY_AUTO_POST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * BlueSky Auto Post プラグインのメインクラス
 *
 * WordPress記事の投稿時に自動的にBlueSkyにも投稿する機能を提供
 * リンクカード表示、画像のアップロード、設定画面なども含む
 *
 * @since 1.0.0
 */
class BlueSkyAutoPost {

	/**
	 * BlueSky API エンドポイント
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://bsky.social/xrpc/';

	/**
	 * コンストラクタ
	 *
	 * WordPressのフックを設定し、プラグインを初期化
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// 投稿画面にMeta Boxを追加
		add_action( 'add_meta_boxes', array( $this, 'add_post_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post_meta_data' ) );

		// 複数のフックで投稿を捕捉
		add_action( 'publish_post', array( $this, 'auto_post_to_bluesky' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );

		// プラグイン有効化・無効化フック
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * プラグイン初期化処理
	 *
	 * テキストドメインの読み込みを行います
	 *
	 * @since 1.0.0
	 */
	public function init() {
		load_plugin_textdomain( 'bluesky-auto-post', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * プラグイン有効化時の処理
	 *
	 * デフォルトオプションを設定します
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// デフォルトオプションを設定
		add_option( 'bluesky_auto_post_enabled', false );
		add_option( 'bluesky_username', '' );
		add_option( 'bluesky_password', '' );
		add_option( 'bluesky_post_format', '"{title}"\n{url}' );
	}

	/**
	 * プラグイン無効化時の処理
	 *
	 * 必要に応じてクリーンアップ処理を行います
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// 必要に応じてクリーンアップ処理
	}

	/**
	 * 管理画面メニューを追加
	 *
	 * 設定ページを管理画面に追加します
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_options_page(
			'Bluesky Auto Post Settings',
			'Bluesky Auto Post',
			'manage_options',
			'bluesky-auto-post',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * 管理画面初期化処理
	 *
	 * 設定フィールドとセクションを登録します
	 *
	 * @since 1.0.0
	 */
	public function admin_init() {
		register_setting( 'bluesky_auto_post_settings', 'bluesky_auto_post_enabled' );
		register_setting( 'bluesky_auto_post_settings', 'bluesky_username' );
		register_setting( 'bluesky_auto_post_settings', 'bluesky_password' );
		register_setting( 'bluesky_auto_post_settings', 'bluesky_post_format' );

		add_settings_section(
			'bluesky_auto_post_main',
			'Bluesky接続設定',
			array( $this, 'settings_section_callback' ),
			'bluesky-auto-post'
		);

		add_settings_field(
			'bluesky_auto_post_enabled',
			'自動投稿を有効にする',
			array( $this, 'enabled_field_callback' ),
			'bluesky-auto-post',
			'bluesky_auto_post_main'
		);

		add_settings_field(
			'bluesky_username',
			'Blueskyユーザー名',
			array( $this, 'username_field_callback' ),
			'bluesky-auto-post',
			'bluesky_auto_post_main'
		);

		add_settings_field(
			'bluesky_password',
			'Blueskyパスワード',
			array( $this, 'password_field_callback' ),
			'bluesky-auto-post',
			'bluesky_auto_post_main'
		);

		add_settings_field(
			'bluesky_post_format',
			'投稿フォーマット',
			array( $this, 'format_field_callback' ),
			'bluesky-auto-post',
			'bluesky_auto_post_main'
		);
	}

	/**
	 * 設定セクションのコールバック
	 *
	 * 設定ページのセクションに表示する説明文を出力
	 *
	 * @since 1.0.0
	 */
	public function settings_section_callback() {
		echo '<p>Blueskyアカウントの接続設定を行ってください。</p>';
	}

	/**
	 * 自動投稿有効化フィールドのコールバック
	 *
	 * 自動投稿を有効化するチェックボックスを表示
	 *
	 * @since 1.0.0
	 */
	public function enabled_field_callback() {
		$enabled = get_option( 'bluesky_auto_post_enabled', false );
		echo '<input type="checkbox" name="bluesky_auto_post_enabled" value="1" ' . checked( 1, $enabled, false ) . ' />';
	}

	/**
	 * ユーザー名フィールドのコールバック
	 *
	 * BlueSkyユーザー名入力フィールドを表示
	 *
	 * @since 1.0.0
	 */
	public function username_field_callback() {
		$username = get_option( 'bluesky_username', '' );
		echo '<input type="text" name="bluesky_username" value="' . esc_attr( $username ) . '" class="regular-text" placeholder="example.bsky.social" />';
		echo '<p class="description">Blueskyのハンドル名を入力してください（例: example.bsky.social）</p>';
	}

	/**
	 * パスワードフィールドのコールバック
	 *
	 * BlueSkyパスワード入力フィールドを表示
	 *
	 * @since 1.0.0
	 */
	public function password_field_callback() {
		$password = get_option( 'bluesky_password', '' );
		echo '<input type="password" name="bluesky_password" value="' . esc_attr( $password ) . '" class="regular-text" />';
		echo '<p class="description">BlueskyのパスワードまたはApp Passwordを入力してください</p>';
	}

	/**
	 * 投稿フォーマットフィールドのコールバック
	 *
	 * 投稿フォーマットの設定フィールドを表示
	 *
	 * @since 1.0.0
	 */
	public function format_field_callback() {
		$format = get_option( 'bluesky_post_format', '"{title}"\n{url}' );
		echo '<textarea name="bluesky_post_format" rows="3" class="regular-text">' . esc_textarea( $format ) . '</textarea>';
		echo '<p class="description">投稿フォーマットを指定してください。{title} = 記事タイトル、{url} = 記事URL、{excerpt} = 抜粋<br>';
		echo '改行を含める場合は実際に改行を入力してください。URLを含めると自動的にリンクカードが表示されます。</p>';
		echo '<p class="description"><strong>例:</strong><br>';
		echo '- <code>"{title}"\n{url}</code> → タイトルをクォートで囲んでURL<br>';
		echo '- <code>{title}\n\n{url}</code> → タイトルの後に空行を入れてURL<br>';
		echo '- <code>{title}\n{excerpt}\n\n{url}</code> → タイトル、抜粋、空行、URL</p>';
	}

	/**
	 * 管理画面の表示
	 *
	 * 設定ページのHTMLを出力します
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1>Bluesky Auto Post Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bluesky_auto_post_settings' );
				do_settings_sections( 'bluesky-auto-post' );
				submit_button();
				?>
			</form>
			
			<h2>接続テスト</h2>
			<p>
				<button type="button" id="test-connection" class="button">接続をテスト</button>
				<span id="test-result"></span>
			</p>
			
			<h2>デバッグ情報</h2>
			<table class="form-table">
				<tr>
					<th>自動投稿設定</th>
					<td><?php echo get_option( 'bluesky_auto_post_enabled' ) ? '有効' : '無効'; ?></td>
				</tr>
				<tr>
					<th>ユーザー名</th>
					<td><?php echo esc_html( get_option( 'bluesky_username' ) ? '設定済み' : '未設定' ); ?></td>
				</tr>
				<tr>
					<th>パスワード</th>
					<td><?php echo esc_html( get_option( 'bluesky_password' ) ? '設定済み' : '未設定' ); ?></td>
				</tr>
				<tr>
					<th>最近の投稿</th>
					<td>
						<?php
						$recent_posts = get_posts(
							array(
								'numberposts' => 3,
								'post_status' => 'publish',
								'meta_query'  => array(
									array(
										'key'     => '_bluesky_posted',
										'compare' => 'EXISTS',
									),
								),
							)
						);

						if ( empty( $recent_posts ) ) {
							echo 'Blueskyに投稿された記事はありません';
						} else {
							foreach ( $recent_posts as $recent_post ) {
								$posted = get_post_meta( $recent_post->ID, '_bluesky_posted', true ) ? '投稿済み' : '未投稿';
								echo '<div>' . esc_html( $recent_post->post_title ) . ' - ' . esc_html( $posted ) . '</div>';
							}
						}
						?>
					</td>
				</tr>
			</table>
			
			<h3>手動投稿テスト</h3>
			<p>最新の記事でBluesky投稿をテストします：</p>
			<p>
				<button type="button" id="test-manual-post" class="button">最新記事を手動投稿</button>
				<span id="manual-post-result"></span>
			</p>
			
			<script>
			document.getElementById('test-connection').addEventListener('click', function() {
				var button = this;
				var result = document.getElementById('test-result');
				
				button.disabled = true;
				result.innerHTML = 'テスト中...';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'action=test_bluesky_connection&_wpnonce=' + '<?php echo esc_attr( wp_create_nonce( 'test_bluesky_connection' ) ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						result.innerHTML = '<span style="color: green;">✓ 接続成功</span>';
					} else {
						result.innerHTML = '<span style="color: red;">✗ 接続失敗: ' + data.data + '</span>';
					}
					button.disabled = false;
				})
				.catch(error => {
					result.innerHTML = '<span style="color: red;">✗ エラーが発生しました</span>';
					button.disabled = false;
				});
			});
			
			document.getElementById('test-manual-post').addEventListener('click', function() {
				var button = this;
				var result = document.getElementById('manual-post-result');
				
				button.disabled = true;
				result.innerHTML = 'テスト中...';
				
				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'action=test_manual_bluesky_post&_wpnonce=' + '<?php echo esc_attr( wp_create_nonce( 'test_manual_bluesky_post' ) ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						result.innerHTML = '<span style="color: green;">✓ 手動投稿成功</span>';
					} else {
						result.innerHTML = '<span style="color: red;">✗ 手動投稿失敗: ' + data.data + '</span>';
					}
					button.disabled = false;
				})
				.catch(error => {
					result.innerHTML = '<span style="color: red;">✗ エラーが発生しました</span>';
					button.disabled = false;
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * 投稿ステータス変更時の処理
	 *
	 * 投稿が公開状態に変更されたときに自動投稿を実行
	 *
	 * @since 1.0.0
	 * @param string  $new_status 新しい投稿ステータス
	 * @param string  $old_status 古い投稿ステータス
	 * @param WP_Post $post       投稿オブジェクト
	 */
	public function on_post_status_change( $new_status, $old_status, $post ) {
		// 公開状態に変更された場合のみ処理
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// postタイプのみ処理
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$this->auto_post_to_bluesky( $post->ID, $post );
	}

	/**
	 * BlueSkyへの自動投稿処理
	 *
	 * WordPress投稿時に呼び出され、条件をチェックしてBlueSkyに投稿
	 *
	 * @since 1.0.0
	 * @param int     $post_id 投稿ID
	 * @param WP_Post $post    投稿オブジェクト
	 */
	public function auto_post_to_bluesky( $post_id, $post ) {

		// 自動投稿が無効の場合は何もしない
		if ( ! get_option( 'bluesky_auto_post_enabled', false ) ) {
			return;
		}

		// リビジョンや自動保存の場合はスキップ
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// postオブジェクトが渡されていない場合は取得
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		// postタイプチェック
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// 投稿ステータスチェック
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// 既に投稿済みかチェック
		if ( get_post_meta( $post_id, '_bluesky_posted', true ) ) {
			return;
		}

		// 手動制御チェック：チェックボックスがオフの場合はスキップ
		$manual_control = get_post_meta( $post_id, '_bluesky_manual_post', true );
		if ( '0' === $manual_control ) {
			return;
		}

		$this->post_to_bluesky( $post_id, $post );
	}

	/**
	 * BlueSkyへの実際の投稿処理
	 *
	 * セッション作成、コンテンツ作成、リンクカード取得、投稿を実行
	 *
	 * @since 1.0.0
	 * @param int     $post_id 投稿ID
	 * @param WP_Post $post    投稿オブジェクト
	 * @return bool           投稿成功時はtrue、失敗時はfalse
	 */
	private function post_to_bluesky( $post_id, $post ) {
		$username = get_option( 'bluesky_username', '' );
		$password = get_option( 'bluesky_password', '' );

		if ( empty( $username ) || empty( $password ) ) {
			error_log( 'BlueSky Auto Post: Username or password not configured' );
			return false;
		}

		// セッションを作成
		$session = $this->create_session( $username, $password );
		if ( ! $session ) {
			error_log( 'BlueSky Auto Post: Failed to create session' );
			return false;
		}

		// 投稿内容を作成
		$format  = get_option( 'bluesky_post_format', '"{title}"\n{url}' );
		$title   = $post->post_title;
		$url     = get_permalink( $post_id );
		$excerpt = get_the_excerpt( $post );

		$content = str_replace(
			array( '{title}', '{url}', '{excerpt}' ),
			array( $title, $url, $excerpt ),
			$format
		);

		// 改行文字を実際の改行に変換
		$content = str_replace( '\\n', "\n", $content );

		// リンクカード情報を取得
		$link_card = $this->get_link_card_data( $url, $session );

		// BlueSkyに投稿
		$result = $this->create_post( $session, $content, $url, $link_card );

		if ( $result ) {
			// 投稿済みフラグを設定
			update_post_meta( $post_id, '_bluesky_posted', true );
			update_post_meta( $post_id, '_bluesky_post_uri', $result['uri'] );
			return true;
		}

		return false;
	}

	/**
	 * BlueSkyセッションの作成
	 *
	 * ユーザー名とパスワードでBlueSky APIに認証し、セッションを取得
	 *
	 * @since 1.0.0
	 * @param string $username BlueSkyユーザー名
	 * @param string $password BlueSkyパスワード
	 * @return array|false    セッションデータまたはfalse
	 */
	private function create_session( $username, $password ) {
		$response = wp_remote_post(
			$this->api_endpoint . 'com.atproto.server.createSession',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'identifier' => $username,
						'password'   => $password,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'BlueSky Auto Post: HTTP Error - ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			error_log( 'BlueSky Auto Post: API Error - ' . $body );
			return false;
		}

		return $data;
	}

	/**
	 * BlueSky投稿の作成
	 *
	 * コンテンツ、URL、リンクカードを使ってBlueSkyに投稿を作成
	 *
	 * @since 1.0.0
	 * @param array       $session   BlueSkyセッションデータ
	 * @param string      $content   投稿コンテンツ
	 * @param string|null $url       リンクURL（オプション）
	 * @param array|null  $link_card リンクカードデータ（オプション）
	 * @return array|false           投稿結果またはfalse
	 */
	private function create_post( $session, $content, $url = null, $link_card = null ) {
		$record = array(
			'text'      => $content,
			'createdAt' => gmdate( 'c' ),
			'$type'     => 'app.bsky.feed.post',
		);

		// URLが含まれている場合はfacetsを追加してリンクを有効化
		if ( $url && strpos( $content, $url ) !== false ) {
			$url_start = strpos( $content, $url );
			$url_end   = $url_start + strlen( $url );

			$record['facets'] = array(
				array(
					'index'    => array(
						'byteStart' => $url_start,
						'byteEnd'   => $url_end,
					),
					'features' => array(
						array(
							'$type' => 'app.bsky.richtext.facet#link',
							'uri'   => $url,
						),
					),
				),
			);

			// リンクカード情報があれば埋め込みとして追加
			if ( $link_card ) {
				$record['embed'] = array(
					'$type'    => 'app.bsky.embed.external',
					'external' => $link_card,
				);
			}
		}

		$post_data = array(
			'repo'       => $session['did'],
			'collection' => 'app.bsky.feed.post',
			'record'     => $record,
		);

		$response = wp_remote_post(
			$this->api_endpoint . 'com.atproto.repo.createRecord',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $session['accessJwt'],
				),
				'body'    => wp_json_encode( $post_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'BlueSky Auto Post: HTTP Error - ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			error_log( 'BlueSky Auto Post: API Error - ' . $body );
			return false;
		}

		return $data;
	}

	/**
	 * リンクカードデータの取得
	 *
	 * URLからOGタグやTwitterカードのメタデータを抽出し、リンクカード用のデータを作成
	 *
	 * @since 1.0.0
	 * @param string     $url     メタデータを取得するURL
	 * @param array|null $session BlueSkyセッション（画像アップロード用）
	 * @return array|null        リンクカードデータまたはnull
	 */
	private function get_link_card_data( $url, $session = null ) {
		// URLからメタデータを取得してリンクカード情報を作成
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'BlueSky Auto Post Plugin',
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return null;
		}

		// HTMLからメタデータを抽出
		$og_title      = $this->extract_meta_tag( $html, 'og:title' );
		$twitter_title = $this->extract_meta_tag( $html, 'twitter:title' );
		$title         = $og_title ? $og_title : ( $twitter_title ? $twitter_title : $this->extract_title_tag( $html ) );

		$og_desc      = $this->extract_meta_tag( $html, 'og:description' );
		$twitter_desc = $this->extract_meta_tag( $html, 'twitter:description' );
		$meta_desc    = $this->extract_meta_tag( $html, 'description' );
		$description  = $og_desc ? $og_desc : ( $twitter_desc ? $twitter_desc : $meta_desc );

		$og_image      = $this->extract_meta_tag( $html, 'og:image' );
		$twitter_image = $this->extract_meta_tag( $html, 'twitter:image' );
		$thumb         = $og_image ? $og_image : $twitter_image;

		if ( empty( $title ) ) {
			return null;
		}

		$card_data = array(
			'uri'         => $url,
			'title'       => substr( $title, 0, 300 ), // BlueSkyの制限
			'description' => substr( $description ? $description : '', 0, 1000 ),
		);

		// サムネイル画像があれば追加
		if ( $thumb && filter_var( $thumb, FILTER_VALIDATE_URL ) ) {
			// 相対URLを絶対URLに変換
			if ( strpos( $thumb, 'http' ) !== 0 ) {
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				if ( strpos( $thumb, '/' ) === 0 ) {
					$thumb = $base_url . $thumb;
				} else {
					$thumb = rtrim( $base_url, '/' ) . '/' . ltrim( $thumb, '/' );
				}
			}

			// サムネイル画像をBlueSkyにアップロード
			$blob = $this->upload_image_to_bluesky( $thumb, $session );
			if ( $blob ) {
				$card_data['thumb'] = $blob;
			}
		}

		return $card_data;
	}

	/**
	 * HTMLからメタタグの抽出
	 *
	 * OGタグやname属性のメタタグを抽出します
	 *
	 * @since 1.0.0
	 * @param string $html     HTMLコンテンツ
	 * @param string $property メタタグのプロパティ名
	 * @return string|null     メタタグの値またはnull
	 */
	private function extract_meta_tag( $html, $property ) {
		// og:タグを抽出
		if ( strpos( $property, 'og:' ) === 0 ) {
			$pattern = '/<meta\s+property=["\']' . preg_quote( $property, '/' ) . '["\']\s+content=["\']([^"\']*)["\'][^>]*>/i';
		} else {
			// name属性のメタタグを抽出
			$pattern = '/<meta\s+name=["\']' . preg_quote( $property, '/' ) . '["\']\s+content=["\']([^"\']*)["\'][^>]*>/i';
		}

		if ( preg_match( $pattern, $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
		}

		return null;
	}

	/**
	 * HTMLからtitleタグの抽出
	 *
	 * HTMLのtitleタグの内容を抽出します
	 *
	 * @since 1.0.0
	 * @param string $html HTMLコンテンツ
	 * @return string|null タイトルまたはnull
	 */
	private function extract_title_tag( $html ) {
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
		}
		return null;
	}

	/**
	 * BlueSkyへの画像アップロード
	 *
	 * 指定されたURLの画像をダウンロードし、BlueSkyにアップロード
	 *
	 * @since 1.0.0
	 * @param string     $image_url アップロードする画像URL
	 * @param array|null $session   BlueSkyセッションデータ
	 * @return array|null          アップロードされた画像blobまたはnull
	 */
	private function upload_image_to_bluesky( $image_url, $session = null ) {
		if ( ! $session ) {
			return null;
		}

		// 画像をダウンロード
		$response = wp_remote_get(
			$image_url,
			array(
				'timeout'    => 30,
				'user-agent' => 'BlueSky Auto Post Plugin',
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'BlueSky Auto Post: Failed to download image: ' . $response->get_error_message() );
			return null;
		}

		$image_data   = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( empty( $image_data ) ) {
			error_log( 'BlueSky Auto Post: Empty image data' );
			return null;
		}

		// 画像サイズをチェック（BlueSkyの制限: 1MB）
		if ( strlen( $image_data ) > 1000000 ) {
			error_log( 'BlueSky Auto Post: Image too large: ' . strlen( $image_data ) . ' bytes' );
			return null;
		}

		// Content-Typeを推測
		if ( empty( $content_type ) || strpos( $content_type, 'image/' ) !== 0 ) {
			$finfo        = finfo_open( FILEINFO_MIME_TYPE );
			$content_type = finfo_buffer( $finfo, $image_data );
			finfo_close( $finfo );
		}

		// サポートされる画像形式かチェック
		$supported_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $content_type, $supported_types, true ) ) {
			error_log( 'BlueSky Auto Post: Unsupported image type: ' . $content_type );
			return null;
		}

		// BlueSkyに画像をアップロード
		$upload_response = wp_remote_post(
			$this->api_endpoint . 'com.atproto.repo.uploadBlob',
			array(
				'headers' => array(
					'Content-Type'  => $content_type,
					'Authorization' => 'Bearer ' . $session['accessJwt'],
				),
				'body'    => $image_data,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $upload_response ) ) {
			error_log( 'BlueSky Auto Post: Image upload failed: ' . $upload_response->get_error_message() );
			return null;
		}

		$upload_body = wp_remote_retrieve_body( $upload_response );
		$upload_data = json_decode( $upload_body, true );

		if ( wp_remote_retrieve_response_code( $upload_response ) !== 200 ) {
			error_log( 'BlueSky Auto Post: Image upload API error: ' . $upload_body );
			return null;
		}

		if ( isset( $upload_data['blob'] ) ) {
			return $upload_data['blob'];
		}

		error_log( 'BlueSky Auto Post: No blob in upload response' );
		return null;
	}

	/**
	 * BlueSky接続テスト
	 *
	 * AJAXリクエストでBlueSkyへの接続をテストし、結果を返す
	 *
	 * @since 1.0.0
	 */
	public function test_connection() {
		check_ajax_referer( 'test_bluesky_connection' );

		$username = get_option( 'bluesky_username', '' );
		$password = get_option( 'bluesky_password', '' );

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( 'ユーザー名またはパスワードが設定されていません' );
			return;
		}

		$session = $this->create_session( $username, $password );

		if ( $session ) {
			wp_send_json_success( '接続に成功しました' );
		} else {
			wp_send_json_error( '認証に失敗しました。ユーザー名とパスワードを確認してください' );
		}
	}

	/**
	 * 手動投稿テスト
	 *
	 * AJAXリクエストで最新記事の手動投稿をテストし、結果を返す
	 *
	 * @since 1.0.0
	 */
	public function test_manual_post() {
		check_ajax_referer( 'test_manual_bluesky_post' );

		// 最新の公開記事を取得
		$latest_post = get_posts(
			array(
				'numberposts' => 1,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		if ( empty( $latest_post ) ) {
			wp_send_json_error( '投稿できる記事が見つかりません' );
			return;
		}

		$post = $latest_post[0];

		// 一時的に投稿済みフラグをクリア
		$was_posted = get_post_meta( $post->ID, '_bluesky_posted', true );
		delete_post_meta( $post->ID, '_bluesky_posted' );

		// 手動投稿を実行
		$result = $this->post_to_bluesky( $post->ID, $post );

		if ( $result ) {
			wp_send_json_success( '投稿に成功しました: ' . $post->post_title );
		} else {
			// 元の状態に戻す
			if ( $was_posted ) {
				update_post_meta( $post->ID, '_bluesky_posted', true );
			}
			wp_send_json_error( '投稿に失敗しました' );
		}
	}

	/**
	 * 投稿編集画面にMeta Boxを追加
	 *
	 * @since 1.0.1
	 */
	public function add_post_meta_boxes() {
		add_meta_box(
			'bluesky_post_control',
			'Bluesky投稿設定',
			array( $this, 'post_meta_box_callback' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Meta Boxの内容を表示
	 *
	 * @since 1.0.1
	 * @param WP_Post $post 投稿オブジェクト
	 */
	public function post_meta_box_callback( $post ) {
		// Nonceフィールドを追加
		wp_nonce_field( 'bluesky_post_meta_nonce', 'bluesky_post_meta_nonce' );

		// 現在の設定を取得
		$already_posted = get_post_meta( $post->ID, '_bluesky_posted', true );
		$manual_control = get_post_meta( $post->ID, '_bluesky_manual_post', true );

		// デフォルト状態を決定（未ポストならオン、ポスト済みならオフ）
		if ( '' === $manual_control ) {
			$manual_control = empty( $already_posted ) ? '1' : '0';
		}

		echo '<div style="margin: 10px 0;">';
		echo '<label style="display: flex; align-items: center; gap: 8px;">';
		$disabled = ! empty( $already_posted ) ? ' disabled' : '';
		echo '<input type="checkbox" name="bluesky_manual_post" value="1" ' . checked( $manual_control, '1', false ) . $disabled . '>';
		echo '<span>Blueskyに投稿する</span>';
		echo '</label>';

		if ( ! empty( $already_posted ) ) {
			echo '<p style="margin: 8px 0 0 0; color: #666; font-size: 12px;">（既に投稿済み）</p>';
		} else {
			echo '<p style="margin: 8px 0 0 0; color: #666; font-size: 12px;">（まだ投稿されていません）</p>';
		}

		// プラグインが無効の場合の警告
		if ( ! get_option( 'bluesky_auto_post_enabled', false ) ) {
			echo '<p style="margin: 8px 0 0 0; color: #d63638; font-size: 12px;">⚠ プラグイン設定で自動投稿が無効になっています</p>';
		}

		echo '</div>';
	}

	/**
	 * 投稿保存時にMeta Dataを保存
	 *
	 * @since 1.0.1
	 * @param int $post_id 投稿ID
	 */
	public function save_post_meta_data( $post_id ) {
		// Nonce確認
		if ( ! isset( $_POST['bluesky_post_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['bluesky_post_meta_nonce'] ), 'bluesky_post_meta_nonce' ) ) {
			return;
		}

		// 自動保存時はスキップ
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 権限確認
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// postタイプのみ処理
		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		// チェックボックスの値を保存
		$manual_post = isset( $_POST['bluesky_manual_post'] ) && '1' === $_POST['bluesky_manual_post'] ? '1' : '0';
		update_post_meta( $post_id, '_bluesky_manual_post', $manual_post );
	}
}

// プラグインを初期化
new BlueSkyAutoPost();

// AJAX接続テスト用
add_action(
	'wp_ajax_test_bluesky_connection',
	function () {
		$plugin = new BlueSkyAutoPost();
		$plugin->test_connection();
	}
);

// AJAX手動投稿テスト用
add_action(
	'wp_ajax_test_manual_bluesky_post',
	function () {
		$plugin = new BlueSkyAutoPost();
		$plugin->test_manual_post();
	}
);