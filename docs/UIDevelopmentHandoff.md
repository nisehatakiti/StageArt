# StageArt UI Handoff Instructions

Version : 1.0
Status : Confirmed

## 1. Purpose
ClaudeがStageArtのAPI・基本入力要素を実装し、ChatGPTがStageArt WebのUI/UXを整える際の引き継ぎ手順を定める。

## 2. Claudeの完了条件
DB / Domain / UseCase / Repository、REST API、API validation、必要な認証・権限、基本入力項目の表示・入力・保存、APIエラー返却、関連テストが成立した状態でUI担当へ引き継ぐ。この段階のUIは暫定的なものでよく、最終的なレイアウト・見た目はChatGPTが整える。

## 3. Claude → ChatGPT 引き継ぎ項目
### Git
Repository、Branch、Base Commit、Final Commit、Working Tree status
### 対象画面
画面名、Route / URL、一覧 / 詳細 / 作成 / 編集等の種別
### 入力項目
項目名、型、必須 / 任意、null可否、文字数・数値・形式等の制約、業務上の意味
### API
Endpoint、HTTP Method、Request、Response、Validation、Error response
### UI
現在の画面状態、暫定UIかどうか、既知のUI問題、スクリーンショット
### テスト
PHPUnit、Jest、TypeScript、その他

## 4. ChatGPTの作業
引き継ぎ情報をもとにWeb UIのみを整える。確認対象はページ構造、セクション、ラベル、補足、入力欄幅、日付 / 時刻入力、エラー表示、ボタン、余白、タイポグラフィ、PCブラウザでの操作性、ブラウザ幅変化への対応、StageArt全体とのデザイン統一。

## 5. ChatGPTの禁止事項
UI改善を理由に、確認なしでDB、Domain、UseCase、REST API、権限、ライフサイクル、業務ルール、データ項目の意味、保存形式を変更しない。必要な場合は「要確認」としてClaudeへ返す。

## 6. ChatGPT → Claude 返却項目
対象画面、UI変更内容、共通UI部品の変更、API / DB / Domain等の変更有無、Commit SHA、Working Tree status、実機・ブラウザ確認事項。

## 7. 問題の返却先
UI → ChatGPT。API / DB / Domain / UseCase / 権限 / 業務ルール → Claude。境界・仕様不明 → 要確認。推測で実装しない。

## 8. デプロイ
デプロイはClaudeが担当する。UI変更後も必要なcommit、push、開発環境へのdeployはClaudeが行う。開発環境はWeb: dev.stageart.top、API: dev-api.stageart.top/wp-json/stageart/v1。Production環境へのdeployは明示的な指示がない限り行わない。

## 9. 標準フロー
Claude → 機能/API実装 → テスト → UI引き継ぎ → ChatGPT → Web UI調整 → Claude → commit / push / deploy → dev.stageart.top確認

## 10. 基本原則
> ClaudeはStageArtの機能・データ・APIを担当する。
> ChatGPTはStageArt WebのUI・UXを担当する。
> 双方は相手の担当領域を推測で変更しない。

既存実装は確定仕様とは限らない。確認済み仕様と既存実装が異なる場合、勝手に統合・修正せず要確認とする。