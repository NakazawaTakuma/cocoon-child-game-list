<?php
if ( ! defined( 'ABSPATH' ) ) exit; // 直接アクセス禁止

/**
 * テーマ内のゲーム関連ファイルを読み込むための定数を定義
 * 'GAME_LIST' → サーバ上の絶対パス
 * 'GAME_LIST_URL' → ブラウザ上のURL
 * 'THEME_INC' → インクルード上位ディレクトリ
 */
define( 'GAME_LIST', get_stylesheet_directory() . '/inc/game-list' );
define( 'GAME_LIST_URL', get_stylesheet_directory_uri() . '/inc/game-list' );
define( 'THEME_INC', get_stylesheet_directory() . '/inc' );

/**
 * 子テーマのスタイルシートを読み込む（親テーマのスタイルは Cocoon 側で自動継承されるためここでは不要）
 * もし 'game-detail.php' で独自 CSS を使う場合は
 * GAME_LIST_URL . '/style.css' のように enqueue できます。
 */
function cgd_enqueue_assets() {
    // 例）ゲーム詳細ページ用の CSS / JS が assets/css, assets/js にある場合
    if ( is_page_template( 'game-detail.php' ) ) {
        wp_enqueue_style(
            'cgd-game-detail-css',
            get_stylesheet_directory_uri() . '/assets/css/game-detail.css',
            array(),
            '1.0.0'
        );
        wp_enqueue_script(
            'cgd-game-detail-js',
            get_stylesheet_directory_uri() . '/assets/js/game-detail.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'cgd_enqueue_assets' );

/**
 * ゲーム関連のインクルードファイルを読み込む
 * → game-detail.php から呼ばれる関数群やデータベースアクセスロジックをまとめておく
 */
require_once THEME_INC . '/game-list/game-list.php';
