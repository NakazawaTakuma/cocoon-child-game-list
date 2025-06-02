<?php
/**
 * recommended_games.php
 *
 * ・初期表示用のおすすめゲームカード（ランダム順・先頭10件）の出力
 * ・シャッフルした順番のゲームID配列を hidden フィールドとして出力
 * ・AJAX ハンドラー：追加読み込み分（10件ずつ）を返す
 */


/**
 * 2つの配列を交互に混ぜ合わせる関数
 *
 * @param array $array1 1つ目の配列
 * @param array $array2 2つ目の配列
 * @param int $count1 交互に追加する際、1つ目の配列から取り出す要素数
 * @param int $count2 交互に追加する際、2つ目の配列から取り出す要素数
 * @return array 混合された配列
 */
function interleave_arrays($array1, $array2, $count1 = 1, $count2 = 1) {
    $result = array();
    $i = 0; // $array1 のインデックス
    $j = 0; // $array2 のインデックス

    while ($i < count($array1) || $j < count($array2)) {
        // $array1から $count1 件追加
        for ($k = 0; $k < $count1 && $i < count($array1); $k++, $i++) {
            $result[] = $array1[$i];
        }
        // $array2から $count2 件追加
        for ($k = 0; $k < $count2 && $j < count($array2); $k++, $j++) {
            $result[] = $array2[$j];
        }
    }

    return $result;
}


// ※ 以下の関数 get_igdb_game_data(), sort_games(), render_platforms_html() は各自実装済みとする。

/**
 * おすすめゲームカード一覧をランダム順に表示する関数
 * 初期表示は先頭10件のみを出力し、並び順（ゲームID配列）を hidden フィールドに出力する
 *
 * @return string HTML出力
 * $release_status = get_release_status_text($detail['release_timestamp']);
 */
function recommended_games($game_id) {
    // ゲームデータ取得＆ソート
//     $games = get_igdb_game_data();
//     $random_games = sort_games($games);

// // 	// 毎回新たにランダムに並び替え
//     if ( is_array($random_games) ) {
//         shuffle($random_games);
//     }

    $random_games = get_sort_games($sort_filter = 'default', $offset = 0, $limit = 500, $platform_filter = 'all', $hypes_filter = 5, $is_random = true);
  	$sorted_games = get_recommended_games($game_id);
// 	$sorted_games = $random_games;
	$sorted_games = interleave_arrays($sorted_games, $random_games, 3, 2);
	
	
    
    // シャッフル後の順番をゲームIDの配列として保存
    $game_order = array();
    foreach ($sorted_games as $game) {
        if ( isset($game['id']) ) {
            $game_order[] = $game['id'];
        }
    }
    
    // 初期表示用：先頭10件
    $initial_games = array_slice($sorted_games, 0, 10);
    
    $output = '';
    $today = strtotime(date('Y-m-d'));
    $tomorrow = strtotime('+1 day', $today);
    
    // ゲームカード一覧コンテナ
    $output .= '<div class="igdb-recommend-game-list" style="display: flex; flex-wrap: wrap; justify-content: center; transform-origin: top center;">';
    foreach ($initial_games as $detail_data) {
        if ( ! isset($detail_data['id']) ) {
            continue;
        }
        $game_id = $detail_data['id'];
        $detail_data_json = htmlspecialchars(json_encode($detail_data), ENT_QUOTES, 'UTF-8');
        $total_rating_display = ($detail_data['total_rating'] != 0) ? sprintf("%.1f", $detail_data['total_rating']) : '-';
        $detail_url = site_url('/game-detail/?id=' . $game_id);
        
        $output .= '<a href="' . esc_url($detail_url) . '" style="text-decoration: none; color: inherit;">';
            $output .= '<div class="igdb-game-card" data-release="' . esc_attr($detail_data['release_timestamp']) . '" data-platforms="' . esc_attr(implode(',', $detail_data['platforms'])) . '" data-rating="' . esc_attr($detail_data['total_rating']) . '" data-rating_count="' . esc_attr($detail_data['total_rating_count']) . '" data-detail=\'' . $detail_data_json . '\' style="border-radius: 15px; margin: 0; padding: 0.4rem; cursor: pointer;">';
                // スライドショーコンテナ
                $output .= '<div class="game-slideshow2" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
                    $output .= '<div class="game-slideshow" id="slideshow-' . esc_attr($game_id) . '" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
//                     $first = true;
//                     if ( isset($detail_data['images']) && is_array($detail_data['images']) ) {
//                         foreach ($detail_data['images'] as $img_url) {
//                             $display = $first ? 'block' : 'none';
//                             $output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: ' . $display . ';" />';
//                             $first = false;
//                         }
//                     }
//                     
                    if ( isset($detail_data['images']) && is_array($detail_data['images']) && !empty($detail_data['images']) ) {
    					// 配列からランダムなキーを取得
						$random_key = array_rand($detail_data['images']);
						$img_url = $detail_data['images'][$random_key];
						$output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: block;" />';
							}

                    $output .= '</div>';
                    // カバー画像のオーバーレイ
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
                        $output .= '<p style="margin: 0; font-size: 1rem;">発売日: ' . esc_html($detail_data['release_date']) . '</p>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html(get_release_status_text($detail_data['release_timestamp'])) . '</p>';
                        if ( isset($detail_data['release_timestamp']) && $detail_data['release_timestamp'] < $tomorrow ) {
                            $output .= '<p style="margin: 0; font-size: 1rem;">評価: ' . esc_html($total_rating_display) . '（レビュー数：' . esc_html($detail_data['total_rating_count']) . '）</p>';
                        }
                        if ( !empty($detail_data['platforms']) && is_array($detail_data['platforms']) ) {
                            $output .= render_platforms_html($detail_data['platforms']);
                        }
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</div>';
        $output .= '</a>';
    }
    $output .= '</div>';
    
    // シャッフル順のゲームID配列を hidden フィールドとして出力
    $output .= '<input type="hidden" id="recommended_game_order" value="' . esc_attr(json_encode($game_order)) . '">';
    
    // インラインCSS（本番では外部CSSへの切り出しを検討）
    $output .= '<style>
        .igdb-game-card:hover { background-color: #ccc; }
    </style>';
    return $output;
}

/**
 * AJAX ハンドラー：追加分のゲームカード HTML を返す
 */
function load_more_recommend_games_ajax_handler() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $itemsPerPage = isset($_POST['items_per_page']) ? intval($_POST['items_per_page']) : 10;
    $order_json = isset($_POST['order']) ? $_POST['order'] : '';

	
	// ページ番号に応じたオフセット
    $offset = ($page - 1) * $itemsPerPage;
    

	$ordered_games = array();
	
    // order パラメーターからシャッフル順の配列を取得
    if ($order_json) {
 		$game_orders = json_decode(stripslashes($order_json), true);
// 		print_r(12142141131231);
// 		print_r($game_orders);

		
		$game_orders = array_slice($game_orders, $offset, $itemsPerPage);

		foreach ($game_orders as  $game_order) {
			// ゲームIDの取得（存在しなければスキップ）
			$game_id = isset($game_order) ? intval($game_order) : false;
			if (!$game_id) {
				continue;
			}
			// 各ゲームの詳細データ（get_game_detail_data() は各自実装済み）
			$detail_data = get_game_detail_data($game_id);
			if (!$detail_data) {
				continue;
			}

			$ordered_games[] = $detail_data;
		}
		
    } else {
        $game_order = array();
    }


    
    $output = '';
    $today = strtotime(date('Y-m-d'));
    $tomorrow = strtotime('+1 day', $today);
    
    foreach ($ordered_games as $detail_data) {
        if (!isset($detail_data['id'])) {
            continue;
        }
        $game_id = $detail_data['id'];
        $detail_data_json = htmlspecialchars(json_encode($detail_data), ENT_QUOTES, 'UTF-8');
        $total_rating_display = ($detail_data['total_rating'] != 0) ? sprintf("%.1f", $detail_data['total_rating']) : '-';
        $detail_url = site_url('/game-detail/?id=' . $game_id);
        
        $output .= '<a href="' . esc_url($detail_url) . '" style="text-decoration: none; color: inherit;">';
            $output .= '<div class="igdb-game-card" data-release="' . esc_attr($detail_data['release_timestamp']) . '" data-platforms="' . esc_attr(implode(',', $detail_data['platforms'])) . '" data-rating="' . esc_attr($detail_data['total_rating']) . '" data-rating_count="' . esc_attr($detail_data['total_rating_count']) . '" data-detail=\'' . $detail_data_json . '\' style="border-radius: 15px; margin: 0; padding: 0.4rem; cursor: pointer;">';
                $output .= '<div class="game-slideshow2" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
                    $output .= '<div class="game-slideshow" id="slideshow-' . esc_attr($game_id) . '" style="position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; border-radius: 15px;">';
//                     $first = true;
//                     if (isset($detail_data['images']) && is_array($detail_data['images'])) {
//                         foreach ($detail_data['images'] as $img_url) {
//                             $display = $first ? 'block' : 'none';
//                             $output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: ' . $display . ';" />';
//                             $first = false;
//                         }
//                     }
						  if ( isset($detail_data['images']) && is_array($detail_data['images']) && !empty($detail_data['images']) ) {
						// 配列からランダムなキーを取得
						$random_key = array_rand($detail_data['images']);
						$img_url = $detail_data['images'][$random_key];
						$output .= '<img src="' . esc_url($img_url) . '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: block;" />';
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
                        $output .= '<p style="margin: 0; font-size: 1rem;">発売日: ' . esc_html($detail_data['release_date']) . '</p>';
                        $output .= '<p style="margin: 0; font-size: 1rem;">' . esc_html(get_release_status_text($detail_data['release_timestamp'])) . '</p>';
                        if (isset($detail_data['release_timestamp']) && $detail_data['release_timestamp'] < $tomorrow) {
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
add_action('wp_ajax_load_more_recommend_games', 'load_more_recommend_games_ajax_handler');
add_action('wp_ajax_nopriv_load_more_recommend_games', 'load_more_recommend_games_ajax_handler');
