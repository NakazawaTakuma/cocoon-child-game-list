<?php


function create_igdb_game_data_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        game_id BIGINT(20) UNSIGNED NOT NULL,
        game_data LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (game_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// プラグイン有効化時にテーブル作成処理を実行
register_activation_hook( __FILE__, 'create_igdb_game_data_table' );


function set_raw_games($cached_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'igdb_game_data';

    // 例：100件ずつ処理
    $chunks = array_chunk($cached_data, 100);

    foreach ($chunks as $chunk) {
        foreach ($chunk as $game) {
            if (empty($game['id'])) {
                continue;
            }
            $game_id = intval($game['id']);
            $game_json = wp_json_encode($game);
            $now = current_time('mysql');

            $sql = $wpdb->prepare(
                "INSERT INTO $table_name (game_id, game_data, updated_at)
                 VALUES (%d, %s, %s)
                 ON DUPLICATE KEY UPDATE game_data = VALUES(game_data), updated_at = VALUES(updated_at)",
                $game_id,
                $game_json,
                $now
            );
            $wpdb->query($sql);
        }
        // ループ終了後にチャンク用変数を解放
        unset($chunk);
    }
}



