<?php
/**
 * Template Name: Game Detail
 * Description: ゲームの詳細情報を API から取得し、固定ページとして表示するテンプレート
 */

// ゲームモードの翻訳マッピング
$gameModeMap = array(
    "Single player" => "シングルプレイヤー",
    "Multiplayer"   => "マルチプレイヤー",
    "Co-operative"  => "協力プレイ",
	"Split screen"   => "分割画面",
	"Massively Multiplayer Online (MMO)"   => "MMO",
	"Battle Royale"   => "バトルロイヤル"
);

function translateGameMode($text) {
    global $gameModeMap;
    return isset($gameModeMap[$text]) ? $gameModeMap[$text] : $text;
}


// ジャンルの翻訳マッピング
$genreMap = array(
    "Platform"            => "プラットフォーム",
    "Role-playing (RPG)"  => "RPG",
    "Simulator"           => "シミュレーション",
    "Adventure"           => "アドベンチャー",
    "Shooter"             => "シューティング",
    "Puzzle"              => "パズル",
	"Indie"              => "インディーズ",
	"Real Time Strategy (RTS)"   => "リアルタイムストラテジー",
	"Strategy"              => "ストラテジー",
	"Hack and slash/Beat 'em up"   => "アクション",
	"Fighting"   => "ファインティング",
	"Tactical"   => "タクティクス",
	"Visual Novel"   => "ノベルゲーム",
	"Point-and-click"  => "ポイント・アンド・クリック",
	"Racing" => "レーシング",
	"Arcade" => "アーケード",
	"Sport" => "スポーツ",
	
);

function translateGenre($text) {
    global $genreMap;
    return isset($genreMap[$text]) ? $genreMap[$text] : $text;
}

// プレイヤー視点の翻訳マッピング
$playerPerspectiveMap = array(
    "Third person" => "三人称",
	"First person" => "一人称",
	"Bird view / Isometric" => "トップダウン",
	"Side view" => "サイドビュー",
	"Virtual Reality" => "バーチャル・リアリティ"
);

function translatePlayerPerspective($text) {
    global $playerPerspectiveMap;
    return isset($playerPerspectiveMap[$text]) ? $playerPerspectiveMap[$text] : $text;
}




// GETパラメーターからゲームIDを取得
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// $detail = null;

// キャッシュ済みのゲームデータを取得（なければAPI呼び出し）
// $games = get_igdb_game_data();

// if ($games && is_array($games) && $game_id > 0) {
// // 	
// 	$detail = get_game_detail_data($games,$game_id,false,true);
// // 	print_r($detail);
// //  	print_r(get_recommended_games($game_id));
// }
$detail = get_game_detail_data($game_id, $isDetail = true);

// ページタイトルを $detail['name'] に変更するフィルター
if ( ! empty($detail['name']) ) {
    add_filter('pre_get_document_title', function($title) use ($detail) {
        return $detail['name'];
    });
}
// site_url('/game-detail/?id=' . $detail['parent_game'])
/**
 * SNS用のメタタグを <head> に出力する関数
 */
/**
 * SNS用のメタタグを <head> に出力する関数
 */
function add_sns_meta_tags() {
    global $detail;
    
    // タイトルの出力
    if ( ! empty($detail['name']) ) {
        echo '<meta property="og:title" content="' . esc_attr($detail['name']) . '">' . "\n";
    }
    
    // 5日ごとに更新される画像のインデックスを算出
    if (!empty($detail['images_big']) && is_array($detail['images_big'])) {
		$period = 1 * 24 * 3600; // 5日間を秒に変換
        $seed = floor(time() / $period); // 現在時刻を5日で割った商
        $index = $seed % count($detail['images_big']); // 画像配列の中からインデックスを決定
        $image = esc_url($detail['images_big'][$index]);
        echo '<meta property="og:image" content="' . $image . '">' . "\n";
    }
    
    // URLの出力
    if ( ! empty($detail['id']) ) {
    echo '<meta property="og:url" content="' . esc_url(get_permalink() . '?id=' . $detail['id']) . '">' . "\n";
	}
    // 説明文の出力（HTMLタグを除去）
    if ( ! empty($detail['summary']) ) {
        echo '<meta property="og:description" content="' . esc_attr(strip_tags($detail['summary'])) . '">' . "\n";
    }
    
    // Twitter Card タグ
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ( ! empty($detail['name']) ) {
        echo '<meta name="twitter:title" content="' . esc_attr($detail['name']) . '">' . "\n";
    }
    if (!empty($detail['images_big']) && is_array($detail['images_big'])) {
        // 同じく5日ごとのシード値を利用して画像を選択
        $period = 1 * 24 * 3600;
        $seed = floor(time() / $period);
        $index = $seed % count($detail['images_big']);
        $image = esc_url($detail['images_big'][$index]);
        echo '<meta name="twitter:image" content="' . $image . '">' . "\n";
    }
}
add_action('wp_head', 'add_sns_meta_tags');


get_header();

$release_status = "";
if ($detail && isset($detail['release_timestamp'])) {
$release_status = get_release_status_text($detail['release_timestamp']);
}
?>

<?php
function display_platform_banners($platforms) {
    // バナー情報ファイルを読み込む
    $platform_info = include( GAME_LIST . '/inf/platform/platform-info.php' );
    // $platform_info が配列でない場合は空文字を返す
    if (!is_array($platform_info)) {
        return '';
    }
    
    if (is_array($platforms)) {
        // プラットフォームの順序と色を取得
        $platformsData = sort_platforms($platforms); // 各要素： array('abbr' => $abbr, 'order' => $order, 'color' => $color)
        
        // HTMLを生成
        $html = '<div id="platform-banners" style="display: flex; flex-direction: column; gap: 20px;">';
        
        foreach ($platformsData as $platformData) {
            // キー 'abbr' の存在確認
            if (!isset($platformData['abbr'])) {
                continue;
            }
            $abbr = $platformData['abbr'];
            $bgColor = isset($platformData['color']) ? $platformData['color'] : '#000';
            
            // プラットフォーム情報の存在確認
            if (!isset($platform_info[$abbr]) || !is_array($platform_info[$abbr])) {
                continue;
            }
            
            foreach ($platform_info[$abbr] as $banner) {
                // 必要なキーの存在チェック
                if (!isset($banner['image'], $banner['text'], $banner['amazon_link'], $banner['rakuten_link'])) {
                    continue;
                }
                
                $html .= '<div style="position: relative; width: 100%; margin-bottom: 10px; height: 96px; background: linear-gradient(to right, ' . esc_attr($bgColor) . ', #000000); box-sizing: border-box; text-align: center;">';
                // 背景画像：右側に配置
                $html .= '<img src="' . esc_url($banner['image']) . '" alt="' . esc_attr($abbr) . '画像" style="position: absolute; top: 0; right: 5%; height: 100%; object-fit: cover; z-index: 1;">';
                // 上のレイヤー：テキストとリンクボタン
                $html .= '<div style="position: relative; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; height: 100%; gap: 3px;">';
                $html .= '<span style="font-size: 2rem; color: #fff; margin-bottom: 0; margin-right: 16px; text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.9); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><strong>' . esc_html($banner['text']) . '</strong></span>';


                $html .= '<div>';
                $html .= '<a href="' . esc_url($banner['amazon_link']) . '" target="_blank" style="display: inline-block; margin: 3px; padding: 8px 16px; background: #ff9900; border-radius: 8px; text-decoration: none; color: #fff; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.4);">Amazon</a>';
                $html .= '<a href="' . esc_url($banner['rakuten_link']) . '" target="_blank" style="display: inline-block; margin: 3px; padding: 8px 16px; background: #BF0000; border-radius: 8px; text-decoration: none; color: #fff; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.4);">楽天</a>';
                $html .= '</div>';
                $html .= '</div>'; // 上のレイヤー終了
                $html .= '</div>'; // バナーコンテナ終了
            }
        }
        
        $html .= '</div>'; // #platform-banners 終了
        return $html;
    }
    return '';
}

?>
	

<div id="game-detail-card" style="background: #fff; border: 2px solid #ccc; border-radius: 10px; padding: 2px; margin: 0px auto; max-width: 900px;">
    <!-- メインゲームの詳細情報 -->
    <div id="detail-content"></div>
    
	


	
	
<?php
	$weekDays = array('日', '月', '火', '水', '木', '金', '土');
	
	
//Steamレビュー数
if (isset($detail['reviewInfo'])) {
    // 各レビュー件数の取得（キー名は実際のデータに合わせてください）
    $review_positive = isset($detail['reviewInfo']['total_positive']) ? intval($detail['reviewInfo']['total_positive']) : 0;
    $review_negative = isset($detail['reviewInfo']['total_negative']) ? intval($detail['reviewInfo']['total_negative']) : 0;
    $total_review    =  $review_positive + $review_negative;

    // 総レビュー数がゼロでなければ、割合を計算
    if ($total_review > 0) {
        $review_pos_percent = round(($review_positive / $total_review) * 100);
        $review_neg_percent = round(($review_negative / $total_review) * 100);
    } else {
        $review_pos_percent = $review_neg_percent = 0;
    }
    
}
	
	
	
	
	
	
	
// ※ $detail['evaluation'] の構造に基づくサンプル処理（Metacritic 部分）
if ( isset($detail['evaluation']['metacritic']) ) {
    $meta = $detail['evaluation']['metacritic'];
    // 批評家レビューの件数
    $meta_positive = isset($meta['metascore']['reviewnum']['positive']) ? intval($meta['metascore']['reviewnum']['positive']) : 0;
    $meta_mixed    = isset($meta['metascore']['reviewnum']['mixed']) ? intval($meta['metascore']['reviewnum']['mixed']) : 0;
    $meta_negative = isset($meta['metascore']['reviewnum']['negative']) ? intval($meta['metascore']['reviewnum']['negative']) : 0;
	// 批評家レビューの場合
	$total_meta = $meta_positive + $meta_mixed + $meta_negative;
	if ($total_meta > 0) {
		// まずは四捨五入で算出
		$meta_pos_percent   = round(($meta_positive / $total_meta) * 100);
		$meta_mixed_percent = round(($meta_mixed / $total_meta) * 100);
		$meta_neg_percent   = round(($meta_negative / $total_meta) * 100);

		// 合計が100になっていない場合は調整
		$sum = $meta_pos_percent + $meta_mixed_percent + $meta_neg_percent;
		if ($sum !== 100) {
			// 調整対象は、最も件数が多い項目に加算／減算
			$max = max($meta_positive, $meta_mixed, $meta_negative);
			if ($max === $meta_positive) {
				$meta_pos_percent += (100 - $sum);
			} elseif ($max === $meta_mixed) {
				$meta_mixed_percent += (100 - $sum);
			} else {
				$meta_neg_percent += (100 - $sum);
			}
		}
	} else {
		$meta_pos_percent = $meta_mixed_percent = $meta_neg_percent = 0;
	}
}

// 同様にユーザーレビューの場合
if ( isset($meta['userscore']) ) {
    $user_positive = isset($meta['userscore']['reviewnum']['positive']) ? intval($meta['userscore']['reviewnum']['positive']) : 0;
    $user_mixed    = isset($meta['userscore']['reviewnum']['mixed']) ? intval($meta['userscore']['reviewnum']['mixed']) : 0;
    $user_negative = isset($meta['userscore']['reviewnum']['negative']) ? intval($meta['userscore']['reviewnum']['negative']) : 0;
	// 同様にユーザーレビューの場合
	$total_user = $user_positive + $user_mixed + $user_negative;
	if ($total_user > 0) {
		$user_pos_percent   = round(($user_positive / $total_user) * 100);
		$user_mixed_percent = round(($user_mixed / $total_user) * 100);
		$user_neg_percent   = round(($user_negative / $total_user) * 100);

		$sum = $user_pos_percent + $user_mixed_percent + $user_neg_percent;
		if ($sum !== 100) {
			$max = max($user_positive, $user_mixed, $user_negative);
			if ($max === $user_positive) {
				$user_pos_percent += (100 - $sum);
			} elseif ($max === $user_mixed) {
				$user_mixed_percent += (100 - $sum);
			} else {
				$user_neg_percent += (100 - $sum);
			}
		}
	} else {
		$user_pos_percent = $user_mixed_percent = $user_neg_percent = 0;
	}
}

// プレイタイムの表示例（メインとメイン+サイド）
$play_time_url = isset($detail['evaluation']['playtime']['url']) ? $detail['evaluation']['playtime']['url'] : 0;
$play_time_date = isset($detail['evaluation']['playtime']['survey_date']) ? $detail['evaluation']['playtime']['survey_date'] : 0;
$main_time = isset($detail['evaluation']['playtime']['main']) ? intval($detail['evaluation']['playtime']['main']) : 0;
$main_sides_time = isset($detail['evaluation']['playtime']['main_sides']) ? intval($detail['evaluation']['playtime']['main_sides']) : 0;
// ※ここでは単純に、メインとメイン+サイドの数値を表示します。
?>
	
	
<style>
.igdb-button {
    display: inline-block;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 30px;
    font-size: 1.5rem;
    font-weight: bold;
    text-align: center;
    white-space: nowrap;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin: 0 5px;
	width : 280px;
	min-width : 100px;
	background-color: #ffffff;
}
.igdb-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
}
</style>
<?php
if ($detail ) {
if (false !== $detail['websites'] || !empty($detail['appid'])) {
    // 固定順序に必要な type をキーとした配列（初期値は null）
    if (false !== $detail['websites'] ) {
    $ordered_websites = $detail['websites'];

    // 固定順序でボタンを表示（順番：1→5→9）
    echo '<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; margin-top: 30px; margin-bottom: 40px;">';
    foreach (array(1, 5, 9, 10, 11, 12, 16) as $type) {
        if (!empty($ordered_websites[$type])) {
            $website_data = $ordered_websites[$type];
            $url = esc_url($website_data['url']);
            // タイプごとのボタン設定
switch ($type) {
                case 1: // 公式サイト
                    $label = '公式サイト';
                    $bg_color = '#FFFFFF';
                    $text_color = '#ffb83c';
                    break;
                case 5: // X（旧Twitter）
                    $label = 'X';
                    $bg_color = '#FFFFFF';
                    $text_color = '#000000';
                    break;
                case 9: // YouTube
                    $label = 'YouTube';
                    $bg_color = '#FFFFFF';
                    $text_color = '#DA1725';
                    break;
				case 10: // iPhone
                    $label = 'iPhone';
                    $bg_color = '#FFFFFF';
                    $text_color = '#808080';
                    break;
				case 11: // iPad
                    $label = 'iPad';
                    $bg_color = '#FFFFFF';
                    $text_color = '#808080';
                    break;
				case 12: // Android
					$label = 'Android';
                    $bg_color = '#FFFFFF';
                    $text_color = '#34A853';
                    break;
				case 16: // Epic Games
                    $label = 'Epic Games';
                    $bg_color = '#FFFFFF';
                    $text_color = '#000000';
                    break;
                default:
                    continue 2;
            }

            // ボタン出力（背景色、境界線、文字色をインラインで設定）
            echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" 
                    class="igdb-button" 
                    style="background-color: ' . $bg_color . '; border: 1.5px solid ' . $text_color . '; color: ' . $text_color . ';">
                    <strong>' . esc_html($label) . '</strong>
                  </a>';
        }
    }
		}
	if (!empty($detail['appid'])) {
		echo '<a href="' . get_steam_game_link($detail['appid']). '" target="_blank" rel="noopener noreferrer" 
                    class="igdb-button" 
                    style="background-color: #FFFFFF; border: 1.5px solid #2a475e; color: #2a475e;">
                    <strong>Steam</strong>
                  </a>';
	}
	            

	
	if (!empty($detail['parent_game'])) {
		$parent_detail = get_game_detail_data($games,$detail['parent_game'],false,true);
		if (!empty($parent_detail)){
			$parent_url = site_url('/game-detail/?id=' . $detail['parent_game']);		
			echo '<a href="' . esc_url($parent_url). '" target="_blank" rel="noopener noreferrer" 
						class="igdb-button" 
						style="background-color: #FFFFFF; border: 1.5px solid #2a475e; color: #2a475e;">
						<strong>本編</strong>
					  </a>';
		}
	}
	
	
	
	
    echo '</div>';
}
}
?>
	

<style>
.custom-sns-share {
  text-align: center;
  margin: 20px 0;
}
.custom-sns-share h4 {
  margin-bottom: 10px;
}
.custom-sns-share .share-btn {
  display: inline-block;
  margin: 5px;
  padding: 10px 15px;
  width: 130px; /* すべてのボタンの幅を統一（必要に応じて調整） */
  text-decoration: none;
  border-radius: 5px;
  text-align: center;
  
  font-weight: bold;
  background-color: #ffffff;
}

/* 各SNSごとの背景色 */
.custom-sns-share .facebook {
  border: 1.5px solid #3b5998;
	color: #3b5998;
}
.custom-sns-share .twitter {
  border: 1.5px solid #000000;
	color: #000000;
}
.custom-sns-share .line {
  border: 1.5px solid #00c300;
	color: #00c300;
}

</style>	
<?php
// 現在のページURLとタイトルを取得（WordPressの関数を利用）
$currentUrl = urlencode(get_permalink() . '?id=' . $game_id);
$pageTitle  = isset($detail['name']) ? $detail['name'] : "";
$release_date  = isset($detail['release_date']) ? $detail['release_date'] : "";	
$release_status  = isset($detail['release_timestamp']) ? get_release_status_text($detail['release_timestamp']) : "";	
?>
<div class="custom-sns-share">
  <h4>シェアする</h4>

	<a href="https://twitter.com/intent/tweet?url=<?php echo $currentUrl; ?>&text=<?php echo urlencode($pageTitle . "\n" . $release_status ."\n発売日: " . $release_date."\n "); ?>" 
	   target="_blank" rel="noopener noreferrer" 
	   class="share-btn twitter">
	  X
	</a>

  <a href="https://social-plugins.line.me/lineit/share?url=<?php echo $currentUrl; ?>" 
     target="_blank" rel="noopener noreferrer" 
     class="share-btn line">
    LINE
  </a>
	
	<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $currentUrl; ?>" 
     target="_blank" rel="noopener noreferrer" 
     class="share-btn facebook">
    Facebook
  </a>
</div>
	

<!-- 評価情報 -->
	<?php if (isset($detail['evaluation']['metacritic']) || (isset( $detail['total_rating_count']) && intval($detail['total_rating_count']) != 0) || isset($detail['evaluation']['playtime'])): ?>
<div id="evaluation" style="margin: 20px 2px;">
  <h2>評価</h2>
	<?php if (isset( $detail['total_rating']) && isset( $detail['total_rating_count']) && intval($detail['total_rating_count']) != 0): ?>
	<div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
	<h3>■<a href="https://www.igdb.com/" target="_blank" style="color: #0073e6; text-decoration: none; font-weight: bold;">IGDB.com</a>の評価</h3>

 <!-- 批評家レビュー（Metacritic） -->
	<div style=" margin-left: 0px;">
	<h4>・批評家レビュー</h4>

  
  <?php
	$totalRatingCount = isset( $detail['total_rating_count']) ? intval($detail['total_rating_count']) : 0;
    // 批評家レビューのスコア（0〜100）を取得
    $igbdScore = isset( $detail['total_rating']) ? $detail['total_rating'] : 0;
    // スコアに応じた背景色の自動判定
    if ($igbdScore >= 75) {
        $igbdColor = '#4CAF50'; // 高得点：緑
    } elseif ($igbdScore >= 50) {
        $igbdColor = '#FFC107'; // 中程度：オレンジ
    } else {
        $igbdColor = '#F44336'; // 低得点：赤
    }
  ?>
  <div style="display: flex; align-items: center; max-width: 600px;">
    <!-- 左側：スコアの円形表示 -->
    <div style="width: 65px; height: 65px; border-radius: 50%; background: <?php echo $igbdColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-right: 15px;">
      <?php echo number_format($igbdScore, 1); ?>
    </div>
	  
<p style="font-size: 1.1rem;">総件数：<?php echo $totalRatingCount; ?>件</p>
  </div>


	</div>
		</div>
	<?php endif; ?>	
	
	
	


<!-- 	Steam -->
<?php if (isset($detail['appid']) &&isset($total_review) && isset($detail['reviewInfo']['review_score']) && intval($total_review) != 0): ?>
	<div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
	<h3>■<a href="<?php echo get_steam_game_link($detail['appid']); ?>" target="_blank" style="color: #0073e6; text-decoration: none; font-weight: bold;">Steam</a>の評価</h3>

 <!-- ユーザーレビュー -->
	<div style=" margin-left: 0px;">
	<h4>・ユーザーレビュー</h4>

  <?php
	$totalRatingCount = isset( $total_review) ? intval($total_review) : 0;
    // ユーザーレビューのスコア（0〜10）を取得
    $steamScore = isset( $detail['reviewInfo']['review_score']) ? $detail['reviewInfo']['review_score']* 10 : 0;
    // スコアに応じた背景色の自動判定
    if ($steamScore >= 75) {
        $igbdColor = '#4CAF50'; // 高得点：緑
    } elseif ($steamScore >= 50) {
        $igbdColor = '#FFC107'; // 中程度：オレンジ
    } else {
        $igbdColor = '#F44336'; // 低得点：赤
    }
  ?>	
	  <div style="display: flex; align-items: center; max-width: 600px;">
    <!-- 左側：ユーザースコアの円形表示 -->
    <div style="width: 65px; height: 65px; border-radius: 50%; background: <?php echo $igbdColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-right: 15px;">
      <?php echo  $steamScore; ?>
    </div>
    <!-- 右側：横棒グラフと内訳 -->
    <div style="flex: 1;">
		<p style="font-size: 1.1rem;">総件数：<?php echo $total_review; ?>件</p>
      <div style="width: 100%;  display: flex;">
        <div style="background: #4CAF50; width: <?php echo $review_pos_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
        <div style="background: #F44336; width: <?php echo $review_neg_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
      </div>
      <p style="font-size: 0.9rem; margin: 5px 0 0;">
        好評：<?php echo $review_positive; ?>件 (<?php echo $review_pos_percent; ?>%)　
        不評：<?php echo $review_negative; ?>件 (<?php echo $review_neg_percent ; ?>%)
      </p>
    </div>
  </div>
	</div>
	</div>

	<?php endif; ?>	
	
<!-- 	    $review_positive = isset($detail['reviewInfo']['total_positive']) ? intval($detail['reviewInfo']['total_positive']) : 0;
    $review_negative = isset($detail['reviewInfo']['total_negative']) ? intval($detail['reviewInfo']['total_negative']) : 0;
    $total_review    =  $review_positive + $review_negative; -->
	
	
	
<?php if (isset($detail['evaluation']['metacritic'])): ?>
	<div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
<h3>■<a href="<?php echo esc_url($meta['url']); ?>" target="_blank" style="color: #0073e6; text-decoration: none; font-weight: bold;">Metacritic</a>の評価</h3>
<?php if ($total_meta > 0): ?>
 <!-- 批評家レビュー（Metacritic） -->
		<div style=" margin-left: 0px;">
	<h4>・批評家レビュー</h4>


  <?php
    // 批評家レビューのスコア（0〜100）を取得
    $criticScore = isset($meta['metascore']['score']) ? intval($meta['metascore']['score']) : 0;
    // スコアに応じた背景色の自動判定
    if ($criticScore >= 75) {
        $criticColor = '#4CAF50'; // 高得点：緑
    } elseif ($criticScore >= 50) {
        $criticColor = '#FFC107'; // 中程度：オレンジ
    } else {
        $criticColor = '#F44336'; // 低得点：赤
    }
  ?>
			
			
  <div style="display: flex; align-items: center; max-width: 600px;">
    <!-- 左側：スコアの円形表示 -->
    <div style="width: 65px; height: 65px; border-radius: 50%; background: <?php echo $criticColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-right: 15px;">
      <?php echo $criticScore; ?>
    </div>
	  
    <!-- 右側：横棒グラフと内訳 -->
    <div style="flex: 1;">
		<p style="font-size: 1.1rem;">総件数：<?php echo $total_meta; ?>件</p>
      <div style="width: 100%;  display: flex;">
        <div style="background: #4CAF50; width: <?php echo $meta_pos_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
        <div style="background: #FFC107; width: <?php echo $meta_mixed_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
        <div style="background: #F44336; width: <?php echo $meta_neg_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
      </div>
      <p style="font-size: 0.9rem; margin: 5px 0 0;">
        好評：<?php echo $meta_positive; ?>件 (<?php echo $meta_pos_percent; ?>%)　
        賛否両論：<?php echo $meta_mixed; ?>件 (<?php echo $meta_mixed_percent; ?>%)　
        不評：<?php echo $meta_negative; ?>件 (<?php echo $meta_neg_percent; ?>%)
      </p>
    </div>
  </div>

<?php endif; ?>
</div>
<!-- ユーザーレビュー（Metacritic） -->
		<?php if ($total_user > 0): ?>
		<div style=" margin-left: 0px;">
<h4>・ユーザーレビュー</h4>

  
  <?php
 // ユーザーレビューのスコア（0〜100）を取得
    $userScore = isset($meta['userscore']['score']) ? intval($meta['userscore']['score']* 10) : 0;
    // スコアに応じた背景色の自動判定（最大100点）
    if ($userScore >= 75) {
        $userColor = '#4CAF50';
    } elseif ($userScore >= 50) {
        $userColor = '#FFC107';
    } else {
        $userColor = '#F44336';
    }
  ?>
  <div style="display: flex; align-items: center; max-width: 600px;">
    <!-- 左側：ユーザースコアの円形表示 -->
    <div style="width: 65px; height: 65px; border-radius: 50%; background: <?php echo $userColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-right: 15px;">
      <?php echo $userScore; ?>
    </div>
    <!-- 右側：横棒グラフと内訳 -->
    <div style="flex: 1;">
		<p style="font-size: 1.1rem;">総件数：<?php echo $total_user; ?>件</p>
      <div style="width: 100%;  display: flex;">
        <div style="background: #4CAF50; width: <?php echo $user_pos_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
        <div style="background: #FFC107; width: <?php echo $user_mixed_percent; ?>%; height: 12px;border-radius: 6px;  margin-right: 2px;"></div>
        <div style="background: #F44336; width: <?php echo $user_neg_percent; ?>%; height: 12px;border-radius: 6px; margin-right: 2px;"></div>
      </div>
      <p style="font-size: 0.9rem; margin: 5px 0 0;">
        好評：<?php echo $user_positive; ?>件 (<?php echo $user_pos_percent; ?>%)　
        賛否両論：<?php echo $user_mixed; ?>件 (<?php echo $user_mixed_percent; ?>%)　
        不評：<?php echo $user_negative; ?>件 (<?php echo $user_neg_percent; ?>%)
      </p>
    </div>
  </div>

<?php endif; ?>
</div>
		   <!-- 調査日と最新情報リンク -->
      <?php if (isset($meta['survey_date']) && isset($meta['url'])): ?>
      <p style="font-size: 0.9rem; margin: 5px 0 0;">
        調査日：<?php echo esc_html($meta['survey_date']); ?>　
        <a href="<?php echo esc_url($meta['url']); ?>" target="_blank" style="color: #0073e6; text-decoration: underline;">最新情報を見る</a>
      </p>
      <?php endif; ?>
		</div>
	
<?php endif; ?>
	
	
<?php if (isset($detail['evaluation']['playtime'])): ?>
<!-- プレイタイム -->
<div style="margin-bottom: 40px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
<h3>■プレイ時間<span style="font-size: 0.9rem;">（<a href="<?php echo esc_url($play_time_url); ?>" target="_blank" style="color: #0073e6; text-decoration: none; font-weight: bold;">HowLongToBeat</a>参照）</span></h3>

  <table style="width: 100%; max-width: 500px; border-collapse: collapse; font-family: 'Arial', sans-serif; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <thead>
      <tr style="background: #0073e6; color: #333;">
        <th style="padding: 10px; text-align: left;">項目</th>
        <th style="padding: 10px; text-align: left;">時間（h）</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom: 1px solid #ddd;">
        <td style="padding: 10px;">メインストーリー</td>
        <td style="padding: 10px;"><?php echo $main_time; ?></td>
      </tr>
      <tr>
        <td style="padding: 10px;">メイン＋サイド</td>
        <td style="padding: 10px;"><?php echo $main_sides_time; ?></td>
      </tr>
    </tbody>
  </table>
  <?php if (isset($play_time_date) && isset($play_time_url)): ?>
    <p style="font-size: 0.9rem; margin: 5px 0 0;">
      調査日：<?php echo esc_html($play_time_date); ?>　
      <a href="<?php echo esc_url($play_time_url); ?>" target="_blank" style="color: #0073e6; text-decoration: underline;">最新情報を見る</a>
    </p>
  <?php endif; ?>
</div>

	
<?php endif; ?>
	<?php endif; ?>
	
	
<script>
jQuery(document).ready(function($) {
    $('.toggle-evaluation').on('click', function(){
        var $toggle = $(this);
        // #evaluation 内の .evaluation-container を探す
        var $evalContainer = $(this).closest('#evaluation').find('.evaluation-container');
        
        if($evalContainer.hasClass('expanded')){
            // 折りたたむ：max-height を80pxに戻す
            $evalContainer.removeClass('expanded').animate({'max-height': '80px'}, 20);
            $toggle.text('さらに表示☟');
        } else {
            // 展開：scrollHeight を取得して max-height をその値にする
            var fullHeight = $evalContainer.prop('scrollHeight') + 'px';
            $evalContainer.addClass('expanded').animate({'max-height': fullHeight}, 20);
            $toggle.text('閉じる☝');
        }
    });
});

</script>
	
	
<!-- 	商品情報 -->
<?php	
	
	
	
if ( isset( $detail['commerce'] ) && is_array( $detail['commerce'] ) && count( $detail['commerce'] ) > 0 ) {
	echo '<h2 style="margin-bottom: 0px;">商品情報</h2>';
	echo '<small style="display: block; margin-bottom: 10px;">※下記の製品は作品タイトルから自動検索したものが含まれるため。異なる製品が紹介されている場合があります。</small>';
    echo '<div class="game-commerce-info" style="margin-bottom: 40px; padding: 0px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';


    foreach ( $detail['commerce'] as $product ) {
		
        echo '<div class="product-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; max-width: 430px;">';
        echo '<h3 style="margin-top: 0;">' . esc_html( $product['title'] ) . '</h3>';
        if ( ! empty( $product['image'] ) ) {
           echo '<img src="' . esc_url( $product['image'] ) . '" alt="' . esc_attr( $product['title'] ) . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto 10px;">';

        }
        echo '<p><strong>価格:</strong> ' . number_format( $product['price'] ) . '円～</p>';
		if (  isset($product['release_date']) ) {
		$release_timestamp = isset($product['release_date']) ? strtotime($product['release_date']) : "";	
		$release_date = $release_timestamp ? date('Y年n月j日', $release_timestamp) . '（' . $weekDays[date('w', $release_timestamp)] . '）' : '未定';
        echo '<p><strong>発売日:</strong> ' . esc_html( $release_date ) . '</p>';
		}
        if ( ! empty( $product['description'] ) ) {
            // 改行コードを反映させた description を出力
            $desc = nl2br(esc_html($product['description']));
            echo '<p><strong>内容:</strong></p>';
            // 初期状態は高さ80px（約4行分）で、オーバーフロー非表示にする
            // .fade-out クラスを追加して、下部にグラデーションオーバーレイを表示する
            echo '<div class="description-container fade-out" style="max-height: 80px; overflow: hidden; position: relative; transition: max-height 0.5s ease; ">';
            echo $desc;
            // フェードアウト用オーバーレイ（折りたたまれている時のみ表示）
            echo '<div class="fade-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; height: 15px; background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1)); pointer-events: none;"></div>';
            echo '</div>';
            // 切り替え用リンク（中央揃え）
            echo '<div style="text-align: center;">';
            echo '<a href="javascript:void(0);" class="toggle-description" style="display: inline-block; margin-top: 5px; color: #0073aa; text-decoration: none;">さらに表示☟</a>';
            echo '</div>';
        }
        if ( ! empty( $product['buttons'] ) && is_array( $product['buttons'] ) ) {
            echo '<div class="product-buttons" style="margin-top: 30px;">';
            foreach ( $product['buttons'] as $btn ) {
                echo '<a href="' . esc_url( $btn['link'] ) . '" target="_blank" style="display: block; width: 100%; margin-bottom: 7px; padding: 15px 12px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px;">';
                echo esc_html( $btn['platform'].'：'.$btn['text'] );
                echo '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}
?>

<script>
jQuery(document).ready(function($) {
    $('.toggle-description').on('click', function(){
        var $toggle = $(this);
        // 親の .product-card 内から description-container を探す
        var $descContainer = $toggle.closest('.product-card').find('.description-container');
        
        if( $descContainer.hasClass('expanded') ){
            // 折りたたむ：max-height を80pxに戻す
            $descContainer.removeClass('expanded').animate({'max-height': '80px'}, 20);
            $toggle.text('さらに表示☟');
        } else {
            // 展開：scrollHeight を取得して max-height をその値にする
            var fullHeight = $descContainer.prop('scrollHeight') + 'px';
            $descContainer.addClass('expanded').animate({'max-height': fullHeight}, 20);
            $toggle.text('閉じる☝');
        }
    });
});
</script>
	
<?php
	if ( isset( $detail['platforms'] ) && is_array( $detail['platforms'] )) {
echo display_platform_banners($detail['platforms']);
	}
?>
	
	<!-- 関連グッズ -->
<?php
if ( isset( $detail['goods'] ) && is_array( $detail['goods'] ) && count( $detail['goods'] ) > 0 ) {
    echo '<h2 style="margin-bottom: 20px;">関連グッズ</h2>';
    // スライド群のコンテナ
    echo '<div id="goods-slides">';
    
    foreach ( $detail['goods'] as $good ) {
        // 各商品カード全体をリンクにする（target="_blank" で新規ウィンドウ）
        // ここで display:block; を追加しておく
        echo '<a href="' . esc_url( $good['link'] ) . '" target="_blank" style="text-decoration: none; color: inherit; display: block;">';
        echo '<div class="goods-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">';
        
        // 商品画像
        if ( ! empty( $good['image'] ) ) {
            echo '<img src="' . esc_url( $good['image'] ) . '" alt="' . esc_attr( $good['text'] ) . '" style="width: 100%; height: auto; display: block; margin-bottom: 10px;">';
        }
        // 商品名（text の内容）を中央揃えで表示
        echo '<h3 style="margin: 0 0 10px; font-size: 1.2rem; text-align: center;">' . esc_html( $good['text'] ) . '</h3>';
        // 価格表示（中央揃え）
        echo '<p style="margin: 0; font-weight: bold; font-size: 1.1rem; text-align: center;">' . number_format( $good['price'] ) . '円</p>';
        
        echo '</div>';
        echo '</a>';
    }
    echo '</div>'; // #goods-slides
}
?>


	
	
<?php
//     // DLC情報の取得（既存コード）
// if ( !empty($detail['dlc_games'] )){
// 	$dlc_games_prv = $detail['dlc_games'];
// 	$dlc_games = array();
// 	if (!empty($dlc_games_prv)) {
// 		// cover または name が存在するもののみ抽出
// 		$filtered = array();
// 		foreach ($dlc_games_prv as $item) {
// 			if ((!empty($item['cover'])) || (!empty($item['name']))) {
// 				$dlc_games[] = $item;
// 			}
// 		}
//     }
	
	
// if ($dlc_games && is_array($dlc_games) && count($dlc_games) > 0) {
	
	
	
//     // DLC を発売日（リリースタイムスタンプ）で降順にソート
//     usort($dlc_games, function($a, $b) {
//         // DLC $a のリリースタイムスタンプ取得（初期値は first_release_date）
//         $a_ts = !empty($a['first_release_date']) ? intval($a['first_release_date']) : 0;
//         if (!empty($a['release_dates']) && is_array($a['release_dates'])) {
//             foreach ($a['release_dates'] as $rd) {
//                 if (isset($rd['release_region']) && $rd['release_region'] == 3 && !empty($rd['date'])) {
//                     $a_ts = intval($rd['date']);
//                     break;
//                 }
//             }
//         }
//         // DLC $b のリリースタイムスタンプ取得
//         $b_ts = !empty($b['first_release_date']) ? intval($b['first_release_date']) : 0;
//         if (!empty($b['release_dates']) && is_array($b['release_dates'])) {
//             foreach ($b['release_dates'] as $rd) {
//                 if (isset($rd['release_region']) && $rd['release_region'] == 3 && !empty($rd['date'])) {
//                     $b_ts = intval($rd['date']);
//                     break;
//                 }
//             }
//         }
//         // 降順ソート
//         return $b_ts - $a_ts;
//     });
    
	

	
	
	
	
//     echo '<h2 style="margin-top: 40px;">DLC</h2>';
//     echo '<div class="dlc-game-list">';
//     foreach ($dlc_games as $dlc) {
//         $dlc_id = isset($dlc['id']) ? intval($dlc['id']) : 0;
//         $dlc_name = isset($dlc['name']) ? esc_html($dlc['name']) : '不明';
//         if (!empty($dlc['game_localizations']) && is_array($dlc['game_localizations'])) {
//             foreach ($dlc['game_localizations'] as $localization) {
//                 if (isset($localization['region']) && $localization['region'] === 3 && !empty($localization['name'])) {
//                     $dlc_name = esc_html($localization['name']);
//                     break;
//                 }
//             }
//         }
//         $dlc_release_timestamp = !empty($dlc['first_release_date']) ? intval($dlc['first_release_date']) : 0;
//         if (!empty($dlc['release_dates']) && is_array($dlc['release_dates'])) {
//             foreach ($dlc['release_dates'] as $rd) {
//                 if (isset($rd['release_region']) && $rd['release_region'] == 3 && !empty($rd['date'])) {
//                     $dlc_release_timestamp = intval($rd['date']);
//                     break;
//                 }
//             }
// 		}
		
// 		$dlc_release_date = $dlc_release_timestamp ? date('Y年n月j日', $dlc_release_timestamp) . '（' . $weekDays[date('w', $dlc_release_timestamp)] . '）' : '未定';
//         $dlc_cover = (!empty($dlc['cover']) && isset($dlc['cover']['image_id'])) ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $dlc['cover']['image_id'] . '.jpg' : 'https://game-plusplus.com/wp-content/uploads/2025/03/cover_no_image.png';
//         echo '<div class="dlc-game-card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">';
//         echo '<img src="' . esc_url($dlc_cover) . '" style="width: 100%; height: auto; object-fit: contain;" />';
//         echo '<h3 style="font-size: 1.2rem; margin: 10px 0 5px;">' . $dlc_name . '</h3>';
//         echo '<p style="font-size: 1rem; margin: 0;">発売日: ' . $dlc_release_date . '</p>';
//         echo '</div>';
//     }
//     echo '</div>';
// }
// }
    ?>

    <!-- 詳細情報テーブル -->
    <?php
	$japanese_supports = array();
	if (!empty($detail['language_supports']) && is_array($detail['language_supports'])) {
		foreach ($detail['language_supports'] as $lang_sup) {
			// 'language'が文字列の場合のチェック
			if (isset($lang_sup['language']) && strtolower($lang_sup['language']) == 3) {
				if (isset($lang_sup['language_support_type'])) {
					if ($lang_sup['language_support_type'] == 2) {
					$japanese_supports[] = "音声";
					}
					else if ($lang_sup['language_support_type'] == 3) {
					$japanese_supports[] = "インターフェイス";
					}
					
			}
		}
	}
	}
	$japanese_supports_str = implode(', ', $japanese_supports);


    $modes = array();
    if (!empty($detail['game_modes']) && is_array($detail['game_modes'])) {
        foreach ($detail['game_modes'] as $mode) {
            if(isset($mode['name'])) {
				$modes[] = translateGameMode($mode['name']);
			}
        }
    }
    $modes_str = implode(', ', $modes);



    $genres_arr = array();
    if (!empty($detail['genres']) && is_array($detail['genres'])) {
        foreach ($detail['genres'] as $genre) {
            if(isset($genre['name'])) {
                $genres_arr[] = translateGenre($genre['name']);
            }
        }
    }
    $genres_str = implode(', ', $genres_arr);

    $perspectives = array();
    if (!empty($detail['player_perspectives']) && is_array($detail['player_perspectives'])) {
        foreach ($detail['player_perspectives'] as $persp) {
            if(isset($persp['name'])) {
                $perspectives[] = translatePlayerPerspective($persp['name']);
            }
        }
    }
    $perspectives_str = implode(', ', $perspectives);

    // マルチプレイヤーモードについては、条件に応じた集約処理が必要
    // 簡易的な例として、最初のエントリーの値を採用する場合：
    $mp = !empty($detail['multiplayer_modes']) && is_array($detail['multiplayer_modes']) ? $detail['multiplayer_modes'][0] : null;
    $offlineCoop = ($mp && isset($mp['offlinecoop'])) ? ($mp['offlinecoop'] ? '可能' : '不可') : '';
    $offlineCoopMax = ($mp && isset($mp['offlinecoopmax'])) ? $mp['offlinecoopmax'] : '';
    $offlineMax = ($mp && isset($mp['offlinemax'])) ? $mp['offlinemax'] : '';
    $onlineCoop = ($mp && isset($mp['onlinecoop'])) ? ($mp['onlinecoop'] ? '可能' : '不可') : '';
    $onlineCoopMax = ($mp && isset($mp['onlinecoopmax'])) ? $mp['onlinecoopmax'] : '';
    $onlineMax = ($mp && isset($mp['onlinemax'])) ? $mp['onlinemax'] : '';

    $engines = array();
    if (!empty($detail['game_engines']) && is_array($detail['game_engines'])) {
        foreach ($detail['game_engines'] as $engine) {
            if(isset($engine['name'])) {
                $engines[] = $engine['name'];
            }
        }
    }
    $engines_str = implode(', ', $engines);

	// 詳細情報のテーブル出力
	echo '<h2 style="display:inline; margin-right:10px;">詳細情報</h2>';
echo '<span id="toggleCost" style="color: blue; text-decoration: underline; cursor: pointer; font-size: small;">さらに表示</span>';


// 詳細情報のテーブルを、元々の「費用項目」「費用詳細」部分に表示（初期状態は非表示）
echo '<div id="costInfo" style="display: none; margin-top: 10px;">';
echo '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
echo '<tr><th>項目</th><th>内容</th></tr>';
echo '<tr><td>日本語対応</td><td>' . $japanese_supports_str . '</td></tr>';
echo '<tr><td>ゲームモード</td><td>' . $modes_str . '</td></tr>';
echo '<tr><td>ジャンル</td><td>' . $genres_str . '</td></tr>';
echo '<tr><td>プレイヤー視点</td><td>' . $perspectives_str . '</td></tr>';
echo '<tr><td>オフライン協力</td><td>' . $offlineCoop . '</td></tr>';
echo '<tr><td>オフライン協力最大プレイヤー数</td><td>' . $offlineCoopMax . '</td></tr>';
echo '<tr><td>オフライン対戦最大プレイヤー数</td><td>' . $offlineMax . '</td></tr>';
echo '<tr><td>オンライン協力</td><td>' . $onlineCoop . '</td></tr>';
echo '<tr><td>オンライン協力最大プレイヤー数</td><td>' . $onlineCoopMax . '</td></tr>';
echo '<tr><td>オンライン対戦最大プレイヤー数</td><td>' . $onlineMax . '</td></tr>';
echo '<tr><td>ゲームエンジン</td><td>' . $engines_str . '</td></tr>';
echo '</table>';
echo '</div>';
?>

<!-- JavaScriptで表示／非表示とリンクテキストの切り替え -->
<script>
document.getElementById('toggleCost').addEventListener('click', function() {
    var costInfo = document.getElementById('costInfo');
    if(costInfo.style.display === 'none') {
        costInfo.style.display = 'block';
        this.textContent = '閉じる';
    } else {
        costInfo.style.display = 'none';
        this.textContent = 'さらに表示';
    }
});
</script>
	
	
	
	
	
	
<?php
if (!empty($detail['pc_requirement'])) {
	echo '<br>';
    // タイトルと「さらに表示」リンクを横並びに表示
    echo '<h2 style="display:inline; margin-right:10px;">PCのシステム要件</h2>';
    echo '<span id="togglePC" style="color: blue; text-decoration: underline; cursor: pointer; font-size: small;">さらに表示</span>';

    // PCシステム要件の詳細情報（初期状態では非表示）
    echo '<div id="pcReq" style="margin-bottom: 20px; padding: 15px; border-radius: 10px; background: #f4f4f4; display: none;">';
    if (!empty($detail['pc_requirement']['minimum'])) {
        echo $detail['pc_requirement']['minimum'];
    }
    if (!empty($detail['pc_requirement']['recommended'])) {
        echo $detail['pc_requirement']['recommended'];
    }
    echo '</div>';
}
?>

<!-- JavaScriptで表示／非表示とリンクテキストの切り替え -->
<script>
document.getElementById('togglePC').addEventListener('click', function() {
    var pcReq = document.getElementById('pcReq');
    if(pcReq.style.display === 'none') {
        pcReq.style.display = 'block';
        this.textContent = '閉じる';
    } else {
        pcReq.style.display = 'none';
        this.textContent = 'さらに表示';
    }
});
</script>


	
	
	
	
	
	
	
</div>
	
	</div>
	<p style="font-size: 0.9rem; margin: 0; text-align: center;">※当サイトではアフィリエイトプログラムを利用して商品を紹介しています。</p>

	<?php

	echo recommended_games($game_id);
	?>
	
	
	
	
	
	
	<?php if (!$detail): ?>
    <p>ゲームデータが見つかりませんでした。</p>
<?php endif; ?>

<?php
// $detail['platforms'] が存在し、かつ配列の場合にのみ render_platforms_html() を実行
$platformsHtml = '';
if ( isset($detail['platforms']) && is_array($detail['platforms']) && count($detail['platforms']) > 0 ) {
    $platformsHtml = render_platforms_html($detail['platforms']);
}
// JavaScript側に渡す際は json_encode でエスケープ
?>  

<!-- 共通スタイル（可能であれば外部CSSへ移動） -->
<style>
    /* ゲーム情報のレイアウト */
    #game-inf { }
    #game-inf-info { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: center; margin-top: 0.2rem; }
    #cover-thum { width: 180px; flex-shrink: 1; }
    #cover-thum img { width: 100%; height: auto; object-fit: contain; margin-bottom: 10px; }
    #game-in { padding-left: 10px; flex-grow: 1; }
    /* スライドショー */
    #detail-slideshow { position: relative; width: 100%; max-width: 889px; margin: auto; margin-bottom: 10px; overflow: hidden; border-radius: 10px; }
    #detail-slides { display: flex; transition: transform 0.5s ease; }
    .slide-button { position: absolute; top: 25%; width: 40px; height: 50%; background: transparent; border: none; color: #fff; z-index: 10; border-radius: 10px; cursor: pointer; }
    .slide-button:hover { background: rgba(0,0,0,0.5) !important; }
	#slide-left {  left: 0px; }
	#slide-right { right: 0px; }
    /* サマリー */
    .summary-container { max-height: 280px; overflow: hidden; position: relative; transition: max-height 0.5s ease;  width: 620px;}
    .fade-overlay2 { position: absolute; bottom: 0; left: 0; right: 0; height: 15px; background: linear-gradient(to bottom, rgba(244,244,244,0), rgba(244,244,244,1)); pointer-events: none; }
    .toggle-summary { display: inline-block; margin-top: 5px; color: #0073aa; text-decoration: none; }
    /* igdb-button は既に外部で定義済みの場合はそちらを利用 */
</style>

<?php 
// 現在の固定ページの投稿サムネイル（アイキャッチ）の URL を取得
$meta_thumbnail = get_the_post_thumbnail_url( get_the_ID(), 'full' );
?>

<script>
		function setImagesBackgroundColorFromHTML(html, bgColor) {
  // 一時的なコンテナ要素を作成し、html文字列を設定
  const container = document.createElement('div');
  container.innerHTML = html;

  // コンテナ内のすべての <img> タグを取得して背景色を変更
  const images = container.querySelectorAll('img');
  images.forEach(function(img) {
    img.style.backgroundColor = bgColor;
  });

  // 変更後のHTML文字列を返す
  return container.innerHTML;
}
	
jQuery(document).ready(function($) {

    // PHP側 detail を JS 変数に設定
    var detail = <?php echo json_encode($detail); ?>;
	var release_status= <?php echo json_encode($release_status); ?>;
	
    if(!detail) {
        $('#detail-content').html('<p>ゲームデータが見つかりませんでした。</p>');
        return;
    }
	
	
	
    
    var detailHtml = '';
	
	
	
	
	

// 固定ページの投稿サムネイルが存在する場合のみ表示
<?php if ( $meta_thumbnail ) : ?>
    detailHtml += '<div id="meta-thumb">';
    detailHtml += '<img src="<?php echo esc_url( $meta_thumbnail ); ?>" loading="lazy" alt="Meta Thumbnail" />';
    detailHtml += '</div>';
<?php endif; ?>

// ゲーム情報部分（カバー画像、タイトル、発売日、ステータス、プラットフォーム）
detailHtml += '<div id="game-inf-info">';
    detailHtml += '<div id="cover-thum">';
        // カバー画像を表示
        detailHtml += '<img src="' + detail.cover + '" loading="lazy" alt="Cover Image" />';
    detailHtml += '</div>';
    detailHtml += '<div id="game-in">';
        detailHtml += '<h3 style="font-size: 2rem;">' + detail.name + '</h3>';
        detailHtml += '<p style="font-size: 1rem; margin-bottom: 10px; color: #666;">' + detail.company + '</p>';
        detailHtml += '<p style="font-size: 1.3rem;"><strong>' + release_status + '</strong></p>';
        detailHtml += '<p style="font-size: 0.9rem; color: #666;">発売日: ' + detail.release_date + '</p>';
        // プラットフォーム部分（存在する場合）
        if(detail.platforms && detail.platforms.length > 0) {
            var platformsHtml = <?php echo json_encode($platformsHtml); ?>;
            detailHtml += platformsHtml;
        }
    detailHtml += '</div>'; // #game-in
detailHtml += '</div>'; // #game-inf-info
	
	
	
	
	
		// --- スライドショー部分 ---
	var slides = [];

	// 動画情報（Steam）
	if (detail.steamvideos && detail.steamvideos.length > 0) {
		for (var i = 0; i < detail.steamvideos.length; i++) {
			if (detail.steamvideos[i].url) {
				slides.push({
					type: 'steamvideo',
					url: detail.steamvideos[i].url,
					thumbnail: detail.steamvideos[i].thumbnail || null
				});
			}
		}
	}

	// 動画情報（YouTube）
	else if (detail.youtubevideos && detail.youtubevideos.length > 0) {
		var youtubeVideos = detail.youtubevideos.filter(function (video) {
			return video.video_id;
		});
		for (var j = 0; j < youtubeVideos.length; j++) {
			slides.push({ type: 'youtubevideo', url: youtubeVideos[j].video_id });
		}
	}

	// 画像情報
	if (detail.images_big && detail.images_big.length > 0) {
		for (var i = 0; i < detail.images_big.length; i++) {
			slides.push({ type: 'image', url: detail.images_big[i] });
		}
	}
// https://www.youtube.com/watch?v=uq-Znr_jGIg&embeds_referring_euri=https%3A%2F%2Fgame-plusplus.com%2Fgame-detail%2F%3Fid%3D288327
	detailHtml += '<div id="detail-slideshow">';
	detailHtml += '<button id="slide-left" class="slide-button">◀</button>';
	detailHtml += '<div id="detail-slides">';

	for (var i = 0; i < slides.length; i++) {
		if (slides[i].type === 'steamvideo') {
			// 親コンテナに16:9のアスペクト比を指定して高さを確保
			detailHtml += '<div style="flex: 0 0 auto; width: 100%; position: relative; padding-bottom: 56.25%; overflow: hidden; background: #000;">';

			// サムネイルがある場合は `poster` を適用
			var posterAttr = slides[i].thumbnail ? ' poster="' + slides[i].thumbnail + '"' : '';

			detailHtml += '  <video controls' + posterAttr + ' style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;">';
			detailHtml += '    <source src="' + slides[i].url + '" type="video/webm">';
			detailHtml += '    Your browser does not support the video tag.';
			detailHtml += '  </video>';
			detailHtml += '</div>';

		} else if (slides[i].type === 'youtubevideo') {
			detailHtml += '<div style="flex: 0 0 auto; width: 100%; position: relative; padding-bottom: 56.25%; overflow: hidden;  background: #000;">';
			detailHtml += '<iframe id="ytplayer' + i + '" src="https://www.youtube.com/embed/' + slides[i].url + '?enablejsapi=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></iframe>';
			detailHtml += '</div>';

		} else {
			detailHtml += '<div style="flex: 0 0 auto; width: 100%; position: relative; padding-bottom: 56.25%; overflow: hidden; background: #000;">';
			detailHtml += '<img src="' + slides[i].url + '" loading="lazy" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;" />';
			detailHtml += '</div>';
		}
	}

	detailHtml += '</div>'; // #detail-slides
	detailHtml += '<button id="slide-right" class="slide-button">▶</button>';
	detailHtml += '</div>'; // #detail-slideshow

	// サマリー部分
	if (detail.summary && detail.summary.length > 0) {
		detailHtml += '<div style="margin-bottom: 20px; padding: 15px; border-radius: 10px; background:  #f4f4f4;">';
		detailHtml += '<div style="display: flex; justify-content: center;">';
		detailHtml += '<div class="summary-container fade-out">' + setImagesBackgroundColorFromHTML(detail.summary, '#000000').replace(/\r?\n/g, '<br>') +
			'<div class="fade-overlay2"></div></div>';
		detailHtml += '</div>';
		detailHtml += '<div style="text-align: center;"><a href="javascript:void(0);" class="toggle-summary">さらに表示☟</a></div>';
		detailHtml += '</div>';
	}

	$('#detail-content').html(detailHtml);

    
    // スライドショー関連の初期設定
    var slideIndex = 0;
    var slideCount = slides.length;
    var slideshow = $('#detail-slideshow');
    var containerWidth = slideshow.width();
    
    // YouTube IFrame API を利用したプレイヤーの生成
    var players = [];
    function initYouTubePlayers() {
        $('#detail-slides iframe').each(function(index, element) {
            var player = new YT.Player(element);
            players.push(player);
        });
    }
    if (typeof YT !== 'undefined' && YT && typeof YT.Player !== 'undefined') {
        initYouTubePlayers();
    } else {
        window.onYouTubeIframeAPIReady = function() {
            initYouTubePlayers();
        };
    }
    
    // 左右ボタンのクリック時：各プレイヤーを一時停止してスライド移動
	$('#slide-left').on('click', function(e) {
		e.stopPropagation();
		// YouTubeプレイヤーを一時停止
		players.forEach(function(player){
			if (player && typeof player.pauseVideo === 'function'){
				player.pauseVideo();
			}
		});
		// Steam動画（video要素）を一時停止
		$('#detail-slides video').each(function() {
			this.pause();
		});

		slideIndex = (slideIndex - 1 + slideCount) % slideCount;
		$('#detail-slides').css('transform', 'translateX(' + (-containerWidth * slideIndex) + 'px)');
	});

	$('#slide-right').on('click', function(e) {
		e.stopPropagation();
		// YouTubeプレイヤーを一時停止
		players.forEach(function(player){
			if (player && typeof player.pauseVideo === 'function'){
				player.pauseVideo();
			}
		});
		// Steam動画（video要素）を一時停止
		$('#detail-slides video').each(function() {
			this.pause();
		});

		slideIndex = (slideIndex + 1) % slideCount;
		$('#detail-slides').css('transform', 'translateX(' + (-containerWidth * slideIndex) + 'px)');
	});

    
    $(window).on('resize.detailSlide', function() {
        containerWidth = slideshow.width();
        $('#detail-slides').css('transform', 'translateX(' + (-containerWidth * slideIndex) + 'px)');
    });
    
    // サマリーのトグル処理
    $('.toggle-summary').on('click', function(){
        var $toggle = $(this);
        var $summaryContainer = $toggle.closest('#detail-content').find('.summary-container');
        if ($summaryContainer.hasClass('expanded')) {
            $summaryContainer.removeClass('expanded').animate({'max-height': '280px'}, 200);
            $toggle.text('さらに表示☟');
        } else {
            var fullHeight = $summaryContainer.prop('scrollHeight') + 'px';
            $summaryContainer.addClass('expanded').animate({'max-height': fullHeight}, 200);
            $toggle.text('閉じる☝');
        }
    });
});
</script>
<?php get_footer(); ?>
