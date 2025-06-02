# Cocoon Child – Game List

## 概要

Cocoon テーマの子テーマで、固定ページテンプレート「Game Detail」を追加します。  
このテンプレートを利用すると、ゲーム情報を API（独自 DB／IGDB など）から取得し、リリース状況やプラットフォーム情報を一覧表示できます。

- **子テーマ名**：Cocoon Child – Game Detail
- **テンプレート名**：Game Detail (`game-detail.php`)
- **主な機能**：
  - ゲーム詳細データを取得するクエリロジック（`inc/game-list/game-detail-db.php`）
  - プラットフォーム・バナー情報の読み込み（`inc/game-list/platform/` 以下）
  - ゲームの要約・概要を折りたたみ表示する JavaScript（`assets/js/game-detail.js`）
  - 固定ページにショートコードやテンプレートを直接当てるだけで動作

## 必要条件

- 親テーマとして **Cocoon** がインストール済みであること
  - 親テーマが `wp-content/themes/cocoon-master` というフォルダ名である前提です。
  - もし違うスラッグになっている場合は、`style.css` の `Template:` 行を修正してください。
- WordPress 本体：バージョン 5.8 以上
- PHP：バージョン 7.4 以上
- 取得元 API キー・DB 設定など（もし `inc/game-list/game-detail-db.php` 内で環境依存の変数がある場合は、個別にご用意ください）

## インストール手順

1. **親テーマ「Cocoon」をインストール**

   - `wp-content/themes/` 以下に、親テーマフォルダ `cocoon-master` が配置されていることを確認してください。

2. **本リポジトリをクローンまたは ZIP ダウンロード**
   ```bash
   git clone https://github.com/YourUserName/cocoon-child-game-detail.git
   ```
