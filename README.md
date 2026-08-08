# StageArt

舞台芸術団体向け統合運営プラットフォーム（WordPress Plugin）

## Vision

> StageArtは劇団の運営を管理するためのシステムではない。
> 舞台芸術に関わる人が、運営業務に追われることなく、創作活動に集中できる時間を増やすためのプラットフォームである。

劇団運営・公演制作・チケット予約・受付・会計・広報・メンバー管理など、公演を成功させるために必要だが創作活動そのものではない業務を一つのプラットフォームに統合し、関わる人が本来最も価値を生み出す「創作」に集中できる環境を提供する。

最終的には舞台芸術向けERPとして、劇団設立から公演企画・制作・チケット販売・収支分析・次回公演の企画までを一気通貫で支援することを目指す（詳細: [`docs/01-Vision.md`](docs/01-Vision.md)）。

## Golden Rule

**利用者はドメインモデルを意識しない。**

利用者が入力するのは「劇団を作る」「公演を作る」「チケットを販売する」といった "やりたいこと" であり、Project・Production・Reservation・History などの内部ドメインは StageArt が責任を持って自動生成・管理する。新機能を追加する際も、利用者に内部構造を意識させる設計は欠陥とみなす（詳細: [`docs/00-GoldenRule.md`](docs/00-GoldenRule.md)）。

## Design Principles

すべての設計・実装が従う15原則（詳細: [`docs/02-DesignPrinciples.md`](docs/02-DesignPrinciples.md)）。

| # | Principle | 要旨 |
|---|-----------|------|
| 1 | Domain First | 画面やDBではなくDomain Modelを中心に設計する |
| 2 | User First | 利用者は内部ドメインを意識しない |
| 3 | Simple UI, Rich Domain | 複雑さはDomainが吸収し、UIはシンプルに保つ |
| 4 | Multi Tenant | 全データをOrganization単位で分離管理する |
| 5 | API First | 全機能をREST APIとして提供する |
| 6 | Mobile Ready | スマートフォン利用（受付・QRチケット）を前提とする |
| 7 | Event Driven | 利用者の操作を起点に必要なデータを自動生成する |
| 8 | Single Source of Truth | 同じ情報を複数箇所で管理しない |
| 9 | Fact and Artifact | 事実（予約・出演・受付）と成果物（QRチケット・レポート等）を区別する |
| 10 | Backward Compatibility | 既存データを破壊しないアップデートを行う |
| 11 | Plugin First | WordPress Pluginとして実装する（WordPress=CMS） |
| 12 | Framework Independent | Domain層はWordPressに依存させない |
| 13 | Incremental Development | MVP優先、小さく作って改善する |
| 14 | Blueprint First | 実装より先にBlueprint（`docs/`）を更新する |
| 15 | Theatre First | 判断に迷ったら「創作活動への集中」を最優先する |

## Business Flow

利用者の立場ごとの主な活動（詳細: [`docs/03-BusinessFlow.md`](docs/03-BusinessFlow.md)）。

- **アカウント登録**: Google／メールで登録すると Person が自動生成される
- **劇団を運営する**: 劇団情報登録、メンバー管理、公演登録、チケット・来場者管理
- **舞台人として活動する**: プロフィール作成、劇団所属、客演。出演実績は公演情報から自動更新される
- **観客として利用する**: 公演・劇団・出演者を探し、チケット予約、QRチケット表示、観劇履歴確認
- **公演当日**: QRコード／予約番号／氏名検索で受付し、来場履歴を記録
- **公演終了後**: 出演実績・来場履歴・公演履歴を自動更新（将来は収支・アンケート・分析へ連携）

## Domain Model

（詳細: [`docs/04-DomainModel/README.md`](docs/04-DomainModel/README.md)、Person詳細: [`docs/04-DomainModel/person.md`](docs/04-DomainModel/person.md)）

```
Organization
      │
   Project
      │
 Production
 ├── Performance ── Seats / Reservation
 ├── Participant (Person または Organization)
 ├── Category
 ├── Genre
 └── Tag

History
```

- **Organization**: 劇団・ユニット・制作会社などの団体
- **Project**: 公演制作プロジェクト。利用者が「公演を作る」と実行すると内部で自動生成される（Internal Domain）
- **Production**: 観客へ公開される「公演」。複数の Performance を持つ
- **Performance**: 実際の公演回（例: 8/1 14:00）。Seats・Reservation が紐付く
- **Reservation**: 予約。自由席／指定席を区別し、受付は Status 変更で管理する（CheckIn ドメインは持たない）
- **Participant**: 出演者・スタッフ・協賛企業・会場提供などを統一的に管理する概念。Person または Organization に紐付き、Category/Role で役割を表現する
- **Person**: 舞台芸術に関わる人物そのもの。職種を持たず、役割は Participant として表現する。PersonID で識別し、表示名の変更に影響されない。StageArt利用の有無に関わらず登録でき、Account とは独立して管理される（Identity Linking により、AIが候補提示 → 運営確認 → 本人承認、というフローで紐付ける）
- **History**: Participant/Reservation から自動生成される活動履歴（AUTO）と、利用者が登録する過去の活動（MANUAL）

## MVP Scope

| Version | 提供機能 |
|---------|----------|
| 1.0 (MVP) | 劇団登録・公演登録・チケット予約・QRチケット・当日受付 |
| 1.5 | プロフィール・出演実績・稽古管理 |
| 2.0 | 予算管理・収支管理・劇団会計 |

## Repository Structure

```
StageArt/
├── docs/                       # Blueprint（設計のSingle Source of Truth）
│   ├── 00-GoldenRule.md
│   ├── 01-Vision.md
│   ├── 02-DesignPrinciples.md
│   ├── 03-BusinessFlow.md
│   └── 04-DomainModel/
├── plugin/                     # WordPress Plugin本体
│   ├── stageart.php            # プラグインエントリーポイント
│   ├── uninstall.php
│   ├── composer.json
│   ├── src/
│   │   ├── Domain/             # ドメイン層（WordPress非依存）
│   │   ├── Application/        # アプリケーション層
│   │   ├── Infrastructure/     # インフラ層（WordPress依存はここに閉じ込める）
│   │   └── Presentation/       # プレゼンテーション層（REST API / 画面）
│   ├── assets/                 # CSS / JS / images
│   ├── templates/
│   └── languages/
├── CLAUDE.md
└── README.md
```

## 開発の原則

コードを書く前に Blueprint（`docs/`）を更新する（Principle 14: Blueprint First）。実装はBlueprintを実現するための手段であり、Blueprintが唯一の設計基準である。
