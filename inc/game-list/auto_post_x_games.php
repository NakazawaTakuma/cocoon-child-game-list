<?php
function auto_post_x_games_to_x() {
    // 今日の日付

	$today = strtotime(date('Y-m-d'));
    
    // 条件に合わせた日付のタイムスタンプ
 	$one_week_after = strtotime('+7 days', $today);
    $three_days_after = strtotime('+3 days', $today);
    $next_day = strtotime('+1 day', $today);
    
    // ゲームデータを取得（例: get_all_games() で全ゲーム取得。実装に合わせてください）
	$games = get_sort_games('upcoming',0, $limit = 500, $platform_filter = 'all', $hypes_filter = 5);
    $games_to_post = array();
    foreach ($games as $game) {
        if (empty($game['release_timestamp'])) {
            continue;
        }
        $release_ts = $game['release_timestamp'];


        // 発売日の条件をチェック（例：1週間前、3日前、当日、翌日）
if (($release_ts <= $one_week_after && $release_ts > strtotime('-1 day', $one_week_after)) ||
            ($release_ts <= $three_days_after && $release_ts > strtotime('-1 day', $three_days_after)) ||($release_ts <= $today && $release_ts > strtotime('-1 day', $today))
        ) {
             $games_to_post[] = $game;
        }
    }
// 	$games_to_post = array($games[0]);
    
// 投稿するゲームがあれば、自動投稿処理へ
    foreach ($games_to_post as $game) {
        // ツイート内容を生成
        $tweet = $game['name'] . "\n" .
                 get_release_status_text($game['release_timestamp']) . "\n" .
                 "発売日: " . $game['release_date'] . "\n" .
                 site_url('/game-detail/?id=' . $game['id']);
        
        // Twitter API を利用して投稿
         $result = post_tweet_to_x($tweet);
// 投稿結果のログ記録などを実施
        if (!$result) {
            error_log("X投稿に失敗しました。ゲームID: " . $game['id']);
        }
    }
}



require_once get_stylesheet_directory(). '/vendor/autoload.php';  // Composer のオートローダーを読み込む

use Abraham\TwitterOAuth\TwitterOAuth;


function post_tweet_to_x($tweet) {
    // X(Twitter) API の認証情報（wp-config.php等で定義するか設定ファイルから取得）
    $consumer_key = 'IXlFvSuQaMR5foQ1WAmxbaBU1';
    $consumer_secret = 'lD8azlNq1k62BBh0iHjiuNXcahBpNp2WIVoVq4CJVcL1Fjgd4f';
    $access_token = '1897737965398638593-Mum1BE3X3SRXTnl6l87roUqx7VghkJ';
    $access_token_secret = 'R5XuFFf9dKZMylhrktTjQL6urTurPB3VgUfw93NFj9rME';

    $connect = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
	$connect->setApiVersion('2');
	


	
// 投稿を投稿
$status_payload = [
    'text' => $tweet
];   
$result = $connect->post(
    'tweets', 
    $status_payload
);
	

//   $http_code = $connect->getLastHttpCode();

     // 結果にエラーがあれば false を返す
 if ($connect->getLastHttpCode() != 200) {
//          error_log("Twitter API error: " . print_r($result, true));
//          echo '<pre>';
//          echo "HTTP ステータスコード: " . $http_code . "\n";
//          echo "API レスポンス:\n";
//          print_r($result, true);
// 	 echo '</pre>';
 		return false;
     }
     return true;
}