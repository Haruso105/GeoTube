# GeoTube
本Webアプリは個人開発となります。
## アプリ概要
地図上にピンを挿すことで、その土地に関連する動画を即座に発見できるWebアプリです。Google Maps APIで取得した位置情報をYouTube Data APIへ橋渡しし、直感的に動画検索を行うことができます。

## デモ動画
https://github.com/user-attachments/assets/1d5ffb09-6f41-4542-952d-3ffe94eb8d12

## 開発環境
### 使用ツール
- Visual Studio Code
- XAMPP

### 使用言語
- HTML5 / CSS3
- JavaScript (ES6)
- PHP Version 8.0.30

## 連携の仕組み
マップをクリックするだけで、以下の2種類の動画を自動的に抽出します。
・国のトレンド動画: 取得した「国コード（例: JP）」を元に、その国全体の人気動画を表示。
・地域関連動画: 取得した「行政区画名（例: 北海道）」をキーワードに、その土地に密着した動画を検索。

