# 団体ロゴのアップロード処理（縮小・検査）

仕様（ロゴは1枚、差し替え可）は `docs/15-OrganizationInformationScreen.md`。本書は、アップロード時の実装上の処理と制約を記録する。

実装：`plugin/src/Infrastructure/WordPress/Media/WordPressOrganizationLogoUploader.php`、
`WordPressOrganizationLogoShrinker.php`、`plugin/src/Application/Organization/OrganizationLogoResizePolicy.php`。
対象は新しくアップロードされたロゴのみ。保存済みのロゴは変更しない。

## 受け付け条件（従来どおり）

- 形式：JPEG / PNG / WebP / GIF（ファイルの中身で判定）
- サイズ：5MB 以下

## 縮小

- JPEG / PNG / WebP で、長辺が 1024px を超えるものだけを、縦横比を保って長辺 1024px に縮小する。切り抜き・拡大はしない。
- 1024px 以下の画像と GIF（アニメーションを含む）は、アップロードされたまま保存する。
- 形式は変えない（PNG / WebP の透明部分は保持）。
- JPEG は EXIF の向き（Orientation）を反映してから縮小する。

## 縮小せずに元のまま保存する場合（ログに記録）

| 条件 | 理由 |
|---|---|
| PHP の `exif` 拡張が無い（`exif_read_data()` が使えない）環境で、長辺 1024px 超の JPEG | WordPress の GD / Imagick エディタはどちらも向きを `exif_read_data()` でしか読めない。再エンコードすると向き情報が失われ横向きになるため。元ファイルの EXIF でブラウザは正しく表示する |
| サーバーにその形式を扱える画像エディタが無い（`image_no_editor`） | 変更前と同じ動作 |

ログ例：`StageArt: Organization logo stored without resizing (image/jpeg 4000x3000, limit 1024px) - the PHP exif extension is not available, ...`

## JPEG の厳格検査（拒否される画像）

長辺 1024px 超の JPEG だけ、縮小前に GD の厳格モード（`gd.jpeg_ignore_warning=0`）で一度読み込み、libjpeg が警告を出すものは拒否する。
メッセージ：「画像ファイルが壊れているか、途中までしか送信されていません。別の画像をお試しください。」

拒否されるもの（WordPress 7.1.3 / PHP 8.2 / GD で確認）：

- 途中で切れたファイル（送信の中断など）
- 終了マーカー（EOI）が無いファイル
- 画像データの途中が壊れたファイル
- 終了マーカーの直前に余分なバイトがあるファイル（libjpeg の警告対象。**ブラウザでは表示できるが拒否される**）

拒否されないもの：

- 通常の JPEG（検証した 42 件、および末尾＝EOI の後ろにデータが付いた同 42 件。誤拒否 0 件）
- 長辺 1024px 以下の JPEG（検査しない。壊れていても従来どおり保存される）

## 環境による制約

| 環境 | 動作 |
|---|---|
| GD（JPEG 対応）あり | 上記の厳格検査を行う（Imagick で縮小する環境でも、検査は GD で行う） |
| GD の JPEG 対応が無い（Imagick のみ） | 厳格検査を行わず、ログに `GD JPEG support is not available - Organization logo truncation check skipped.` を記録して縮小を続ける。Imagick が壊れた JPEG をどう扱うかは**未検証** |
| `ini_set()` が使えない | 厳格検査を行わず、ログに記録 |
| `exif` 拡張なし | 長辺 1024px 超の JPEG は縮小しない（上記） |

**本番・開発サーバーの `exif` / GD / Imagick の有無と WordPress のバージョンは未確認**（リポジトリからは分からない）。推測で判断せず、配置前にサーバー上で読み取りのみで確認し、確認した内容と確認日を記録する（例：`php -m` で `exif`・`gd`・`imagick` の有無を見る。CLI と Web サーバーの PHP は設定が異なる場合があるため、WordPress の「サイトヘルス」→「情報」でも確認する。WordPress のバージョンは WP-CLI が使える場合 `wp core version`）。Imagick だけの環境が見つかった場合は、その環境で壊れた JPEG の扱いを検証してから配置する。
