<?php

// テーマ内のファイルを読み込むためのパスを定義
// define( 'THEME_INF', get_stylesheet_directory() . '/inf' );

require_once GAME_LIST . '/inf/pc/cpu-info.php';





function parse_requirements($requirements_html) {
    // HTMLタグを除去し、改行コード（\n）でデータを分割
    // ここで<br>タグを置き換え、改行コードで分割する
    $requirements_html = preg_replace('/<br\s*\/?>/i', "\n", $requirements_html); // <br>を改行に変換
    $lines = explode("\n", strip_tags($requirements_html)); // 改行コードで分割

    // 比較用のデータを格納する配列
    $parsed_data = [
        'os'        => '',
        'processor' => '',
        'memory'    => 0,
        'graphics'  => '',
        'storage'   => 0
    ];

    foreach ($lines as $line) {
        $line = trim($line);

        // OS
        if (preg_match('/(OS|オペレーティングシステム):\s*(.+)/i', $line, $matches)) {
            $parsed_data['os'] = trim($matches[2]);
        }

        // プロセッサー
        elseif (preg_match('/(プロセッサー|CPU):\s*(.+)/i', $line, $matches)) {
            $parsed_data['processor'] = trim($matches[2]);
// 			print_r($parsed_data['processor']);
 			$C = extract_cpu_models($parsed_data['processor'], get_cpu_data() );
//  			print_r($C );
      }

        // メモリー (GB/MB対応)
        elseif (preg_match('/(メモリー|メモリ|Memory):\s*(\d+)\s*(GB|MB)?/i', $line, $matches)) {
            $memory = intval($matches[2]);
            $parsed_data['memory'] = (isset($matches[3]) && strtoupper($matches[3]) === 'MB')
                                    ? round($memory / 1024) // MB→GB換算
                                    : $memory;
        }

        // グラフィック
        elseif (preg_match('/(グラフィック|GPU|Graphics):\s*(.+)/i', $line, $matches)) {
            $parsed_data['graphics'] = trim($matches[2]);
        }

        // ストレージ (GB/MB対応)
        elseif (preg_match('/(ストレージ|Storage):\s*(\d+)\s*(GB|MB)?/i', $line, $matches)) {
            $storage = intval($matches[2]);
            $parsed_data['storage'] = (isset($matches[3]) && strtoupper($matches[3]) === 'MB')
                                    ? round($storage / 1024) // MB→GB換算
                                    : $storage;
        }
    }

    return $parsed_data;
}



/**
 * CPU要件文字列から、ベンチマーク配列にあるCPU名と類似するものを抽出する関数
 *
 * @param string $cpuRequirement 入力されたCPU要件文字列
 * @param array  $benchmarkArray   ベンチマークデータ（キーがCPU名、値はスコア等の情報）
 * @param float  $threshold        類似度の閾値（0～100、例: 70）
 * @return array 抽出されたCPU名の配列
 */
function extract_cpu_models($cpuRequirement, $benchmarkArray, $threshold = 80) {
	print_r($cpuRequirement);
    // 入力が配列の場合は文字列に結合
    if (is_array($cpuRequirement)) {
        $cpuRequirement = implode(', ', $cpuRequirement);
    }
    
    // 入力が "TBA" や "TBC" などの場合は空の結果を返す
    if (preg_match('/^(tba|tbc)$/i', trim($cpuRequirement))) {
        return [];
    }
    
    // 改行、カンマ、スラッシュ、パイプ、" or " などで分割
    $tokens = preg_split('/[,\/|]+|\bor\b/i', $cpuRequirement);
    
    // 正規化用のクロージャ：小文字化、不要な単語・記号の除去
    $normalize = function($str) {
		$str = mb_strtolower($str);
		// 不要な単語（ベンダー名や「core」など）を削除
		$remove_words = array('intel', 'core', 'intel', 'amd', 'cpu');
		foreach ($remove_words as $word) {
			$str = str_replace($word, '', $str);
		}
        // 「以上」「以上」「〜」などを除去
        $str = preg_replace('/(以上|〜|~)/u', '', $str);
        // 不要な記号や括弧類を除去
        $str = preg_replace('/[\(\)®™]/u', '', $str);
        // 複数空白は1つに
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    };
    
    $results = [];
    // ベンチマークのキー（CPU名）の配列
    $benchmarkKeys = array_keys($benchmarkArray);
    print_r($tokens);
    foreach ($tokens as $token) {
        $token = trim($token);
		
        if (empty($token)) continue;
        
        // "amd / intel cpu" など、汎用的な文字列は除外
        if (preg_match('/^(amd\s*\/\s*intel\s*cpu)$/i', $token)) {
            continue;
        }
        
        $normalizedToken = $normalize($token);
        if (empty($normalizedToken)) continue;
        
        $bestMatch = null;
        $bestSimilarity = 0;
        
//         foreach ($benchmarkKeys as $cpuName) {
//             $normalizedCpuName = $normalize($cpuName);
//             similar_text($normalizedToken, $normalizedCpuName, $percent);
//             if ($percent > $bestSimilarity && $percent >= $threshold) {
//                 $bestSimilarity = $percent;
//                 $bestMatch = $cpuName;
//             }
//         }
		foreach ($benchmarkKeys as $cpuName) {
			$normalizedCpuName = $normalize($cpuName);
			similar_text($normalizedToken, $normalizedCpuName, $percent);

			// もしトークンがベンチマーク名に部分的に含まれていれば、類似度を100%とみなす
// 			if (strpos($normalizedCpuName, $normalizedToken) !== false || strpos($normalizedToken, $normalizedCpuName) !== false) {
// 				$percent = 100;
// 			}

			if ($percent > $bestSimilarity && $percent >= $threshold) {
				$bestSimilarity = $percent;
				$bestMatch = $cpuName;
			}
		}

		
        
        if ($bestMatch !== null && !in_array($bestMatch, $results)) {
            $results[] = $bestMatch;
        }
    }
    print_r($results);
    return $results;
}



