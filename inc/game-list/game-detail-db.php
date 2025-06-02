<?php
require_once GAME_LIST . '/get-rakuten-games.php';

function get_igdb_game_data_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_detail';
    // テーブル内の全件数を取得
    $query = "SELECT COUNT(*) FROM $table_name";
    $count = $wpdb->get_var($query);
    return intval($count);
}

function create_igdb_game_detail_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_detail';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        game_id BIGINT(20) UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        name_english VARCHAR(255) NOT NULL,
        release_date VARCHAR(50) NOT NULL,
        release_timestamp BIGINT(20) UNSIGNED,
        cover VARCHAR(255) NOT NULL,
        platforms LONGTEXT NOT NULL,
        images_big LONGTEXT NOT NULL,
		images LONGTEXT NOT NULL,
        youtubevideos LONGTEXT NOT NULL,
        steamvideos LONGTEXT NOT NULL,
        language_supports LONGTEXT NOT NULL,
        game_modes LONGTEXT NOT NULL,
        genres LONGTEXT NOT NULL,
        player_perspectives LONGTEXT NOT NULL,
        multiplayer_modes LONGTEXT NOT NULL,
        game_engines LONGTEXT NOT NULL,
        parent_game TINYINT(1) NOT NULL,
        summary LONGTEXT NOT NULL,
        company VARCHAR(255) NOT NULL,
        total_rating FLOAT,
        total_rating_count INT,
        websites LONGTEXT NOT NULL,
        dlc_games LONGTEXT NOT NULL,
        appid INT,
        reviewInfo LONGTEXT NOT NULL,
        pc_requirement LONGTEXT NOT NULL,
        hypes INT,
        age_ratings LONGTEXT NOT NULL,
		commerce LONGTEXT NOT NULL,  -- 追加
        goods LONGTEXT NOT NULL,     -- 追加
        PRIMARY KEY (game_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);
}


function create_igdb_game_platforms_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_platforms';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        game_id BIGINT(20) UNSIGNED NOT NULL,
        platform_name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        INDEX game_idx (game_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}




function get_game_detail_data($game_id, $isDetail = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_detail';

    // $isDetail が false の場合は、必要最小限のカラムのみ取得
    if (!$isDetail) {
        $sql = $wpdb->prepare(
            "SELECT game_id, name, name_english, release_date, release_timestamp, cover, images, total_rating, total_rating_count, company, hypes
             FROM $table_name WHERE game_id = %d",
            $game_id
        );
    } else {
        // 詳細表示用は全カラム取得
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE game_id = %d", $game_id);
    }

    // DBからレコード取得（メモリ節約のため、配列のコピーは最小限にする）
    $record = $wpdb->get_row($sql, ARRAY_A);
    if (!$record) {
        return false;
    }

    // プラットフォーム情報は別テーブルから取得（必要なものだけ取得し、すぐ変数に格納）  
    $platform_rows = $wpdb->get_col(
        $wpdb->prepare("SELECT platform_name FROM {$wpdb->prefix}igdb_game_platforms WHERE game_id = %d", $game_id)
    );
    unset($sql); // 不要になった変数の解放

    if (!$isDetail) {
        // 詳細情報が不要な場合は、必要な情報だけを新たな配列にまとめ、元レコードは解放
        $result = array(
            'id'                 => $record['game_id'],
            'name'               => $record['name'],
            'name_english'       => $record['name_english'],
            'release_date'       => $record['release_date'],
            'release_timestamp'  => $record['release_timestamp'],
            'cover'              => $record['cover'],
            'images'             => json_decode($record['images'], true),
            'total_rating'       => $record['total_rating'],
            'total_rating_count' => $record['total_rating_count'],
            'company'            => $record['company'],
            'hypes'              => $record['hypes'],
            'platforms'          => $platform_rows,
        );
        unset($record); // 使用済みの変数解放
        return $result;
    }

    // 詳細情報の場合、各フィールドを必要に応じてjson_decodeし、元レコードを更新
    $record['platforms'] = $platform_rows;
    unset($platform_rows);
    
    $record['images'] = json_decode($record['images'], true);
    $record['images_big'] = json_decode($record['images_big'], true);
    $record['youtubevideos'] = json_decode($record['youtubevideos'], true);
    $record['steamvideos'] = json_decode($record['steamvideos'], true);
    $record['language_supports'] = json_decode($record['language_supports'], true);
    $record['game_modes'] = json_decode($record['game_modes'], true);
    $record['genres'] = json_decode($record['genres'], true);
    $record['player_perspectives'] = json_decode($record['player_perspectives'], true);
    $record['multiplayer_modes'] = json_decode($record['multiplayer_modes'], true);
    $record['game_engines'] = json_decode($record['game_engines'], true);
    $record['websites'] = json_decode($record['websites'], true);
    $record['dlc_games'] = json_decode($record['dlc_games'], true);
    $record['reviewInfo'] = json_decode($record['reviewInfo'], true);
    $record['age_ratings'] = json_decode($record['age_ratings'], true);
    $record['pc_requirement'] = json_decode($record['pc_requirement'], true);
    $record['commerce'] = json_decode($record['commerce'], true);
    $record['goods'] = json_decode($record['goods'], true);

    return $record;
}



function set_game_detail_data($games) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_detail';
    $platforms_table = $wpdb->prefix . 'igdb_game_platforms';

    

    // 10件ずつに分割して処理
    $game_chunks = array_chunk($games, 10);
    foreach ($game_chunks as $chunk) {
		
		// トランザクションを開始
   	 	$wpdb->query('START TRANSACTION');
		
        foreach ($chunk as $game) {
            if (!isset($game['id']) || intval($game['id']) <= 0) {
                continue;
            }
            $game_id = intval($game['id']);

            // ----- ゲーム基本情報の整形 -----
            $name = !empty($game['name']) ? esc_html($game['name']) : '不明';
            $name_english = $name;
            $localname = false;
            if (!empty($game['game_localizations']) && is_array($game['game_localizations'])) {
                foreach ($game['game_localizations'] as $localization) {
                    if (isset($localization['region']) && $localization['region'] === 3 && !empty($localization['name'])) {
                        $name = esc_html($localization['name']);
                        $localname = true;
                        break;
                    }
                }
            }

            // 開発元情報の取得
            $company = '';
            if (!empty($game['involved_companies']) && is_array($game['involved_companies'])) {
                foreach ($game['involved_companies'] as $invCompany) {
                    if (!empty($invCompany['company']['name'])) {
                        $company = esc_html($invCompany['company']['name']);
                        break;
                    }
                }
            }

            // 評価情報、発売日の計算など（必要な部分だけ）
            $total_rating = isset($game['total_rating']) ? $game['total_rating'] : 0;
            $total_rating_count = isset($game['total_rating_count']) ? $game['total_rating_count'] : 0;
            $release_timestamp = !empty($game['first_release_date']) ? intval($game['first_release_date']) : false;
            if (!empty($game['release_dates']) && is_array($game['release_dates'])) {
                foreach ($game['release_dates'] as $releasedate) {
                    if (isset($releasedate['release_region']) && $releasedate['release_region'] == 3 && !empty($releasedate['date'])) {
                        $release_timestamp = intval($releasedate['date']);
                        break;
                    }
                }
            }
            $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
            $release_date = $release_timestamp ? date('Y年n月j日', $release_timestamp) . '（' . $weekDays[date('w', $release_timestamp)] . '）' : '未定';

            // 対応プラットフォームの整形
            $platforms_array = [];
            if (!empty($game['platforms']) && is_array($game['platforms'])) {
                foreach ($game['platforms'] as $platform) {
                    if (!empty($platform['abbreviation'])) {
                        $platforms_array[] = esc_html($platform['abbreviation']);
                    }
                }
            }

            // 画像やカバー情報、ウェブサイト情報など必要な部分だけ処理
            $images = []; $images_big = [];
            if (!empty($game['screenshots']) && is_array($game['screenshots'])) {
                foreach ($game['screenshots'] as $screenshot) {
                    if (!empty($screenshot['image_id'])) {
                        $images[] = 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/' . $screenshot['image_id'] . '.jpg';
                        $images_big[] = 'https://images.igdb.com/igdb/image/upload/t_screenshot_big/' . $screenshot['image_id'] . '.jpg';
                    }
                }
            }
            if (empty($images)) {
                $images[] = 'https://via.placeholder.com/600x400?text=No+Image';
            }
            if (empty($images_big)) {
                $images_big[] = 'https://via.placeholder.com/600x400?text=No+Image';
            }
            $cover_url = 'https://via.placeholder.com/120x180?text=No+Cover';
            if (!empty($game['cover']) && is_array($game['cover']) && !empty($game['cover']['image_id'])) {
                $cover_url = 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $game['cover']['image_id'] . '.jpg';
            }

            // ウェブサイト情報の整形（必要な部分だけ）
            $ordered_websites = null;
            if (isset($game['websites'])) {
                $ordered_websites = [1 => null, 5 => null, 9 => null, 10 => null, 11 => null, 12 => null, 16 => null];
                $website_datas = get_website_data($game['websites']);
                if (is_array($website_datas)) {
                    foreach ($website_datas as $website_data) {
                        if (isset($website_data['type'], $website_data['url'])) {
                            $type = $website_data['type'];
                            if (array_key_exists($type, $ordered_websites) && $ordered_websites[$type] === null) {
                                $ordered_websites[$type] = $website_data;
                            }
                        }
                    }
                }
            }

            // DLC情報やSteam情報の取得（各自実装済みの関数を利用）
            $dlc_games = get_dlc_game_data($game_id);
            $appId = get_steam_uid_from_igdb($game_id);
			
			
			//楽天ブックスAPI
			$commerceData = getRakutenCommerce($name, $platforms_array);
			if ($commerceData !== false) {
				print_r($commerceData);
			} else {
				$commerceData = array();
			}
			error_log(print_r($game_id, true));
			error_log(print_r($commerceData, true));
			
// 			$commerceData = array();
			
            // ここまでで detail 配列を組み立てる
            $detail = [
                'id'                => $game_id,
                'name'              => $name,
                'name_english'      => $name_english,
                'release_date'      => $release_date,
                'release_timestamp' => $release_timestamp,
                'cover'             => $cover_url,
                'platforms'         => $platforms_array,
                'images_big'        => $images_big,
                'images'            => $images,
                'youtubevideos'     => isset($game['videos']) ? $game['videos'] : [],
                'steamvideos'       => [],
                'language_supports' => isset($game['language_supports']) ? $game['language_supports'] : [],
                'game_modes'        => isset($game['game_modes']) ? $game['game_modes'] : [],
                'genres'            => isset($game['genres']) ? $game['genres'] : [],
                'player_perspectives'=> isset($game['player_perspectives']) ? $game['player_perspectives'] : [],
                'multiplayer_modes' => isset($game['multiplayer_modes']) ? $game['multiplayer_modes'] : [],
                'game_engines'      => isset($game['game_engines']) ? $game['game_engines'] : [],
                'parent_game'       => isset($game['parent_game']) ? $game['parent_game'] : false,
                'summary'           => '',
                'company'           => $company,
                'total_rating'      => $total_rating,
                'total_rating_count'=> $total_rating_count,
                'websites'          => $ordered_websites,
                'dlc_games'         => $dlc_games,
                'appid'             => $appId,
                'reviewInfo'        => false,
                'pc_requirement'    => '',
                'hypes'             => isset($game['hypes']) ? $game['hypes'] : false,
                'age_ratings'       => isset($game['age_ratings']) ? $game['age_ratings'] : [],
                'commerce'          => $commerceData,
                'goods'             => array(),
            ];

            // 追加情報のマージ（必要に応じて軽量化する処理に変更も検討）
            $detail = merge_game_extra_info($detail, $game_id);

            // Steam追加情報の取得
            if ($detail['appid'] !== false) {
                $steamDetailData = fetchSteamData($detail['appid']);
                $description = getJapaneseGameDescription($steamDetailData);
                if ($description !== false) {
                    $description = replaceHeadings($description);
                    $detail['summary'] = $description;
                }
                $media = getGameMedia($steamDetailData);
                if (!empty($media['images'])) {
                    $detail['images_big'] = array_merge($detail['images_big'], $media['images']);
                }
                if (!empty($media['videos'])) {
                    $detail['steamvideos'] = $media['videos'];
                }
                $reviewInfo = getReviewInformation($detail['appid']);
                $detail['reviewInfo'] = $reviewInfo ? $reviewInfo : false;
                if (!$localname) {
                    $title = getGameTitleJapanese($steamDetailData);
                    if (!empty($title)) {
                        $detail['name'] = $title;
                    }
                }
                $pc_requirement = getSteamPcRequirements($steamDetailData);
                $detail['pc_requirement'] = !empty($pc_requirement) ? $pc_requirement : '';
            }

            // --- プラットフォーム情報の更新 ---
            $current_platforms = $wpdb->get_col($wpdb->prepare(
                "SELECT platform_name FROM {$platforms_table} WHERE game_id = %d",
                $game_id
            ));
            if (array_map('strtolower', $current_platforms) !== array_map('strtolower', $platforms_array)) {
                // 古いプラットフォーム情報を削除
                $wpdb->delete($platforms_table, array('game_id' => $game_id));
                if (!empty($platforms_array)) {
                    // 新しいプラットフォーム情報を挿入
                    foreach ($platforms_array as $platform_name) {
                        $wpdb->insert(
                            $platforms_table,
                            array(
                                'game_id' => $game_id,
                                'platform_name' => $platform_name
                            ),
                            array('%d', '%s')
                        );
                    }
                }
            }

            // メインテーブルへの UPSERT 処理（ON DUPLICATE KEY UPDATE）
            $data = array(
                'game_id'             => $game_id,
                'name'                => $detail['name'],
                'name_english'        => $detail['name_english'],
                'release_date'        => $detail['release_date'],
                'release_timestamp'   => $detail['release_timestamp'],
                'cover'               => $detail['cover'],
                'platforms'           => wp_json_encode($platforms_array),
                'images_big'          => wp_json_encode($detail['images_big']),
                'images'              => wp_json_encode($detail['images']),
                'youtubevideos'       => wp_json_encode($detail['youtubevideos']),
                'steamvideos'         => wp_json_encode($detail['steamvideos']),
                'language_supports'   => wp_json_encode($detail['language_supports']),
                'game_modes'          => wp_json_encode($detail['game_modes']),
                'genres'              => wp_json_encode($detail['genres']),
                'player_perspectives' => wp_json_encode($detail['player_perspectives']),
                'multiplayer_modes'   => wp_json_encode($detail['multiplayer_modes']),
                'game_engines'        => wp_json_encode($detail['game_engines']),
                'parent_game'         => is_bool($detail['parent_game']) ? ($detail['parent_game'] ? 1 : 0) : $detail['parent_game'],
                'summary'             => $detail['summary'],
                'company'             => $detail['company'],
                'total_rating'        => $detail['total_rating'],
                'total_rating_count'  => $detail['total_rating_count'],
                'websites'            => wp_json_encode($detail['websites']),
                'dlc_games'           => wp_json_encode($detail['dlc_games']),
                'appid'               => $detail['appid'],
                'reviewInfo'          => wp_json_encode($detail['reviewInfo']),
                'pc_requirement'      => wp_json_encode($detail['pc_requirement']),
                'hypes'               => $detail['hypes'],
                'age_ratings'         => wp_json_encode($detail['age_ratings']),
                'commerce'            => wp_json_encode($detail['commerce']),
                'goods'               => wp_json_encode($detail['goods']),
            );

            // UPSERTクエリの構築
            $columns = array_keys($data);
            $placeholders = array();
            foreach ($data as $value) {
                $placeholders[] = (is_int($value) || is_float($value)) ? '%d' : '%s';
            }

            $sql = "INSERT INTO $table_name (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")
                    ON DUPLICATE KEY UPDATE ";
            $update_parts = [];
            foreach ($columns as $column) {
                if ($column === 'game_id') {
                    continue;
                }
                $update_parts[] = "$column = IF(
    (VALUES($column) = '' OR VALUES($column) = '[]' OR VALUES($column) = 'false' OR VALUES($column) IS NULL)
    AND $column <> '',
    $column,
    VALUES($column)
)";

            }
            $sql .= implode(', ', $update_parts);
            $values = array_values($data);
			$wpdb->query($wpdb->prepare($sql, ...$values));
			
			
			// ---------- 固定ページ（またはカスタム投稿）のメタデータ更新 ----------
            // 各ゲームに対応する投稿を、meta key 'game_id' で検索し、SNS用メタ情報を更新する
//             update_game_meta_data($game_id, $detail);

            // メモリ解放
            unset($game, $game_id, $name, $name_english, $company, $release_timestamp, $release_date, 
                  $platforms_array, $images, $images_big, $cover_url, $ordered_websites, $dlc_games, 
                  $appId, $detail, $data);
        }
        // チャンクごとにメモリ解放
        unset($chunk);
		   // トランザクションのコミット
    $wpdb->query('COMMIT');
    }

 
}

/**
 * 指定したゲームIDに対応する投稿（カスタム投稿タイプ "game"）のSNS用メタデータを更新する
 *
 * @param int   $game_id ゲームID
 * @param array $detail  ゲーム詳細情報配列
 */
// function update_game_meta_data($game_id, $detail) {
//     // ゲーム投稿を meta key 'game_id' で検索
//     $game_posts = get_posts(array(
//         'post_type'   => 'game',
//         'meta_key'    => 'game_id',
//         'meta_value'  => $game_id,
//         'numberposts' => 1,
//     ));
//     if (!$game_posts) {
//         return;
//     }
//     $post_id = $game_posts[0]->ID;

//     // og:title の設定
//     $og_title = $detail['name'];

//     // og:image の設定：images_big 配列から、一定期間ごとのシード値で画像を選択
//     if (!empty($detail['images_big']) && is_array($detail['images_big'])) {
    
//         $seed = floor(time());
//         $index = $seed % count($detail['images_big']);
//         $og_image = esc_url($detail['images_big'][$index]);
//     } else {
//         $og_image = '';
//     }

//     // og:url の設定：サイトURLに固定ページパスとパラメータとしてゲーム名を付与
//     $og_url = esc_url( site_url('/game-detail/') . '?id=' . urlencode($detail['game_id']) );

//     // og:description の設定：summary から HTML タグを除去して出力
//     $og_description = !empty($detail['summary']) ? esc_attr(strip_tags($detail['summary'])) : '';

//     // Twitter用の設定
//     $twitter_card = 'summary_large_image';
//     $twitter_title = $og_title;
//     $twitter_image = $og_image;

//     // 各 meta フィールドを更新
//     update_post_meta($post_id, 'og_title', $og_title);
//     update_post_meta($post_id, 'og_image', $og_image);
//     update_post_meta($post_id, 'og_url', $og_url);
//     update_post_meta($post_id, 'og_description', $og_description);
//     update_post_meta($post_id, 'twitter_card', $twitter_card);
//     update_post_meta($post_id, 'twitter_title', $twitter_title);
//     update_post_meta($post_id, 'twitter_image', $twitter_image);
// }



function get_sort_games($sort_filter = 'default', $offset = 0, $limit = 50, $platform_filter = 'all', $hypes_filter = null, $search = '', $is_random = false) {
    global $wpdb;
    $detail_table = $wpdb->prefix . 'igdb_game_detail';
    $platform_table = $wpdb->prefix . 'igdb_game_platforms';

    $where_clauses = [];
    $order_clause = '';
    $join_sql = '';

    $today = strtotime(date('Y-m-d'));
    $tomorrow = strtotime('+1 day', $today);

    // --- ソート条件（通常フィルター） ---
    if (!$is_random) {
        switch ($sort_filter) {
            case 'rating':
            case 'rating_count':
            case 'released':
                $where_clauses[] = "release_timestamp IS NOT NULL AND release_timestamp < {$tomorrow}";
                break;
            case 'upcoming':
                $where_clauses[] = "release_timestamp >= {$tomorrow}";
                break;
        }
    }

    // --- hypes フィルター ---
    if (!empty($hypes_filter)) {
        $where_clauses[] = $wpdb->prepare("hypes >= %d", $hypes_filter);
    }

    // --- プラットフォームフィルター（JOIN利用） ---
    if ($platform_filter !== 'all') {
        $join_sql = "INNER JOIN {$platform_table} p ON d.game_id = p.game_id";
        $where_clauses[] = $wpdb->prepare("p.platform_name = %s", $platform_filter);
    }

    // --- 検索条件（$search が指定されている場合） ---
    $search_sql = '';
if (!empty($search)) {
    // LIKE検索用に準備（エスケープ + ワイルドカード付き）
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $like_values = array_fill(0, 11, $search_like);

    // relevanceスコア（重み付け）
    $relevance_parts = [
        "(CASE WHEN d.name LIKE %s THEN 5 ELSE 0 END)",
        "(CASE WHEN d.game_id LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.name_english LIKE %s THEN 3 ELSE 0 END)",
        "(CASE WHEN d.platforms LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.game_modes LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.genres LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.player_perspectives LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.game_engines LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.summary LIKE %s THEN 1 ELSE 0 END)",
        "(CASE WHEN d.company LIKE %s THEN 2 ELSE 0 END)",
        "(CASE WHEN d.pc_requirement LIKE %s THEN 1 ELSE 0 END)",
    ];
    $relevance_sql = "(" . implode(" + ", $relevance_parts) . ") AS relevance";
    $relevance_sql = $wpdb->prepare($relevance_sql, ...$like_values);

    // WHERE句も同様にprepareで安全に
    $where_clauses[] = $wpdb->prepare(
        "(" .
        "d.name LIKE %s OR " .
        "d.game_id LIKE %s OR " .
        "d.name_english LIKE %s OR " .
        "d.platforms LIKE %s OR " .
        "d.game_modes LIKE %s OR " .
        "d.genres LIKE %s OR " .
        "d.player_perspectives LIKE %s OR " .
        "d.game_engines LIKE %s OR " .
        "d.summary LIKE %s OR " .
        "d.company LIKE %s OR " .
        "d.pc_requirement LIKE %s" .
        ")",
        ...$like_values
    );
}



    // --- 並び順の設定 ---
    if (!empty($search)) {
        // 検索の場合は relevance の降順でソート
        $order_clause = "ORDER BY relevance DESC";
    } elseif ($is_random) {
        $order_clause = "ORDER BY RAND()";
    } else {
        switch ($sort_filter) {
            case 'rating':
                $order_clause = "ORDER BY total_rating DESC";
                break;
            case 'rating_count':
                $order_clause = "ORDER BY total_rating_count DESC";
                break;
            case 'upcoming':
                $order_clause = "ORDER BY release_timestamp ASC";
                break;
            case 'released':
                $order_clause = "ORDER BY release_timestamp DESC";
                break;
            default:
                $order_clause = "ORDER BY d.game_id DESC";
                break;
        }
    }

    // --- WHERE句の組み立て ---
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // --- SELECT文の組み立て ---
    // 検索がある場合、$relevance_sql を SELECT に追加
    if (!empty($search)) {
        $select_clause = "SELECT DISTINCT d.*, $relevance_sql";
    } else {
        $select_clause = "SELECT DISTINCT d.*";
    }

    if ($is_random) {
        $sql = "
            $select_clause
            FROM {$detail_table} d
            $join_sql
            $where_sql
            $order_clause
            LIMIT %d
        ";
        $prepared_sql = $wpdb->prepare($sql, $limit);
    } else {
        $sql = "
            $select_clause
            FROM {$detail_table} d
            $join_sql
            $where_sql
            $order_clause
            LIMIT %d OFFSET %d
        ";
        $prepared_sql = $wpdb->prepare($sql, $limit, $offset);
    }

    $raw_games = $wpdb->get_results($prepared_sql, ARRAY_A);

    $formatted_games = array();

    // チャンク処理（例：10件ずつ）
    $chunks = array_chunk($raw_games, 10);
    foreach ($chunks as $chunk) {
        foreach ($chunk as $raw_game) {
            // get_game_detail_data() は詳細データ取得用（一覧表示は簡易版）
            $game_detail = get_game_detail_data($raw_game['game_id'], false);
            if ($game_detail) {
                // 検索の場合、関連度（relevance）も付与（キャストして数値に）
                if (!empty($raw_game['relevance'])) {
                    $game_detail['relevance'] = (int) $raw_game['relevance'];
                }
                $formatted_games[] = $game_detail;
            }
        }
        unset($chunk);
    }
    return $formatted_games;
}




