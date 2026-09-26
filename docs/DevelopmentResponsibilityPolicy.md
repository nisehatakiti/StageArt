# StageArt Development Responsibility Policy

Version : 1.0
Status : Confirmed

## 1. Purpose
StageArtの開発におけるClaudeとChatGPTの役割を明確に分離し、機能仕様・API・データ構造とWeb UI/UXが、互いの担当範囲を推測によって変更しないようにする。StageArt WebはPCブラウザで業務を快適に行うことを基本とする。スマホアプリは別建てのUIとして扱い、Web UIをスマホアプリの縮小版として設計しない。

## 2. Responsibility Split
### Claude — Function / Data / API
Claudeを主担当とする範囲：DB設計、Migration、Domain、Application / UseCase、Repository、REST API、API validation、認証・権限、データ取得・保存、業務ルール、基本的な入力項目の実装、APIとフォームの接続、テスト。Claudeは機能として入力・取得・保存できる状態までを作る。最終的なWeb UIのレイアウトやStageArtらしい見た目を完成させることは担当しない。

### ChatGPT — Web UI / UX
ChatGPTを主担当とする範囲：StageArt Webのページレイアウト、セクション構成、項目の横並び・縦並び、入力欄の幅・高さ、ラベル・補足・エラーの配置、ボタン配置・デザイン、フォーム共通コンポーネント、テーブル・一覧・詳細画面の見せ方、余白・タイポグラフィ・色・境界線・角丸、ローディング・空状態・成功表示等のUI、PCブラウザを基準としたレスポンシブWeb UI、StageArt Web全体のUI統一。

## 3. Boundary Rule
双方は相手の担当領域を推測で変更しない。ChatGPTはDB、Domain、UseCase、REST API仕様、権限、ライフサイクル、業務ルール、データ項目の意味、保存形式を勝手に変更しない。ClaudeはChatGPT担当のWeb UIについて、機能実装のついでにレイアウト、入力欄サイズ、セクション構成、ボタン配置、色・余白・タイポグラフィ、共通UIコンポーネントを独自判断で変更しない。

## 4. Specification Rule
既存コード・既存UI・既存APIは、それ自体を確定仕様とはみなさない。確認済み仕様と既存実装が異なる場合、推測で統合・修正しない。不明点、仕様間の矛盾、担当領域をまたぐ変更が必要な問題は「要確認」として停止し、確認後に実装する。

## 5. Web UI Principle
StageArt Webは「スマホでも使えるWeb画面」ではなく、「PCブラウザで気持ちよく業務を進められるWebアプリ」を基本とする。共通フォームでは、情報をセクションで整理し、関連項目をPCでは横並びにし、入力内容に応じて入力欄の幅を変える。日付はカレンダー入力補助、時刻は時刻入力補助を提供する。長文は十分な入力領域を確保し、補足説明は入力欄付近、エラーは該当項目の近くに表示する。長いフォームは自然にスクロールでき、狭いブラウザ幅では必要に応じて縦並びへ切り替える。

## 6. Deployment Responsibility
開発環境へのデプロイはClaudeの担当とする。標準フローは、Claudeが機能・APIを実装 → テスト → ChatGPTへUI調整を引き継ぐ → ChatGPTがWeb UIを調整 → Claudeがcommit / push / dev環境へdeploy → dev.stageart.topで確認、とする。Production環境へのデプロイは明示的な指示がない限り行わない。

## 7. Development Principle
> Claude = StageArtの「中身」
> ChatGPT = StageArt Webの「使い心地」
双方は担当領域を尊重し、境界を越える変更は推測で行わない。