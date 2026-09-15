# StageArt Theme

StageArtCoreの公開データを表示するWordPressクラシックテーマです。

## 役割

Themeは表示だけを担当し、公演・メンバー・団体基本情報などの正本データはStageArtCore側に置きます。

- StageArtCore: データと公開URL、公開日時、業務ロジック
- StageArt Theme: ヘッダー、フッター、トップ、固定ページ、レスポンシブ表示
- StageArtTicket: チケット予約・受付（別プラグイン）

## 導入

`theme/stageart` ディレクトリをWordPressのテーマディレクトリへ配置して有効化します。

推奨固定ページ:

- `/members/` — 固定ページのスラッグを `members` にするとメンバー一覧テンプレートを使用
- `/contact/` — StageArtCoreが生成する連絡先ページを公開すると専用テンプレートを使用

メインメニューはWordPressの「メニュー」で `primary` に割り当てます。未設定の場合は基本メニューを表示します。

## StageArtCoreとの連携

StageArtCoreのProductionRouter / MemberRouterが `get_header()` / `get_footer()` を呼び出すため、公演・メンバーの専用URLにも本テーマの共通ヘッダー・フッターとCSSが適用されます。
