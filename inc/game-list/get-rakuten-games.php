<?php
/**
 * 楽天ブックスゲーム検索API（version:2017-04-04）から、
 * ゲームタイトルのみで全プラットフォーム向けのデータを一括取得する関数
 *
 * @param string $gameTitle 検索したいゲームタイトル
 * @param int $hits 取得件数（例：30件）
 * @return array|false 取得結果の連想配列（失敗時はfalse）
 */
function getRakutenGameDataAll($gameTitle, $hits = 30) {
    $appId     = "1094221071605830392";
    $affiliate = "46eb4d68.e6eb5ecd.46eb4d69.602c242b";  // 任意

    $params = [
        "applicationId" => $appId,
        "affiliateId"   => $affiliate,    
        "format"        => "json",
        "title"         => $gameTitle,
        "hits"          => $hits,
		"sort"          => "standard",
        // hardwareパラメーターは指定せず、全件取得する
    ];

    $baseUrl = "https://app.rakuten.co.jp/services/api/BooksGame/Search/20170404";
    $queryString = http_build_query($params);
    $requestUrl = $baseUrl . "?" . $queryString;
// 	error_log($requestUrl);
	
    $response = file_get_contents($requestUrl);
    if ($response === false) {
		error_log("APIリクエストに失敗しました。URL: " . $requestUrl);
        return false;
    }

    $data = json_decode($response, true);
    if ($data === null) {
		error_log("JSONのデコードに失敗しました。Response: " . $response);
        return false;
    }

    return $data;
}

/**
 * プラットフォーム名と楽天側のhardware値の対応表を返す関数
 *
 * @return array
 */
function get_platform_to_hardware() {
    return array(
        'Switch 2'      => 'Nintendo Switch 2',
        'Switch'        => 'Nintendo Switch',
        'Wii'           => 'Wii',
        'WiiU'          => 'Wii U',
        'DS'            => 'Nintendo DS',
        '3DS'           => 'Nintendo 3DS',
        'PS2'           => 'PS2',
        'PS3'           => 'PS3',
        'PS4'           => 'PS4',
        'PS5'           => 'PS5',
        'PSP'           => 'PSP',
        'Vita'          => 'PS Vita',
        'X360'          => 'Xbox 360',
        'XONE'          => 'Xbox One',
        'Series X|S'    => 'Xbox Series'
    );
}
/**
 * 指定したゲームタイトルと対象プラットフォーム群に対して、
 * 楽天ブックスから商品情報を取得し、価格や画像、説明、アフィリエイトリンク等をまとめる関数
 * API呼び出しは、タイトルのみで全件取得し、ローカルで各プラットフォームごとにフィルタリングします。
 *
 * @param string $gameTitle 検索したいゲームタイトル
 * @param array $platforms_array プラットフォーム名の配列（例：["Switch", "PS5", "XONE"]）
 * @return array 商品情報のまとめ配列
 */
function getRakutenCommerce($gameTitle, $platforms_array) {
    $commerce = array();
    $buttons = array();
    $minPrice = null;
    $image = '';
    $description = '';
    $name = '';

// まず、タイトルのみで全件取得（API使用回数は１回）
    $data = getRakutenGameDataAll($gameTitle, 30);
// 	error_log(print_r($data, true));
// 	error_log("【データ】: " . print_r($data, true));
    if ($data === false || !isset($data["Items"]) || !is_array($data["Items"])) {
//         error_log("getRakutenCommerce: 取得したデータが不正です。");
        return false;
    }
    $allItems = $data["Items"];

    // 対応表を取得
    $platform2Hardware = get_platform_to_hardware();
	
    // 取得済みの全アイテムから、各アイテム毎にプラットフォームがマッチするものだけ処理する
    foreach ($allItems as $itemdata) {
		$item = $itemdata["Item"];
		
		
        $buttons = array();
        $Price = null;
        $itemImage = '';
        $itemDescription = '';
        $itemName = '';

        $containPlatform = false;
        $matchedPlatform = ''; // マッチしたプラットフォーム名を保持
		error_log("【タイトル】: " . print_r($gameTitle, true));
        // 各プラットフォームごとに、該当する商品があるかチェック
        foreach ($platforms_array as $platform_name) {
            if (!$containPlatform) {
                if (!isset($platform2Hardware[$platform_name])) {
//                     error_log("getRakutenCommerce: 未定義のプラットフォーム: " . $platform_name);
                    continue;
                }
                $hardware = $platform2Hardware[$platform_name];
                // APIレスポンスの hardware は大文字小文字や余計な空白が混じる可能性があるため、trimおよびcase-insensitive比較
                if (isset($item["hardware"]) && (strcasecmp(trim($item["hardware"]), $hardware) === 0)) {
                    $containPlatform = true;
                    $matchedPlatform = $platform_name;
                    break; // 最初にマッチしたプラットフォームでループを抜ける
                }
            }
        }
        // 対象プラットフォームに一致しなければスキップ
        if (!$containPlatform) {
            continue;
        }
		error_log("【取得したアイテム】: " . print_r($item, true));

        // 各項目を取得
        if (!empty($item["largeImageUrl"])) {
            $itemImage = $item["largeImageUrl"];
        }
        if (!empty($item["itemPrice"])) {
            $Price = $item["itemPrice"];
        }
//         if (isset($item["itemCaption"])) {
//             $itemDescription = $item["itemCaption"];
//         }
        // API仕様により商品名は通常 "itemName" で返されることが多い
        if (isset($item["itemName"])) {
            $itemName = $item["itemName"];
        } else if (isset($item["title"])) {
            $itemName = $item["title"];
        }

        // アフィリエイトリンクの取得（affiliateUrlがあれば優先）
        $link = "";
        if (isset($item["affiliateUrl"])) {
            $link = $item["affiliateUrl"];
        } else if (isset($item["itemUrl"])) {
            $link = $item["itemUrl"];
        }

        // 対象プラットフォーム向けのボタン情報を作成
        $buttons[] = array(
            'platform' => $matchedPlatform,
            'shop'     => 'rakuten',
            'text'     => '楽天で予約・購入する',
            'link'     => $link
        );
		error_log("【現在のcommerce配列】: " . print_r($commerce, true));
        // 商品情報をまとめて配列に追加
        $commerce[] = array(
            'title'       => $itemName,
            'price'       => $Price,        // 価格（円単位）
            'image'       => $itemImage,    // 商品画像のURL
            'description' => $itemDescription,
            'buttons'     => $buttons
        );
    }

    return $commerce;
}

// function getRakutenCommerce($gameTitle, $platforms_array) {
//     $commerce = array();
//     $buttons = array();
//     $minPrice = null;
//     $image = '';
//     $description = '';

//     // まず、タイトルのみで全件取得（API使用回数は１回）
//     $data = getRakutenGameDataAll($gameTitle, 30);
//     if ($data === false || !isset($data["items"]) || !is_array($data["items"])) {
// 		error_log("getRakutenCommerce: 取得したデータが不正です。");
//         return false;
//     }
//     $allItems = $data["items"];

//     // 対応表を取得
//     $platform2Hardware = get_platform_to_hardware();

//     // 各プラットフォームごとに、該当する商品があればボタン情報を作成
//     foreach ($platforms_array as $platform_name) {
//         if (!isset($platform2Hardware[$platform_name])) {
// 			error_log("getRakutenCommerce: 未定義のプラットフォーム: " . $platform_name);
//             continue;
//         }
//         $hardware = $platform2Hardware[$platform_name];

//         // 取得済みの全アイテムから、"hardware"フィールドが$hardwareと一致するものをフィルタリング
//         foreach ($allItems as $item) {
//             // APIレスポンスのハードウェア情報は大文字小文字やスペースが含まれる可能性があるので、trimやcase-insensitiveな比較を行う
//             if (isset($item["hardware"]) && (strcasecmp(trim($item["hardware"]), $hardware) === 0)) {
//                 // 画像がまだセットされていなければ設定
//                 if (empty($image) && !empty($item["mediumImageUrl"])) {
//                     $image = $item["mediumImageUrl"];
//                 }
//                 // 最安価格の更新
//                 if (!empty($item["itemPrice"]) && (is_null($minPrice) || $item["itemPrice"] <= $minPrice)) {
//                     $minPrice = $item["itemPrice"];
//                 }
//                 // 商品説明があれば取得
//                 if (isset($item["itemCaption"])) {
//                     $description = $item["itemCaption"];
//                 }

//                 // アフィリエイトリンクの取得（affiliateUrlが優先）
//                 $link = "";
//                 if (isset($item["affiliateUrl"])) {
//                     $link = $item["affiliateUrl"];
//                 } else if (isset($item["itemUrl"])) {
//                     $link = $item["itemUrl"];
//                 }

//                 // 対象プラットフォーム向けのボタン情報を追加
//                 $buttons[] = array(
//                     'platform' => $platform_name,
//                     'shop'     => 'rakuten',
//                     'text'     => '楽天で予約・購入する',
//                     'link'     => $link
//                 );
//                 // ひとつ見つかったら、各プラットフォームごとに複数呼ばなくてもよいので break する（必要に応じて変更）
//                 break;
//             }
//         }
//     }

//     $commerce = array(
//         array(
//             'title'       => $gameTitle,
//             'price'       => $minPrice,        // 価格（例: 円単位）
//             'image'       => $image,           // 商品画像のURL
//             'description' => $description,
//             'buttons'     => $buttons
//         )
//     );

//     return $commerce;
// }




// function getRakutenCommerce($gameTitle, $platforms_array) {
// 	$commerce = array();
// 	$buttons = array();
// 	$minPrince = null;
// 	$image = '';
// 	$description = '';
// 	$buttons = array();
// 	$platform2Hardware = get_platform_to_hardware();
// 	foreach ($platforms_array as $platform_name) {
// 		$hardware = $platform2Hardware[$platform_name];
// 		if(!empty($hardware)){
// 			$data = getRakutenGameData($gameTitle, $hardware);
// 			if ($data !== false && isset($data["items"]) && is_array($data["items"])) {
				
//     		foreach ($data["items"] as $item) {
// 				if(empty($image) && !empty( $item["mediumImageUrl"] )){
// 					$image = $item["mediumImageUrl"];
// 					}
// 				if( !empty( $item["itemPrice"] ) && ( empty($minPrince) || $item["itemPrice"] <= $minPrince)){
// 					$minPrince = $item["itemPrice"];
// 					}
// 				if (isset($item["itemCaption"])) {
// 					$description = $item["itemCaption"];
// 				}

// 				$link = '';
				
// 				if (isset($item["affiliateUrl"])) {
// 						$link = $item["affiliateUrl"];
// 				}else if (isset($item["itemUrl"])) {
// 						$link = $item["itemUrl"];
// 				}
				
// 				$buttons[] = array(
//                         'platform' => $platform_name,
// 						'shop' => 'rakuten',
//                         'text'   => '楽天で予約・購入する',
//                         'link'     => $link
//                     );
// 				}
// 			}
// 		}
// 	}
// 	$commerce =  array(
//             array(
//                 'title'        => '',
//                 'price'        => $minPrince, // 価格（例: 円単位）
//                 'image'        => $image, // 商品画像のURL
//                 'description' => $description,
//                 'buttons'      => $buttons
//             )
// 	);

// 	return $commerce;
// }


// /**
//  * 楽天ブックスゲーム検索API（version:2017-04-04）から、ゲームタイトルとプラットフォームでデータを取得する関数
//  *
//  * @param string $gameTitle 検索したいゲームタイトル
//  * @param string $hardware  プラットフォーム（例："PS", "Nintendo Switch"）
//  * @return array|false 取得結果の連想配列（失敗時はfalse）
//  */
// function getRakutenGameData($gameTitle, $hardware) {
//     $appId     = "YOUR_APPLICATION_ID";
//     $affiliate = "YOUR_AFFILIATE_ID";  // 任意

//     $params = [
//         "applicationId" => $appId,
//         "affiliateId"   => $affiliate,    // アフィリエイト連携をする場合
//         "format"        => "json",
//         "title"         => $gameTitle,
//         "hardware"      => $hardware,     // ここでプラットフォームを指定
//         "hits"          => 1,
//     ];

//     $baseUrl = "https://app.rakuten.co.jp/services/api/BooksGame/Search/20170404";
//     $queryString = http_build_query($params);
//     $requestUrl = $baseUrl . "?" . $queryString;

//     $response = file_get_contents($requestUrl);
//     if ($response === false) {
//         return false;
//     }

//     $data = json_decode($response, true);
//     if ($data === null) {
//         return false;
//     }

//     return $data;
// }


// function get_platform_to_hardware() {
//     return array(
// 		'Switch 2'      => 'Nintendo Switch 2',
//         'Switch'      => 'Nintendo Switch',
// 		'Wii'      => 'Wii',
// 		'WiiU'      => 'Wii U',
// 		'DS'      => 'Nintendo DS',
// 		'3DS'      => 'Nintendo 3DS',
// 		'PS2'         => 'PS2',
// 		'PS3'         => 'PS3',
//         'PS4'         => 'PS4',
//         'PS5'         => 'PS5',
// 		'PSP'         => 'PSP',
// 		'Vita'         => 'PS Vita',
// 		'X360'        => 'Xbox 360',
//         'XONE'        => 'Xbox One',
//         'Series X|S'  => 'Xbox Series',
//     );
// }