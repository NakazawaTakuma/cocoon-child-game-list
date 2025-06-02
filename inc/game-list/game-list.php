<?php

// テーマ内のファイルを読み込むためのパスを定義



// カスタムウィジェットの定義
require_once GAME_LIST . '/inf/platform/platform-data.php';
require_once GAME_LIST . '/inf/pc/cpu-info.php';
require_once GAME_LIST. '/recommended_games.php';
require_once GAME_LIST . '/recommend_games_setting.php';
require_once GAME_LIST . '/steam-requirements-analysis.php';
require_once GAME_LIST . '/auto_post_x_games.php';
require_once GAME_LIST . '/game-db.php';
require_once GAME_LIST . '/game-detail-db.php';

 //add_action('after_setup_theme', 'my_create_igdb_tables_once');

function my_create_igdb_tables_once() {
	error_log('create table');
// 	create_igdb_game_data_table();
// 	create_igdb_game_detail_table();
// 	create_igdb_game_platforms_table();
// 	create_game_recommendations_table();
    if (!get_option('igdb_tables_created')) {
		create_igdb_game_data_table();
		create_igdb_game_detail_table();
        create_igdb_game_platforms_table();
		create_game_recommendations_table();
        update_option('igdb_tables_created', 1);
    }
}

// add_action('after_setup_theme', 'reset_igdb_tables_data');
//テーブル内の全データを削除する
function reset_igdb_tables_data() {
	error_log('delete table');
    global $wpdb;
    // 対象テーブルのリスト（プレフィックスは環境に合わせて）
    $tables = array(
        $wpdb->prefix . 'igdb_game_data',
//         $wpdb->prefix . 'igdb_game_detail',
//         $wpdb->prefix . 'igdb_game_platforms',
//         $wpdb->prefix . 'game_recommendations'
    );
    
    foreach ( $tables as $table ) {
        // TRUNCATE を使うと高速に全データを削除できます（オートインクリメントもリセットされる）
        $wpdb->query("TRUNCATE TABLE $table");
        // ※ TRUNCATE が使えない場合は以下の DELETE を使う
        // $wpdb->query("DELETE FROM $table");
    }
    
    // 再作成済みオプションも削除（必要なら）
    delete_option('igdb_tables_created');
}


//テーブル自体を削除
// add_action('after_setup_theme', 'drop_igdb_tables');
function drop_igdb_tables() {
    error_log('drop tables');
    global $wpdb;
    // 削除対象テーブルのリスト（プレフィックスは環境に合わせて）
    $tables = array(
        $wpdb->prefix . 'igdb_game_data',
        $wpdb->prefix . 'igdb_game_detail',
        $wpdb->prefix . 'igdb_game_platforms',
        $wpdb->prefix . 'game_recommendations'
    );
    
    foreach ( $tables as $table ) {
        // テーブルが存在すれば削除
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // 再作成済みオプションも削除（必要なら）
    delete_option('igdb_tables_created');
}




/**
 * Xに自動投稿
 */
// if ( ! wp_next_scheduled( 'auto_post_x_games' ) ) {
//     wp_schedule_event( strtotime('tomorrow midnight'), 'daily', 'auto_post_x_games' );
// }
// add_action( 'auto_post_x_games', 'auto_post_x_games_to_x' );




/**
 * 外部 JS ファイルを登録し、WP にローカライズ変数を渡す
 */
function my_enqueue_recommended_games_script() {
    wp_enqueue_script(
        'recommended-games', // ハンドル名
        GAME_LIST_URL . '/js/recommended-games.js', // ファイルパス（子テーマの場合）
        array('jquery'),
        '1.0',
        true  // フッターに出力
    );
    
    // AJAX の URL をローカライズ変数として渡す
    wp_localize_script('recommended-games', 'my_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}

add_action('wp_enqueue_scripts', 'my_enqueue_recommended_games_script');






function enqueue_igdb_games_scripts() {
    wp_enqueue_script(
        'igdb-games',
        GAME_LIST_URL . '/js/igdb-games.js',
        array('jquery'),
        '1.0',
        true
    );
    
    // PHP から JS に変数を渡す
    $currentReleaseFilter = isset($_GET['release']) ? sanitize_text_field($_GET['release']) : 'upcoming';
    $currentPlatformFilter = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : 'all';
    $currentHypesFilter = isset($_GET['hypes']) ? sanitize_text_field($_GET['hypes']) : 10;
    
    wp_localize_script( 'igdb-games', 'my_ajax_object', array(
        'ajax_url'              => admin_url( 'admin-ajax.php' ),
        'currentReleaseFilter'  => $currentReleaseFilter,
        'currentPlatformFilter' => $currentPlatformFilter,
		'currentHypesFilter' => $currentHypesFilter,
        'itemsPerPage'          => 30
    ));
}
add_action( 'wp_enqueue_scripts', 'enqueue_igdb_games_scripts' );




function enqueue_youtube_iframe_api() {
    wp_enqueue_script( 'youtube-iframe-api', 'https://www.youtube.com/iframe_api', array(), null, true );
}
add_action( 'wp_enqueue_scripts', 'enqueue_youtube_iframe_api' );



// -------------------------------------------------------------
// ① IGDB API 認証情報（wp-config.php で定義するのが望ましい）
define('IGDB_CLIENT_ID', '8tpyc06kpd5kt4n237jbt27hy2vcry');  // Client ID
define('IGDB_CLIENT_SECRET', 'jqzg8jkthfrpg9mnfsj465tbsjucw6'); // Client Secret

// -------------------------------------------------------------
// ② アクセストークンの更新と取得
function update_igdb_access_token() {
    $url = 'https://id.twitch.tv/oauth2/token';
    $body = array(
        'client_id'     => IGDB_CLIENT_ID,
        'client_secret' => IGDB_CLIENT_SECRET,
        'grant_type'    => 'client_credentials',
    );
    $response = wp_remote_post($url, array('body' => $body));
    if ( is_wp_error($response) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB API トークン取得エラー: ' . $response->get_error_message());
        }
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ( isset($data['access_token']) ) {
        $expires_at = time() + $data['expires_in'] - 300;
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB Access Token: ' . $data['access_token']);
            error_log('IGDB Token Expires At: ' . date('Y-m-d H:i:s', $expires_at));
        }
        update_option('igdb_access_token', $data['access_token'], false);
        update_option('igdb_token_expires_at', $expires_at, false);
        return $data['access_token'];
    } else {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB API トークンレスポンスエラー: ' . print_r($data, true));
        }
        return false;
    }
}

function get_valid_igdb_access_token() {
    $access_token = get_option('igdb_access_token');
    $expires_at = get_option('igdb_token_expires_at', 0);
    if ( ! $access_token || time() >= $expires_at ) {
        $access_token = update_igdb_access_token();
    }
    return $access_token;
}

// -------------------------------------------------------------
// ③ 定期更新のスケジュールイベント
function schedule_igdb_token_refresh() {
    if ( ! wp_next_scheduled('igdb_refresh_token_event') ) {
        wp_schedule_event(time(), 'twicedaily', 'igdb_refresh_token_event');
    }
}
add_action('wp', 'schedule_igdb_token_refresh');
add_action('igdb_refresh_token_event', 'update_igdb_access_token');
https://game-plusplus.com/wp-admin/theme-editor.php?file=game-detail.php&theme=cocoon-child-master



// APIからゲームデータを取得する処理（キャッシュ対象となる raw データを取得）
function fetch_igdb_game_api($offset = 0, $filterIdx = 0) {
    $url = 'https://api.igdb.com/v4/games';
	
if ($filterIdx === 1) {
    $year_ago = strtotime('-20 year');
 	$body = 'fields id, name, websites, game_localizations.*, release_dates.*, first_release_date, cover.*, screenshots.*, videos.*, similar_games, external_games, platforms.abbreviation, platforms.platform_logo.image_id, hypes, language_supports.*, game_engines.*, involved_companies.company.name, total_rating, total_rating_count, game_modes.*, genres.*, player_perspectives.*, multiplayer_modes.*; where first_release_date != null & first_release_date >= ' . $year_ago . ' & screenshots != null & cover != null; sort hypes desc; limit 300; offset ' . strval($offset) . ';';
} else {
    $month_ago = strtotime('-1 year');
    $body = 'fields id, name, websites, game_localizations.*, release_dates.*, first_release_date, cover.*, screenshots.*, videos.*, similar_games, external_games, platforms.abbreviation, platforms.platform_logo.image_id, hypes, language_supports.*, game_engines.*, involved_companies.company.name, total_rating, total_rating_count, game_modes.*, genres.*, player_perspectives.*, multiplayer_modes.*; where first_release_date >= ' . $month_ago . ' & screenshots != null & cover != null; sort hypes desc; limit 300; offset ' . strval($offset) . ';';
}
	
    $access_token = get_valid_igdb_access_token();
    $args = array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => array(
            'Client-ID'     => IGDB_CLIENT_ID,
            'Authorization' => 'Bearer ' . $access_token,
            'Accept'        => 'application/json',
        ),
    );
    $response = wp_remote_post($url, $args);
    if ( is_wp_error($response) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB API Error: ' . $response->get_error_message());
        }
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
	
}
	

function fetch_igdb_game_by_id($game_id) {
    $url = 'https://api.igdb.com/v4/games';
    // 必要なフィールドを指定し、特定のゲームIDのみを取得するクエリを作成
    $body = 'fields id, name, websites, game_localizations.*, release_dates.*, first_release_date, cover.*, screenshots.*, videos.*, similar_games, external_games, platforms.abbreviation, platforms.platform_logo.image_id, hypes, language_supports.*, game_engines.*, involved_companies.company.name, total_rating, total_rating_count, game_modes.*, genres.*, player_perspectives.*, multiplayer_modes.*; where id = ' . intval($game_id) . ';';
    
    $access_token = get_valid_igdb_access_token();
    $args = array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => array(
            'Client-ID'     => IGDB_CLIENT_ID,
            'Authorization' => 'Bearer ' . $access_token,
            'Accept'        => 'application/json',
        ),
    );
    $response = wp_remote_post($url, $args);
    if ( is_wp_error($response) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB API Error: ' . $response->get_error_message());
        }
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}




/**
 * 毎日更新するための Cron イベント処理
 *
 * フィルター順序を任意に設定できるようにし、update_igdb_game_data() を順番に実行する。
 */
function update_igdb_game_data_daily() {
    // 任意のフィルター順序。すでにオプションに設定されていなければ、デフォルトは [0, 1]
    $filter_order = array(0, 1);
    
    // 現在の順番のインデックスを取得（なければ 0 をデフォルトに）\n
    $current_index = get_option('igdb_filter_order_index', 0);
    
    // 現在の順序配列から実行するフィルター値を取得\n
    $filter_value = $filter_order[$current_index];
    
    // 現在のフィルター値に基づいてデータ更新を実行\n
    update_igdb_game_data($filter_value);
    
    // 次回実行するため、現在のインデックスを更新（順序配列の長さを超えたら 0 に戻す）\n
    $next_index = ($current_index + 1) % count($filter_order);
    update_option('igdb_filter_order_index', $next_index, false);
}
add_action('update_igdb_game_data_daily', 'update_igdb_game_data_daily');





function update_igdb_game_data($filterIdx = 0){
	error_log('update_igdb_game_data ');
    $offsetOption = 'igdb_api_offset_' . intval($filterIdx);
    // 現在の offset をオプションから取得（なければ 0）
    $offset = get_option($offsetOption, 0);
    
    // IGDB API から最新データを取得
    $raw_data = fetch_igdb_game_api($offset, $filterIdx);
	
    // 取得データが正しい配列でない場合は処理を中断
    if (false === $raw_data || !is_array($raw_data)) {
        return;
    }
    // 空の配列が返ってきた場合は offset をリセット
    if (empty($raw_data)) {
        update_option($offsetOption, 0, false);
        return;
    }
	
		// raw_data が配列であることを前提とする
	for ($i = 0; $i < 20 && isset($raw_data[$i]); $i++) {
		if (isset($raw_data[$i]['id'])) {
			error_log('id: ' . $raw_data[$i]['id']);
		} else {
			error_log("Element {$i} に id が見つかりませんでした。");
		}
	}
	
	error_log('update_igdb_game_data2 ');
	$currentMemory = memory_get_usage();
	error_log("Current memory usage: " . number_format($currentMemory) . " bytes"); 
	$peakMemory = memory_get_peak_usage();
	error_log("Peak memory usage: " . number_format($peakMemory) . " bytes");
//     set_raw_games($raw_data);
    set_game_detail_data($raw_data);
	error_log('update_igdb_game_data3');
	$currentMemory = memory_get_usage();
	error_log("Current memory usage: " . number_format($currentMemory) . " bytes"); 
	$peakMemory = memory_get_peak_usage();
	error_log("Peak memory usage: " . number_format($peakMemory) . " bytes");
	unset($raw_data);
//     set_recommended_games();
    error_log('update_igdb_game_data4');
	$currentMemory = memory_get_usage();
	error_log("Current memory usage: " . number_format($currentMemory) . " bytes"); 
	$peakMemory = memory_get_peak_usage();
	error_log("Peak memory usage: " . number_format($peakMemory) . " bytes");
    // 次回の offset を更新
    $new_offset = $offset + 300;
    update_option($offsetOption, $new_offset, false);
    
    // 不要になった変数を解放
    
}



// wp_clear_scheduled_hook('update_igdb_game_data_daily');

add_action( 'update_igdb_game_data_daily', 'update_igdb_game_data_daily' );

function custom_cron_six_hourly( $schedules ) {
    $schedules['six_hourly'] = array(
//         'interval' => 6 * 3600, // 6時間 = 21600秒
		'interval' => 15 * 60, // 3分 = 300秒
        'display'  => __('Every 6 Hours')
    );
    return $schedules;
}
add_filter('cron_schedules', 'custom_cron_six_hourly');

if ( ! wp_next_scheduled( 'update_igdb_game_data_daily' ) ) {
 // 次の 0:01 のタイムスタンプを算出（明日の0:01から開始）
    $timestamp = time(); //strtotime('tomorrow 00:01');
    wp_schedule_event( $timestamp, 'six_hourly', 'update_igdb_game_data_daily' );
}



/**
 * IGDB 関連のオプションをすべて削除する関数
 *
 * ここで削除対象とするオプションキーは、下記の通りです：
 *  - 生データのキャッシュ: 'igdb_game_data_raw'
 *  - ソート済みデータ: 'igdb_game_data_rating', 'igdb_game_data_rating_count', 'igdb_game_data_upcoming', 'igdb_game_data_released'
 *  - オフセット管理: 'igdb_api_offset_0', 'igdb_api_offset_1' （必要に応じて増える場合は追加） 
 *  - フィルター順序: 'igdb_filter_order_index', 'igdb_filter_order'
 *  - IGDB アクセストークン: 'igdb_access_token', 'igdb_token_expires_at'
 *  - 各ゲームの詳細情報（キーが 'game_detail_' で始まるもの）\n
 */
function delete_all_igdb_options() {
    // 削除対象の固定オプションキーを配列にまとめる
    $option_keys = array(
//          'igdb_game_data_raw',
//         'igdb_game_data_rating',
//         'igdb_game_data_rating_count',
//         'igdb_game_data_upcoming',
//         'igdb_game_data_released',
        'igdb_api_offset_0',
        'igdb_api_offset_1',
        'igdb_filter_order_index',
        'igdb_filter_order',
//  		'igdb_game_data_raw_index_count',
//         'igdb_access_token',
//         'igdb_token_expires_at'
    );
    foreach ( $option_keys as $key ) {
        delete_option( $key );
    }
    // ゲーム詳細情報（オプション名が 'game_detail_' で始まるもの）も削除する\n
//     global $wpdb;
//     $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'game_detail_%'" );
// 	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'game_min_detail_%'" );
// 	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'igdb_game_data_raw_%'" );
}




function get_dlc_game_data($parent_game_id) {
    $url = 'https://api.igdb.com/v4/games';
    $body = 'fields id, name, game_localizations.*, release_dates.*, first_release_date, cover.*, screenshots.*, videos.*, platforms.abbreviation, platforms.platform_logo.image_id, hypes, category; where category != 5 & category != 12 & parent_game = ' . intval($parent_game_id) . ';';
    $access_token = get_valid_igdb_access_token();
    $args = array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => array(
            'Client-ID'     => IGDB_CLIENT_ID,
            'Authorization' => 'Bearer ' . $access_token,
            'Accept'        => 'application/json',
        ),
    );
    $response = wp_remote_post($url, $args);
    if ( is_wp_error($response) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB DLC API Error: ' . $response->get_error_message());
        }
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data)) {
        // cover または name が存在するもののみ抽出
        $filtered = array();
        foreach ($data as $item) {
            if ((!empty($item['cover'])) || (!empty($item['name']))) {
                $filtered[] = $item;
            }
        }
        return $filtered;
    }
    return false;
}



function get_website_data($website_ids) {

    if (empty($website_ids) || !is_array($website_ids)) {
        return false; // 空の配列や無効な入力を処理
    }

    $url = 'https://api.igdb.com/v4/websites';
    $ids_string = implode(',', array_map('intval', $website_ids)); // IDをカンマ区切りで結合
    $body = 'fields id, url, type; where id = (' . $ids_string . ');';

    $access_token = get_valid_igdb_access_token();
    $args = array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => array(
            'Client-ID'     => IGDB_CLIENT_ID,
            'Authorization' => 'Bearer ' . $access_token,
            'Accept'        => 'application/json',
        ),
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('IGDB Website API Error: ' . $response->get_error_message());
        }
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return !empty($data) ? $data : false;
}





function get_steam_uid_from_igdb($igdb_game_id) {
    $url = 'https://api.igdb.com/v4/external_games';
    $body = 'fields uid, name, category; where category = 1 & game = ' . intval($igdb_game_id) . ';';
    $access_token = get_valid_igdb_access_token();
    $args = array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => array(
            'Client-ID'     => IGDB_CLIENT_ID,
            'Authorization' => 'Bearer ' . $access_token,
            'Accept'        => 'application/json',
        ),
    );
    $response = wp_remote_post($url, $args);
    if ( is_wp_error($response) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('IGDB External Games API Error: ' . $response->get_error_message());
        }
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($data) && !empty($data[0]['uid'])) {
        return $data[0]['uid'];
    }
    return false;
}







/**
 * ② appid から現在のプレイヤー数を取得する関数
 *
 * @param int $appId Steam のアプリケーションID
 * @return int|false 現在のプレイヤー数。取得できなければ false。
 */
function getCurrentPlayerCount($appId) {
    $url = "https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid={$appId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if(isset($data['response']['player_count'])) {
        return (int)$data['response']['player_count'];
    }
    return false;
}





/**
 * ③ appid からレビュー情報を取得する関数
 *
 * Steam の AppReviews API を利用して、レビューのサマリー情報を取得する。
 *
 * @param int $appId Steam のアプリケーションID
 * @return array|false 取得できた場合は、例として [ 'total_reviews' => 総レビュー数, 'review_score' => 評価スコア ] などの情報を返す。取得できなければ false。
 */
function getReviewInformation($appId) {
    // 全言語、全購入者対象、全レビュー種別
    $url = "https://store.steampowered.com/appreviews/{$appId}?json=1&language=all&purchase_type=all&review_type=all";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['query_summary'])) {
        return $data['query_summary'];
    }
    return false;
}



/**
 * Steam APIデータ取得関数
 *
 * @param int $appId Steam のアプリケーションID
 * @return array|false 成功時は連想配列、失敗時は false
 */
function fetchSteamData($appId) {

    $url = "https://store.steampowered.com/api/appdetails?appids={$appId}&cc=jp&l=japanese";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data[$appId]['success']) || $data[$appId]['success'] !== true) {
        return false;
    }

    return $data[$appId]['data'];
}
/**
 * 日本語の詳細説明を取得
 */
function getJapaneseGameDescription($data) {
//     $data = fetchSteamData($appId);
//     if (!$data) return false;

    return $data['detailed_description'] ?? $data['short_description'] ?? false;
}

/**
 * ゲームメディア (画像・動画) を取得
 */
function getGameMedia($data) {
    $images = array_map(fn($ss) => $ss['path_full'] ?? '', $data['screenshots'] ?? []);

    $videos = array_map(function($movie) {
        $video_url = $movie['webm']['480'] ?? '';
        $thumbnail = $movie['thumbnail'] ?? '';

        // URL が http:// で始まる場合、https:// に変更
        if (strpos($video_url, 'http://') === 0) {
            $video_url = preg_replace('/^http:/', 'https:', $video_url);
        }

        return [
            'url' => $video_url,
            'thumbnail' => $thumbnail
        ];
    }, $data['movies'] ?? []);

    return ['images' => array_filter($images), 'videos' => array_filter($videos)];
}


/**
 * ゲームタイトルを取得
 */
function getGameTitleJapanese($data) {
//     $data = fetchSteamData($appId);
    return $data['name'] ?? false;
}

function replaceHeadings($html, $tags = ['h1', 'h2'], $newTag = 'h3') {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    // HTMLエンティティに変換して読み込む
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    // 指定された見出しタグを置換
    foreach ($tags as $tag) {
        $nodes = $doc->getElementsByTagName($tag);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $newNode = $doc->createElement($newTag, $node->textContent);
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $newNode->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }
            $node->parentNode->replaceChild($newNode, $node);
        }
    }
    
//     // すべての <img> タグの style 属性から既存の width 指定を削除し、width: 100%; を設定
//     $images = $doc->getElementsByTagName('img');
//     foreach ($images as $img) {
//         $existingStyle = $img->getAttribute('style');
//         // 正規表現で既存の width 設定を削除
//         $newStyle = preg_replace('/width\s*:\s*[^;]+;?/i', '', $existingStyle);
//         $newStyle = trim($newStyle);
//         if (!empty($newStyle)) {
//             $newStyle .= ' ';
//         }
//         $newStyle .= 'width: 100%;';
//         $img->setAttribute('style', $newStyle);
//     }

	
    return $doc->saveHTML();
}




function getSteamPcRequirements($appData) {
    // `$appData`がnullや無効な場合のチェック
    if (!$appData || !is_array($appData)) {
        return false;
    }

    // `pc_requirements`が存在するか確認して返す
    return $appData['pc_requirements'] ?? false;
}




/**
 * Steamのゲームリンクを取得
 */
function get_steam_game_link($appId) {
    return "https://store.steampowered.com/app/" . intval($appId) . "/";
}








/**
 * ゲームごとの追加情報を読み込み、マージする関数
 */
function merge_game_extra_info($detail, $game_id) {
    static $extra_info = null;
    if ($extra_info === null) {
        $extra_info = array();
        // ゲーム追加情報が入っているフォルダのパス
        $dir = GAME_LIST. '/game-extra-info/';
        // ディレクトリ内のすべてのPHPファイルを取得
        $files = glob($dir . '*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                $data = include($file);
                if (is_array($data)) {
                    // 各ファイルが返す配列は、キーがゲームID、値が追加情報と仮定
                    foreach ($data as $id => $info) {
                        if (isset($extra_info[$id])) {
                            // 同じゲームIDが既に存在する場合は、再帰的にマージ
                            $extra_info[$id] = array_replace_recursive($extra_info[$id], $info);
                        } else {
                            $extra_info[$id] = $info;
                        }
                    }
                }
            }
        }
    }
    // 指定されたゲームIDの情報があれば、$detail とマージして返す
    if (isset($extra_info[$game_id])) {
        return array_replace_recursive($detail, $extra_info[$game_id]);
    }
    return $detail;
}

function get_release_status_text($release_timestamp) {
    // $release_timestamp が数値でない、または0以下の場合は空文字を返す
    if (!is_numeric($release_timestamp) || $release_timestamp <= 0) {
        return "";
    }

    // 今日の日付のタイムスタンプ（時間部分は0時にリセット）
    $today = strtotime(date('Y-m-d'));
    // 明日のタイムスタンプ
    $tomorrow = strtotime('+1 day', $today);
    // 今日から発売日までの日数を計算（1日単位で切り上げ）
    $diff_days = ceil(($release_timestamp - $today) / 86400);
    
    // 発売日が今日の場合
    if ($release_timestamp >= $today && $release_timestamp < $tomorrow) {
        return '本日発売';
    } elseif ($release_timestamp >= $today) {
        // 未来の場合
        if ($diff_days >= 365) {
            $years = floor($diff_days / 365);
            return '発売まで ' . $years . ' 年';
        } elseif ($diff_days >= 30) {
            $months = floor($diff_days / 30);
            return '発売まで ' . $months . ' か月';
        } else {
            return '発売まで ' . $diff_days . ' 日';
        }
    } else {
        // 過去の場合
        $past_days = -$diff_days; // 日数を正の値に変換
        if ($past_days >= 365) {
            $years = floor($past_days / 365);
            return '発売中（' . $years . ' 年前に発売）';
        } elseif ($past_days >= 30) {
            $months = floor($past_days / 30);
            return '発売中（' . $months . ' か月前に発売）';
        } else {
            return '発売中（' . $past_days . ' 日前に発売）';
        }
    }
}






/**
 * プラットフォーム情報のHTMLを生成する関数
 */
function sort_platforms($platforms) {
    $platform_order = get_platform_order();
    $platform_colors = get_platform_colors();
    $platformsData = array();
    if (is_array($platforms)) {
        foreach ($platforms as $platform) {
            $abbr = $platform;
            $order = isset($platform_order[$abbr]) ? $platform_order[$abbr] : 999;
            $color = isset($platform_colors[$abbr]) ? $platform_colors[$abbr] : '#000';
            $platformsData[] = array('abbr' => $abbr, 'order' => $order, 'color' => $color);
        }
    }
    usort($platformsData, function($a, $b) {
        return $a['order'] - $b['order'];
    });
    return $platformsData;
}

function render_platforms_html($platforms) {
    $platformsData = sort_platforms($platforms);
    $html = '<div class="platform-abbreviations" style="margin-bottom: 20px; font-size: 1rem;">';
    foreach ($platformsData as $p) {
        $html .= '<span style="display: inline-block; background-color: ' . esc_attr($p['color']) . '; color: #fff; border-radius: 5px; padding: 0px 8px; margin-right: 4px; margin-bottom: 4px;">' . esc_html($p['abbr']) . '</span>';
    }
    $html .= '</div>';
    return $html;
}








// ⑤ ゲーム一覧と詳細カード出力のショートコード関数（最適化後）
function igdb_game_list_shortcode() {
//        delete_all_igdb_options();

	$total_games = get_igdb_game_data_count();
	echo "総ゲーム数: " . $total_games;
// 		$option_name0 = 'igdb_api_offset_0';
//  	$option_name1 = 'igdb_api_offset_1';
//  	$cached_data0 = get_option( $option_name0, false );
// 	if ( false !== $cached_data0 ) {
// 		print_r('________');
// 		print_r($cached_data0);
// 	}
//  	$cached_data1 = get_option( $option_name1, false );
// 	if ( false !== $cached_data1 ) {
// 		print_r('________');
// 		print_r($cached_data1);
// 	}	
    // 初回ロード時に表示する件数と、以降追加で読み込む件数
    $itemsPerPage = 30;
    $today = strtotime(date('Y-m-d'));
	$tomorrow = strtotime('+1 day', $today);
    
    // URLパラメータからフィルター条件を取得（指定がなければ初期値）
    $currentReleaseFilter = isset($_GET['release']) ? sanitize_text_field($_GET['release']) : 'upcoming';
    $currentPlatformFilter = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : 'all';
	$currentHypesFilter = isset($_GET['hypes']) ? sanitize_text_field($_GET['hypes']) : 1;
	$currentSearch = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
   

    $game_details = get_sort_games($currentReleaseFilter, $offset = 0,$itemsPerPage,$currentPlatformFilter, $currentHypesFilter, $currentSearch, false);
	
    $output = '';

    // インラインCSS（本番では外部CSSへの切り出しを検討）
    $output .= '<style>
        .igdb-game-card:hover { background-color: #ccc; }
        .release-btn { background: transparent;  padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .release-btn.active { background-color: #0073aa; color: #fff; border-color: #006799; }
        .platform-btn { width: 68px; height: 68px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: transparent; background-size: cover; background-repeat: no-repeat; cursor: pointer; margin: 3px; padding: 0; border-radius: 5px; }
        .platform-btn .platform-icon { display: block; margin: 4px 0; }
        .platform-btn .platform-icon img { max-width: 100%; max-height: 38px; }
        .platform-btn .platform-text { font-size: 12px; text-align: center; }
        .platform-btn .active-icon { display: none; }
        .platform-btn.active .inactive-icon { display: none; }
        .platform-btn.active .active-icon { display: inline-block; }
        .platform-btn.active .platform-text { color: #ffffff; }
        /* 各プラットフォームのアクティブ時背景色 */
        .platform-btn.active[data-platform="all"] { background-color:#000000; }
        .platform-btn.active[data-platform="Switch"] { background-color:#ff0000; }
		.platform-btn.active[data-platform="Switch 2"] { background-color:#ff0000; }
        .platform-btn.active[data-platform="PS4"] { background-color: #003087; }
        .platform-btn.active[data-platform="PS5"] { background-color: #003087; }
        .platform-btn.active[data-platform="XONE"] { background-color: #52b043; }
        .platform-btn.active[data-platform="Series X|S"] { background-color: #52b043; }
        .platform-btn.active[data-platform="PC"] { background-color: #ffa500; }
        .platform-btn.active[data-platform="Mac"] { background-color: #ffa500; }
        .platform-btn.active[data-platform="Linux"] { background-color: #ffa500; }
        .platform-btn.active[data-platform="iOS"] { background-color: #8a2be2; }
        .platform-btn.active[data-platform="Android"] { background-color: #8a2be2; }
		.platform-btn[data-platform="all"] { color:#000000 !important; }
        .platform-btn[data-platform="Switch"] { color:#ff0000; border: 1px solid #ff0000; }
		.platform-btn[data-platform="Switch 2"] { color:#ff0000; border: 1px solid #ff0000; }
        .platform-btn[data-platform="PS4"] { color: #003087; }
        .platform-btn[data-platform="PS5"] { color: #003087; }
        .platform-btn[data-platform="XONE"] { color: #52b043; }
        .platform-btn[data-platform="Series X|S"] { color: #52b043; }
        .platform-btn[data-platform="PC"] { color: #ffa500; }
        .platform-btn[data-platform="Mac"] { color: #ffa500; }
        .platform-btn[data-platform="Linux"] { color: #ffa500; }
        .platform-btn[data-platform="iOS"] { color: #8a2be2; }
        .platform-btn[data-platform="Android"] { color: #8a2be2; }
    </style>';

    $output .= '<div class="game-list">';

    // フィルター用ボタン群（HTMLはそのまま）
    $output .= '<div id="igdb-filter-controls" style="margin-bottom: 20px;">';
	
	
	
//検索
$output .= '<div id="search-filter" style="margin-bottom: 20px;">';
    $output .= '<form id="search-form" method="get" action="" style="display: flex; max-width: 500px; width: 100%;">';
        $output .= '<input type="text" id="search" name="search" placeholder="検索キーワード" style="flex: 1; padding: 8px 12px; font-size: 1rem; border: 1px solid #ccc; border-right: none; border-radius: 4px 0 0 4px; max-width: 500px; box-sizing: border-box;">';
$output .= '<button type="submit" style="padding: 8px 12px; font-size: 1rem; border: 1px solid #ccc; border-left: none; background: #000; color: #fff; border-radius: 0 4px 4px 0; cursor: pointer;">検索</button>';
    $output .= '</form>';
$output .= '</div>';



$output .= '<div id="hypes-filter" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">';
    // スライダー部分
    $output .= '<div style="width:400px; position: relative;">';
    $output .= '<label for="hypes-slider" style="display: block; font-weight: bold; margin-bottom: 5px;">注目度: <span id="hypes-value">' . esc_html($currentHypesFilter) . '以上</span></label>';
	    // マーク部分（右側に縦に３つの円を一定間隔で配置）
	$output .= '<div id="slider" style="display: flex; flex-direction: row; gap: 10px;">';
    $output .= '<input id="hypes-slider" type="range" min="0" max="100" value="' . esc_attr($currentHypesFilter) . '" style="width:400px; height: 10px; border-radius: 5px; outline: none; background-image: linear-gradient(to right, #e0e0e0 0%, #e0e0e0 0%, #ffb83c 0%, #ffb83c 100%); -webkit-appearance: none;">';
		foreach (range(1, 3) as $i) {
			$output .= '<div class="slider-mark" style="width: 10px; height: 10px; border-radius: 50%; background: #ffb83c;"></div>';
		}
	$output .= '</div>';
    $output .= '</div>';
    
	

$output .= '</div>';



	
	
	
	
        $output .= '<div id="release-filter" style="margin-bottom: 10px;">';
  $output .= '<button class="release-btn'. ($currentReleaseFilter==='upcoming' ? ' active' : '') .'" data-filter="upcoming">発売予定</button>';
            $output .= '<button class="release-btn'. ($currentReleaseFilter==='released' ? ' active' : '') .'" data-filter="released" >発売中</button>';
            $output .= '<button class="release-btn'. ($currentReleaseFilter==='rating' ? ' active' : '') .'" data-filter="rating">高評価</button>';
            $output .= '<button class="release-btn'. ($currentReleaseFilter==='rating_count' ? ' active' : '') .'" data-filter="rating_count">レビュー数</button>';
        
        $output .= '</div>';
        $output .= '<div id="platform-filter" style="display: flex; flex-wrap: wrap; gap: 0px;">';
            // 各プラットフォームボタン
            $platforms = array(
                'all' => array('すべて', '2025/03/all-icon-c.png', '2025/03/all-icon.png'),
				'Switch 2' => array('Switch 2', '2025/04/switch_2-icon-c.png', '2025/04/switch_2-icon.png'),
                'Switch' => array('Switch', '2025/03/switch-icon-c.png', '2025/03/switch-icon.png'),
                'PS4' => array('PS4', '2025/03/ps4-icon-c.png', '2025/03/ps4-icon.png'),
                'PS5' => array('PS5', '2025/03/ps5-icon-c.png', '2025/03/ps5-icon.png'),
                'XONE' => array('XONE', '2025/03/xbox-icon-c.png', '2025/03/xbox-icon.png'),
                'Series X|S' => array('Series X|S', '2025/03/xbox-icon-c.png', '2025/03/xbox-icon.png'),
                'PC' => array('PC', '2025/03/pc-icon-c.png', '2025/03/pc-icon.png'),
                'Mac' => array('Mac', '2025/03/mac-icon-c.png', '2025/03/mac-icon.png'),
                'Linux' => array('Linux', '2025/03/pc-icon-c.png', '2025/03/pc-icon.png'),
                'iOS' => array('iOS', '2025/03/phone-icon-c.png', '2025/03/phone-icon.png'),
                'Android' => array('Android', '2025/03/phone-icon-c.png', '2025/03/phone-icon.png'),
            );
            foreach($platforms as $key => $vals){
				$active = ($currentPlatformFilter === $key) ? ' active' : '';
                $output .= '<button class="platform-btn'. $active .'" data-platform="'. esc_attr($key) .'" style="border: 1px solid;">';
                    $output .= '<span class="platform-icon">';
                        $output .= '<img class="inactive-icon" src="https://game-plusplus.com/wp-content/uploads/'. esc_attr($vals[1]) .'" alt="'. esc_attr($vals[0]) .'" />';
                        $output .= '<img class="active-icon" src="https://game-plusplus.com/wp-content/uploads/'. esc_attr($vals[2]) .'" alt="'. esc_attr($vals[0]) .'" />';
                    $output .= '</span>';
                    $output .= '<span class="platform-text">'. esc_html($vals[0]) .'</span>';
                $output .= '</button>';
            }
        $output .= '</div>';
    $output .= '</div>';

	
    // ゲームカード一覧コンテナ
    $output .= '<div class="igdb-game-list" style="display: flex; flex-wrap: wrap; justify-content: center; transform-origin: top center;">';
	
    foreach ( $game_details as $detail_data ) {
        $game_id = $detail_data['id'];
            $detail_data_json = htmlspecialchars(json_encode($detail_data), ENT_QUOTES, 'UTF-8');
            $total_rating_display = ($detail_data['total_rating'] != 0) ? sprintf("%.1f", $detail_data['total_rating']) : '-';
            $detail_url = site_url('/game-detail/?id=' . $game_id);

            $output .= '<a href="' . esc_url($detail_url) . '" style="text-decoration: none; color: inherit;">';
                $output .= '<div class="igdb-game-card" data-release="' . esc_attr($detail_data['release_timestamp']) . '" data-platforms="' . esc_attr(implode(',', $detail_data['platforms'])) . '" data-rating="' . esc_attr($detail_data['total_rating']) . '" data-rating_count="' . esc_attr($detail_data['total_rating_count']) . '" data-detail=\'' . $detail_data_json . '\' style="border-radius: 15px;  margin: 0; padding: 0.4rem; cursor: pointer;">';
                    // スライドショーコンテナ（画像は lazy load 対応）
                    $output .= '<div class="game-slideshow2" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
                        $output .= '<div class="game-slideshow" id="slideshow-' . esc_attr($game_id) . '" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
						$images = $detail_data['images'];
						shuffle($images); // 配列内の画像の順番をランダムにする
                        $first = true;
                        foreach ( $images as $img_url ) {
                            $display = $first ? 'block' : 'none';
                            $output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: ' . $display . ';" />';
                            $first = false;
                        }
                        $output .= '</div>';
                        // カバー画像を右下にオーバーレイ表示
                        $output .= '<div class="cover-thumb2" style="position: absolute; top: 50%; left: 0; transform: translate(5%, -50%); width: 30%; height: auto; z-index: 2;">';
                            $output .= '<img src="' . esc_url($detail_data['cover']) . '" alt="' . esc_attr($detail_data['name']) . '" loading="lazy" style="width: 100%; height: auto; object-fit: contain; box-shadow: 0 4px 8px rgba(50,50,50,0.9);">';
                        $output .= '</div>';
                    $output .= '</div>';
                    // ゲーム情報エリア
                    $output .= '<div class="game-info-container" style="display: flex; align-items: center; margin-top: 0.2rem;">';
                        $output .= '<div class="cover-thumb" style="width: 120px; flex-shrink: 0;">';
                            $output .= '<img src="' . esc_url($detail_data['cover']) . '" alt="' . esc_attr($detail_data['name']) . '" loading="lazy" style="width: 100%; height: auto; object-fit: contain;">';
                        $output .= '</div>';
                        $output .= '<div class="game-info" style="padding-left: 10px; flex-grow: 1;">';
                            $output .= '<h3 style="margin: 0; font-size: 1.2rem;">' . esc_html($detail_data['name']) . '</h3>';
                            $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html($detail_data['company']) . '</p>';
							$output .= '<p style="margin: 0; font-size: 1rem;">注目度: ' . esc_html($detail_data['hypes']) . '</p>';
                            $output .= '<p style="margin: 0; font-size: 1rem;">発売日: ' . esc_html($detail_data['release_date']) . '</p>';
                            $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html(get_release_status_text($detail_data['release_timestamp'])) . '</p>';
                            if (!empty($detail_data['release_timestamp']) && $detail_data['release_timestamp'] < $tomorrow) {
                                $output .= '<p style="margin: 0; font-size: 1rem;">評価: ' . esc_html($total_rating_display) . '（レビュー数：' . esc_html($detail_data['total_rating_count']) . '）</p>';
                            }
                            if (!empty($detail_data['platforms']) && is_array($detail_data['platforms'])) {
                                $output .= render_platforms_html($detail_data['platforms']);
                            }
                        $output .= '</div>';
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</a>';
			
			        $output .= "<script>
        </script>";

    }
    $output .= '</div>'; // .igdb-game-list
    $output .= '</div>'; // .game-list



    return $output;
}

add_shortcode('igdb_games', 'igdb_game_list_shortcode');













// ② AJAX 用ハンドラー（admin-ajax.php 経由で追加分のゲームカード HTML を返す）
function load_more_games_ajax_handler() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $release_filter = isset($_POST['release_filter']) ? sanitize_text_field($_POST['release_filter']) : 'upcoming';
    $platform_filter = isset($_POST['platform_filter']) ? sanitize_text_field($_POST['platform_filter']) : 'all';
    $itemsPerPage = isset($_POST['items_per_page']) ? intval($_POST['items_per_page']) : 30;
    $hypes_filter = isset($_POST['hypes_filter']) ? sanitize_text_field($_POST['hypes_filter']) : 5;
    // 新たに検索キーワードを取得
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    $today = strtotime(date('Y-m-d'));
    $tomorrow = strtotime('+1 day', $today);

    $offset = $itemsPerPage * ($page - 1);
    // get_sort_games() の引数に $search を追加
    $games_to_load = get_sort_games($release_filter, $offset, $itemsPerPage, $platform_filter, $hypes_filter, $search, false);

    $output = '';
    foreach ($games_to_load as $detail_data) {
        $game_id = $detail_data['id'];
        $detail_data_json = htmlspecialchars(json_encode($detail_data), ENT_QUOTES, 'UTF-8');
        $total_rating_display = ($detail_data['total_rating'] != 0) ? sprintf("%.1f", $detail_data['total_rating']) : '-';
        $detail_url = site_url('/game-detail/?id=' . $game_id);
        
        $output .= '<a href="' . esc_url($detail_url) . '" style="text-decoration: none; color: inherit;">';
            $output .= '<div class="igdb-game-card" data-release="' . esc_attr($detail_data['release_timestamp']) . '" data-platforms="' . esc_attr(implode(',', $detail_data['platforms'])) . '" data-rating="' . esc_attr($detail_data['total_rating']) . '" data-rating_count="' . esc_attr($detail_data['total_rating_count']) . '" data-detail=\'' . $detail_data_json . '\' style="border-radius: 15px; margin: 0; padding: 0.4rem; cursor: pointer;">';
                $output .= '<div class="game-slideshow2" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
                    $output .= '<div class="game-slideshow" id="slideshow-' . esc_attr($game_id) . '" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
                        $images = $detail_data['images'];
                        shuffle($images); // 配列内の画像の順番をランダムにする
                        $first = true;
                        foreach ($images as $img_url) {
                            $display = $first ? 'block' : 'none';
                            $output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: ' . $display . ';" />';
                            $first = false;
                        }
                    $output .= '</div>';
                    $output .= '<div class="cover-thumb2" style="position: absolute; top: 50%; left: 0; transform: translate(5%, -50%); width: 30%; height: auto; z-index: 2;">';
                        $output .= '<img src="' . esc_url($detail_data['cover']) . '" alt="' . esc_attr($detail_data['name']) . '" loading="lazy" style="width: 100%; height: auto; object-fit: contain; box-shadow: 0 4px 8px rgba(50,50,50,0.9);">';
                    $output .= '</div>';
                $output .= '</div>';
                $output .= '<div class="game-info-container" style="display: flex; align-items: center; margin-top: 0.2rem;">';
                    $output .= '<div class="cover-thumb" style="width: 120px; flex-shrink: 0;">';
                        $output .= '<img src="' . esc_url($detail_data['cover']) . '" alt="' . esc_attr($detail_data['name']) . '" loading="lazy" style="width: 100%; height: auto; object-fit: contain;">';
                    $output .= '</div>';
                    $output .= '<div class="game-info" style="padding-left: 10px; flex-grow: 1;">';
                        $output .= '<h3 style="margin: 0; font-size: 1.2rem;">' . esc_html($detail_data['name']) . '</h3>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html($detail_data['company']) . '</p>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">注目度: ' . esc_html($detail_data['hypes']) . '</p>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">発売日: ' . esc_html($detail_data['release_date']) . '</p>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html(get_release_status_text($detail_data['release_timestamp'])) . '</p>';
                        if (!empty($detail_data['release_timestamp']) && $detail_data['release_timestamp'] < $tomorrow) {
                            $output .= '<p style="margin: 0; font-size: 1rem;">評価: ' . esc_html($total_rating_display) . '（レビュー数：' . esc_html($detail_data['total_rating_count']) . '）</p>';
                        }
                        if (!empty($detail_data['platforms']) && is_array($detail_data['platforms'])) {
                            $output .= render_platforms_html($detail_data['platforms']);
                        }
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</div>';
        $output .= '</a>';
    }
    
    echo $output;
    wp_die();
}
add_action('wp_ajax_load_more_games', 'load_more_games_ajax_handler');
add_action('wp_ajax_nopriv_load_more_games', 'load_more_games_ajax_handler');
