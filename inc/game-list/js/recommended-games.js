// js/recommended-games.js
jQuery(document).ready(function ($) {
  // hidden フィールドからシャッフル順の order を取得
  var order = $("#recommended_game_order").val();
  var page = 2; // 初期表示で1ページ目（先頭10件）が表示済み
  var loading = false;

  $(window).on("scroll", function () {
    if (loading) return;
    // 画面下部から100px以内になったら追加読み込み
    if (
      $(window).scrollTop() + $(window).height() >=
      $(document).height() - 100
    ) {
      loading = true;

      $.ajax({
        url: my_ajax_object.ajax_url,
        method: "POST",
        data: {
          action: "load_more_recommend_games",
          page: page,
          items_per_page: 20,
          order: order,
        },
        success: function (response) {
          if (response) {
            $(".igdb-recommend-game-list").append(response);
            page++;
          }
          loading = false;
        },
        error: function () {
          loading = false;
        },
      });
    }
  });
});
