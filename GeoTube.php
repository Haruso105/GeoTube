<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>GeoTube</title>
<link rel="stylesheet" href="style.css">
</head>
  <body>
    <h1>GeoTube: マップで動画検索！</h1>
    <h3>地図をクリックすると、その国の人気動画やその地域の関連動画が表示されます！</h3>
    <p>※検索できない地域は、地域情報の未登録、もしくはYoutubeの規制が入っています <br>
      ※同じ国内でも、ピンの位置によってはその国の動画が表示されないことがあります</p>

    <div id="map"></div>

    <!-- 見出しを表示 -->
    <h2><span id="countryTitle">国</span> の人気動画</h2>
    <div id="popularList" class="video-container"></div>

    <h2><span id="regionTitle">地域</span> の関連動画</h2>
    <div id="searchList" class="video-container"></div>


    <?php
      function loadEnv($path) {
          if (!file_exists($path)) return;
          $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
              if (strpos(trim($line), '#') === 0) continue;
              list($name, $value) = explode('=', $line, 2);
              putenv(trim($name) . "=" . trim($value));
          }
      }

      loadEnv(__DIR__ . '/.env');
      $api_key = getenv('GEOTUBE_API_KEY');
    ?>

    <script>
      // googleMapとYoutubeのAPI
      const API_KEY = '<?php echo $api_key ?>'; 

      let map;
      let marker;
      let geocoder;

      function initMap() {
        // 初期位置は東京駅にする
        const defaultPos = { lat: 35.681, lng: 139.767 }; 

        // マップ情報
        map = new google.maps.Map(document.getElementById("map"), {
          center: defaultPos,
          zoom: 4,
        });

        // まーかー情報
        marker = new google.maps.Marker({
          position: defaultPos,
          map: map,
          draggable: true
        });

        // 地域取得用情報
        geocoder = new google.maps.Geocoder();

        // クリックしたらマーカー設置、地域検索
        map.addListener("click", function(e) {
          marker.setPosition(e.latLng);
          reverseGeocode(e.latLng.lat(), e.latLng.lng());
        });

        reverseGeocode(defaultPos.lat, defaultPos.lng);
      }

      // 逆ジオコーディング、地域情報を取得する
      function reverseGeocode(lat, lng) {
        geocoder.geocode({ location: { lat, lng } }).then(response => {
          //レスポンスを取得
          const result = response.results[0];
          if(!result) return;

          let countryCode = null;
          let countryName = null;
          let regionName = null;

          // 地域の情報を探す
          result.address_components.forEach(c => {
            if(c.types.includes("country")){
                countryCode = c.short_name;
                countryName = c.long_name;
              }
            if(c.types.includes("administrative_area_level_1")){
              regionName = c.long_name;
            }
          });

          const countryTitle = document.getElementById("countryTitle");
          const popularList = document.getElementById("popularList");

          //htmlを書き換えて、国名を反映させる
          if (countryCode) {
            countryTitle.innerText = countryName;
            loadPopularVideos(countryCode);
          } else {
            countryTitle.innerText = "不明な国";
            popularList.innerHTML = '<p class="message">この場所の国情報を取得できませんでした。</p>';
          }

          // 地域情報の処理
          const regionTitle = document.getElementById("regionTitle");
          const searchList = document.getElementById("searchList");

          if (regionName) {
            // 地域名が取れた場合
            regionTitle.innerText = regionName;
            loadSearchVideos(regionName, countryCode);
            //searchList.innerHTML = '<p class="message">API節約のため制限しています</p>';
            console.log()
          } else {
            // 地域名が取れなかった場合
            console.warn("地域名（administrative_area_level_1）が見つかりませんでした");
            regionTitle.innerText = "不明な地域";
            searchList.innerHTML = '<p class="message">地域情報を取得できませんでした。</p>';
          }

        }).catch(e => {
          console.error("Geocoder error:", e);
          document.getElementById("searchList").innerHTML = '<p class="message">地図情報の取得に失敗しました。</p>';
        });
      }

      // -------------------------
      // 人気動画検索
      async function loadPopularVideos(regionCode) {
        try {
          const url = `https://www.googleapis.com/youtube/v3/videos?` +
          `part=snippet,contentDetails,statistics` +
          `&chart=mostPopular` +
          `&regionCode=${regionCode}` +
          `&maxResults=10` +
          `&key=${API_KEY}`;

          const response = await fetch(url);
          const data = await response.json();

          const filtered = (data.items || []).filter(item => 
            !isVertical(item) && !hasShortsTag(item)
          );
          displayVideos(filtered, "popularList");
        } catch (e){
          console.error("Popular Error:", e);
        }
      }

      // 地域名で検索
      async function loadSearchVideos(keyword, regionCode) {
        try {
          // regionCodeがあれば優先し、なければ無視して検索
          const regionParam = regionCode ? `&regionCode=${regionCode}` : "";

          const url = `https://www.googleapis.com/youtube/v3/search?` +
          `part=snippet&type=video&maxResults=10` +
          `&q=${encodeURIComponent(keyword)}` +
          `${regionParam}` +
          `&key=${API_KEY}`;

          const response = await fetch(url);
          const data = await response.json();

          const filtered = (data.items || []).filter(item => 
            !isVertical(item) && !hasShortsTag(item)
          );
          displayVideos(filtered, "searchList");
        } catch (e){
          console.error("Search Error:", e);
        }
      }

      // ----Shorts動画は検索されないようにする----
      // 縦横比でShortsか判定
      function isVertical(item) { 
        const t = item.snippet?.thumbnails?.high || item.snippet?.thumbnails?.medium;
        return t && t.height > t.width; 
      }

      // #shortsのハッシュタグが含まれているか判定
      function hasShortsTag(item) {
        const text = ((item.snippet?.title || "") + " " + (item.snippet?.description || "")).toLowerCase();
        return text.includes("#shorts");
      }
      // ------------------------------------------

      function displayVideos(items, listId) {
        const list = document.getElementById(listId);
        list.innerHTML = "";

        if (!items || items.length === 0) {
          list.innerHTML = `<p class="message">動画が見つかりませんでした。</p>`;
          return;
        }

        items.forEach(item => {
          const videoId = item.id?.videoId || item.id;
          if (!videoId || typeof videoId !== 'string') return;

          const thumb = item.snippet?.thumbnails?.medium?.url || "";
          const title = item.snippet?.title || "(タイトルなし)";
          const channel = item.snippet?.channelTitle || "";

          const a = document.createElement("a");
          a.className = "video-card";
          a.href = `https://www.youtube.com/watch?v=${videoId}`;
          a.target = "_blank";
          a.innerHTML = `
          <img src="${thumb}">
          <p><strong>${title}</strong></p>
          <p style="font-size:12px; color:#555;">${channel}</p>
          `;
          list.appendChild(a);
        });
      }
    </script>

    <script>
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${API_KEY}&callback=initMap`;
      script.async = true;
      script.defer = true;
      document.body.appendChild(script);
    </script>
  </body>
</html>
