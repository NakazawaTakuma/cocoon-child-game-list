<?php
/**
 * 指定した接頭辞を持つすべてのオプションを削除する関数
 *
 * @param array $prefixes 削除対象のオプション名の接頭辞の配列
 */
function delete_options_by_prefix( $prefixes = array() ) {
    global $wpdb;
    
    if ( empty( $prefixes ) || ! is_array( $prefixes ) ) {
        return;
    }
    
    foreach ( $prefixes as $prefix ) {
        // プレースホルダにより安全にLIKE句を作成
        $like = $wpdb->esc_like( $prefix ) . '%';
        // 接頭辞で始まるオプション名をすべて取得
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                $like
            )
        );
        if ( ! empty( $option_names ) ) {
            foreach ( $option_names as $option_name ) {
                delete_option( $option_name );
            }
        }
    }
}
// 	delete_options_by_prefix( array( 'similar_games_', 'similarity_' ) );
//    	delete_options_by_prefix( array( 'similar_games_') );





function create_game_recommendations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'game_recommendations';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        game_id BIGINT(20) UNSIGNED NOT NULL,
        recommended_game_id BIGINT(20) UNSIGNED NOT NULL,
        similarity FLOAT NOT NULL,
        PRIMARY KEY (game_id, recommended_game_id),
        INDEX similarity_idx (game_id, similarity)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// 		delete_options_by_prefix( array( 'similar_games_', 'similarity_' ) );
//    	delete_options_by_prefix( array( 'similar_games_') );


function get_recommended_games($game_id) {

    global $wpdb;
    $table = $wpdb->prefix . 'game_recommendations';
    
    // 指定したゲームの推薦情報を類似度順に取得
    $sql = $wpdb->prepare("SELECT recommended_game_id, similarity FROM $table WHERE game_id = %d ORDER BY similarity DESC", $game_id);
    $rows = $wpdb->get_results($sql, ARRAY_A);
    
    $result = array();
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $rec_id = intval($row['recommended_game_id']);
            $detail = get_game_detail_data($rec_id, false);
            if ($detail) {
                $detail['similarity'] = $row['similarity'];
                $result[] = $detail;
            }
        }
    }
    return $result;
}



function set_recommended_games() {
    global $wpdb;
    $table = $wpdb->prefix . 'game_recommendations';

// 処理済みのペアを保持する静的配列
static $processed_pairs = array();

foreach (get_all_game_ids_generator(50) as $game_id) {
    $detail = get_game_detail_data($game_id, false);
    if (empty($detail)) {
        continue;
    }

    $similarities = [];

    foreach (get_all_game_ids_generator(50) as $other_game_id) {
        if ($other_game_id === $game_id) continue;

        $pair_key = $game_id < $other_game_id ? $game_id . '_' . $other_game_id : $other_game_id . '_' . $game_id;

        if (isset($processed_pairs[$pair_key])) {
            $sim = $processed_pairs[$pair_key];
            $other_detail = get_game_detail_data($other_game_id, false);
            if (empty($other_detail)) continue;
        } else {
            $other_detail = get_game_detail_data($other_game_id, false);
            if (empty($other_detail)) continue;

            // ここから各種似ているかの計算
            $name_similarity = 0.0;
            if (!empty($detail['name_english']) && !empty($other_detail['name_english'])) {
                $name_similarity = calculateNameSimilarity($detail['name_english'], $other_detail['name_english']);
            }

            $company_similarity = 0.0;
            if (!empty($detail['company']) && !empty($other_detail['company']) && $detail['company'] === $other_detail['company']) {
                $company_similarity = 1.0;
            }

            $platforms_similarity = calculate_similarity($detail['platforms'], $other_detail['platforms']);
            
            $genres_similarity = 0.0;
            if (!empty($detail['genres']) && !empty($other_detail['genres'])) {
                $genres_similarity = calculate_name_similarity($detail['genres'], $other_detail['genres']);
            }

            $game_modes_similarity = 0.0;
            if (!empty($detail['game_mode']) && !empty($other_detail['game_mode'])) {
                $game_modes_similarity = calculate_name_similarity($detail['game_modes'], $other_detail['game_modes']);
            }

            $player_perspectives_similarity = 0.0;
            if (!empty($detail['player_perspectives']) && !empty($other_detail['player_perspectives'])) {
                $player_perspectives_similarity = calculate_name_similarity($detail['player_perspectives'], $other_detail['player_perspectives']);
            }

            $release_timestamp_similarity = 0.0;
            if (!empty($detail['release_timestamp']) && !empty($other_detail['release_timestamp'])) {
                $diff = abs($detail['release_timestamp'] - $other_detail['release_timestamp']);
                $threshold = 365 * 24 * 3600; // 1年分の秒数
                $release_timestamp_similarity = max(0, 1 - ($diff / $threshold));
            }
            
            // 総合類似度の計算（重み付けは調整可能）
            $sim = $name_similarity * 10.0 +
                   $company_similarity * 5.0 +
                   $platforms_similarity * 2.5 +
                   $genres_similarity * 2.0 +
                   $game_modes_similarity * 1.0 +
                   $player_perspectives_similarity * 1.0 +
                   $release_timestamp_similarity * 3.5;
            
            // 処理済みペアとして記録
            $processed_pairs[$pair_key] = $sim;

            unset($other_detail);  // 変数解放
        }

        // 注目度（hypes）の補正
        $hypes_similarity = (!empty($other_detail['hypes'])) 
                            ? min(1.0, $other_detail['hypes'] / 100.0)
                            : 0.0;
        $sim += $hypes_similarity * 3.0;

        $similarities[] = array(
            'id' => $other_game_id,
            'similarity' => $sim,
        );
		unset($other_detail);
    } // 内側ループ終了

    // ソートと推薦数の制限
    usort($similarities, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    $similarities = array_slice($similarities, 0, 500);

    // 推薦レコードのDB登録前に既存データを削除
    $wpdb->delete($table, array('game_id' => $game_id));

    // 各推薦候補をDBに挿入
    if (!empty($similarities)) {
        foreach ($similarities as $rec) {
            $wpdb->insert(
                $table,
                array(
                    'game_id'             => $game_id,
                    'recommended_game_id' => $rec['id'],
                    'similarity'          => $rec['similarity'],
                ),
                array('%d', '%d', '%f')
            );
        }
    }

    // ループごとに不要変数を解放
    unset($similarities, $detail);
}

}








/**
 * 全ゲームのIDをチャンク単位で返すジェネレータ関数
 *
 * @param int $chunk_size 一度に取得する件数（デフォルト100）
 * @return Generator 各ゲームの game_id を順次返す
 */
function get_all_game_ids_generator($chunk_size = 100) {
    global $wpdb;
    $detail_table = $wpdb->prefix . 'igdb_game_detail';
    $offset = 0;
    while (true) {
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT game_id FROM {$detail_table} LIMIT %d OFFSET %d", $chunk_size, $offset),
            ARRAY_A
        );
        if (empty($results)) {
            break;
        }
        foreach ($results as $row) {
            yield intval($row['game_id']);
        }
        $offset += $chunk_size;
    }
}






/**
 * 2つの文字列の類似度を0.0～1.0で数値化する関数
 *
 * @param string $name1 文字列1
 * @param string $name2 文字列2
 * @return float 類似度（0.0～1.0）
 */
function calculateNameSimilarity($name1, $name2) {
    if (empty($name1) || empty($name2)) {
        return 0.0;
    }
    similar_text($name1, $name2, $percent);
    return $percent / 100.0;
}

/**
 * 2つのプラットフォーム配列のJaccard類似度を計算する関数
 *
 * @param array $platforms1 配列1（例：$detail['platforms']）
 * @param array $platforms2 配列2（例：$perdetail['platforms']）
 * @return float 0.0～1.0の類似度。共通要素がなければ0.0を返す。
 */
function calculate_similarity($platforms1, $platforms2) {
    if (empty($platforms1) || empty($platforms2)) {
        return 0.0;
    }
    
    // 共通部分
    $common = array_intersect($platforms1, $platforms2);
    // 和集合（重複除去）
    $union = array_merge($platforms1, $platforms2);
    
    if (count($union) === 0) {
        return 0.0;
    }
    
    return count($common) / count($union);
}




/**
 * 2つのジャンル配列の、各要素の 'name' を比較してJaccard類似度を計算する関数
 *
 * @param array $genres1 例: $detail['genres']（各要素は連想配列で 'name' キーを持つ）
 * @param array $genres2 例: $perdetail['genres']（同上）
 * @return float 0.0～1.0 の類似度。要素がない場合は 0.0 を返す。
 */
function calculate_name_similarity($genres1, $genres2) {
	    // どちらかが空の場合は類似度0.0
    if (empty($names1) || empty($names2)) {
        return 0.0;
    }
	
    // 各配列から 'name' 要素のみ抽出し、正規化（小文字化とtrim）する
    $names1 = array();
    if (!empty($genres1) && is_array($genres1)) {
        foreach ($genres1 as $genre) {
            if (isset($genre['name'])) {
                $names1[] = strtolower(trim($genre['name']));
            }
        }
    }
    
    $names2 = array();
    if (!empty($genres2) && is_array($genres2)) {
        foreach ($genres2 as $genre) {
            if (isset($genre['name'])) {
                $names2[] = strtolower(trim($genre['name']));
            }
        }
    }
    
    
    // 共通部分と和集合を求める
    $common = array_intersect($names1, $names2);
    $union = array_merge($names1, $names2);
    
    if (count($union) === 0) {
        return 0.0;
    }
    
    // Jaccard 係数: 共通部分 / 和集合
    return count($common) / count($union);
}
