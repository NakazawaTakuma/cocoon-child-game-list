jQuery(document).ready(function ($) {
  // wp_localize_script() で渡した値を利用
  var currentReleaseFilter = my_ajax_object.currentReleaseFilter;
  var currentPlatformFilter = my_ajax_object.currentPlatformFilter;
  var itemsPerPage = my_ajax_object.itemsPerPage;
  var currentPage = 1;
  var loading = false;

  // スライダーの初期化と背景更新
  var slider = document.getElementById("hypes-slider");
  var hypesValue = document.getElementById("hypes-value");

  // フィルター変更時の処理
  $(".release-btn").on("click", function () {
    $(".release-btn").removeClass("active");
    $(this).addClass("active");
    currentReleaseFilter = $(this).data("filter");
    updateFiltering();
  });

  $(".platform-btn").on("click", function () {
    $(".platform-btn").removeClass("active");
    $(this).addClass("active");
    currentPlatformFilter = $(this).data("platform");
    updateFiltering();
  });

  if (slider && hypesValue) {
    function updateSliderBackground() {
      var val = slider.value;
      var min = slider.min || 0;
      var max = slider.max || 100;
      var percentage = ((val - min) / (max - min)) * 100;
      slider.style.backgroundImage =
        "linear-gradient(to right, #e0e0e0 0%, #e0e0e0 " +
        percentage +
        "%, #ffb83c " +
        percentage +
        "%, #ffb83c 100%)";
      hypesValue.innerText = val + "以上";
    }
    slider.addEventListener("input", updateSliderBackground);
    // フィルター更新も change イベントで行う
    slider.addEventListener("change", function () {
      updateFiltering();
    });
    updateSliderBackground();
  }
	
$("#search-form").on("submit", function(e) {
    e.preventDefault(); // デフォルトの送信を止める

    updateFiltering(); // フィルター状態に応じてURL構築して遷移
  });

function updateFiltering() {
  var hypesParam = "";
  if (slider) {
    hypesParam = "&hypes=" + slider.value;
  }
  // 検索キーワードも追加
  var searchParam = "";
  var searchValue = $("#search").val();
  if (searchValue) {
    searchParam = "&search=" + encodeURIComponent(searchValue);
  }
  var newUrl =
    window.location.protocol +
    "//" +
    window.location.host +
    window.location.pathname +
    "?release=" +
    currentReleaseFilter +
    "&platform=" +
    currentPlatformFilter +
    hypesParam +
    searchParam;
  history.pushState(null, "", newUrl);
  currentPage = 1;
  $(".igdb-game-list").empty();
  loadGames(currentPage, true);
}
function loadGames(page, replace) {
  if (loading) return;
  loading = true;
  $.ajax({
    url: my_ajax_object.ajax_url,
    type: "POST",
    data: {
      action: "load_more_games",
      page: page,
      release_filter: currentReleaseFilter,
      platform_filter: currentPlatformFilter,
      items_per_page: itemsPerPage,
      hypes_filter: slider ? slider.value : 10,
      search: $("#search").val() // ここで検索キーワードを追加
    },
    success: function (response) {
      if (replace) {
        $(".igdb-game-list").html(response);
      } else {
        $(".igdb-game-list").append(response);
      }
      loading = false;
      initSlideshows();
    },
    error: function () {
      loading = false;
    },
  });
}


  // 無限スクロール
  $(window).on("scroll", function () {
    if (
      $(window).scrollTop() + $(window).height() >
      $(document).height() - 500
    ) {
      if (!loading) {
        currentPage++;
        loadGames(currentPage, false);
      }
    }
  });

  // スライドショーの初期化処理
  function initSlideshows() {
    $(".igdb-game-list a").each(function () {
      var container = $(this).find(".game-slideshow");
      var images = container.find("img");
      if (
        images.length > 1 &&
        typeof container.data("currentIndex") === "undefined"
      ) {
        container.data("currentIndex", 0);
      }
    });
    if (typeof window.globalSlideshowTimer === "undefined") {
      window.globalSlideshowTimer = setInterval(function () {
        $(".igdb-game-list a").each(function () {
          var container = $(this).find(".game-slideshow");
          var images = container.find("img");
          if (images.length <= 1) return;
          if (!isElementInViewport(container[0])) return;
          var currentIndex = container.data("currentIndex") || 0;
          var nextIndex = (currentIndex + 1) % images.length;
          images.eq(currentIndex).fadeOut(1000);
          images.eq(nextIndex).fadeIn(1000);
          container.data("currentIndex", nextIndex);
        });
      }, 5000);
    }
  }

  function isElementInViewport(el) {
    var rect = el.getBoundingClientRect();
    return (
      rect.bottom > 0 &&
      rect.top < (window.innerHeight || document.documentElement.clientHeight)
    );
  }

  if (
    window.location.hostname === "game-plusplus.com" &&
    /^\/release\/?$/.test(window.location.pathname)
  ) {
    initSlideshows();
  }
});
