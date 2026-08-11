# StageArt

舞台芸術団体向け統合運営プラットフォーム（WordPress Plugin）

---

# Vision

> StageArtは劇団の運営を管理するためのシステムではない。
>
> 舞台芸術に関わる人が、運営業務に追われることなく、創作活動に集中できる時間を増やすためのプラットフォームである。

劇団運営・公演制作・チケット予約・受付・稽古・会計・予算管理・広報・メンバー管理など、公演を成功させるために必要だが創作活動そのものではない業務を一つのプラットフォームに統合し、関わる人が本来最も価値を生み出す「創作」に集中できる環境を提供する。

最終的には舞台芸術向けERPとして、劇団設立から活動計画、公演企画・制作、稽古、チケット販売、公演実施、収支・予実分析、次回公演の企画までを一気通貫で支援することを目指す。

詳細：
`docs/01-Vision.md`

---

# Golden Rule

**利用者はドメインモデルを意識しない。**

利用者が入力するのは、

- 「劇団を作る」
- 「公演を作る」
- 「稽古日程を調整する」
- 「チケットを販売する」
- 「受付する」
- 「公演の予算を作る」

といった「やりたいこと」である。

Project・Production・Participant・Reservation・Historyなどの内部Domainは、StageArtが責任を持って管理する。

新機能を追加する際も、利用者に内部構造を意識させる設計は避ける。

詳細：
`docs/00-GoldenRule.md`

---

# Design Principles

StageArtの設計・実装は、以下の原則に従う。

| # | Principle | 要旨 |
|---|-----------|------|
| 1 | Domain First | 画面やDBではなくDomain Modelを中心に設計する |
| 2 | User First | 利用者は内部Domainを意識しない |
| 3 | Simple UI, Rich Domain | 複雑さはDomainが吸収し、UIはシンプルに保つ |
| 4 | Multi Tenant | Organization単位で安全にデータを分離する |
| 5 | API First | 全機能をREST APIとして提供する |
| 6 | Mobile Ready | スマートフォン利用を前提とする |
| 7 | Event Driven | 利用者の操作を起点に必要なデータを生成・更新する |
| 8 | Single Source of Truth | 同じ情報を複数箇所で管理しない |
| 9 | Fact and Artifact | 事実と成果物を区別する |
| 10 | Backward Compatibility | 既存データを破壊しない |
| 11 | Plugin First | WordPress Pluginとして実装する |
| 12 | Framework Independent | Domain層はWordPressに依存させない |
| 13 | Incremental Development | 小さく作って改善する |
| 14 | Blueprint First | 実装より先にBlueprintを更新する |
| 15 | Theatre First | 「創作活動への集中」を最優先する |
| 16 | UI Theme and Design System | Theme Tokenと共通ComponentによってUIを統一する |

詳細：
`docs/02-DesignPrinciples.md`

---

# Business Flow

StageArtでは、団体管理者、代理人、メンバー、キャスト、スタッフ、受付担当、観客など、様々な立場の利用者が存在する。

利用者はシステムの内部構造を意識せず、自分の目的に応じた操作を行う。

主なBusiness Flowは以下の通り。

## アカウント

- Googleアカウントまたはメールアドレスで登録
- Personを作成
- 複数Organizationへの所属
- Organization Contextの切り替え

## 団体

- 団体登録
- 団体情報管理
- メンバー管理
- Role管理
- 団体規約管理
- 年度計画管理
- 団体会計
- 備品管理
- 過去活動・公演履歴管理

## 公演・活動

- 活動・公演登録
- Project / Production管理
- 公演情報管理
- Category / Genre管理
- キャスト・スタッフ等のParticipant管理
- 公演単位の代理権限管理
- 公開ページ生成
- 内部Portal提供

## 稽古

稽古日程は、

候補日提示
↓
① 日程調整のための出欠
↓
稽古日確定
↓
Google Calendar連携
↓
② 確定した稽古への参加確認
↓
稽古実施

という流れで管理する。

Rehearsalは候補日から作成する場合だけでなく、直接作成することもできる。

Google Calendarへの登録は、稽古参加者以外にも行える。

## ファイル共有

公演・活動ごとに内部ファイルを管理する。

実ファイルはGoogle Driveと連携し、StageArtではファイル情報と共有情報を管理する。

主な用途：

- 台本
- 稽古資料
- 制作資料
- その他公演関連ファイル

## 内部連絡

管理者または権限を持つ代理人から、

- キャスト
- スタッフ
- 制作
- その他関係者

などを対象として一括連絡を行う。

連絡内容および送信履歴を管理する。

## タイムテーブル

稽古日や小屋入り後などの日別タイムテーブルを作成・共有する。

- 時刻
- 内容
- 場所
- 担当
- 対象者
- 備考

などを管理する。

## チケット・予約

- 公演ごとのTicket Master作成
- Ticket Type設定
- Price設定
- チケット予約
- QRチケット発行
- 公演当日の受付

チケットの種類と価格は、公演ごとに設定する。

## 公演当日

受付では、

- QRコード
- 予約番号
- 氏名

などを利用して来場確認を行う。

受付完了後、来場履歴を記録する。

## 観客

一般観客はStageArtの内部Portalへ入る必要はない。

公演ページからチケットを予約・購入し、QRチケットを利用できる。

StageArtユーザーとして登録した観客はPersonとして管理され、

- 自分の観劇履歴
- 過去に観劇した公演

などを確認できる。

観劇履歴は、予約・受付などのFactから自動的に蓄積する。

同行者を管理するCompanion Domainは設けない。

## 公演終了後

公演終了後は、

- 出演履歴
- 観劇履歴
- 公演履歴
- 公演収支
- 公演予実

などを確認できる。

公演には終了予定時刻を設定する。

終了予定時刻を基準として、公演直後にアンケート依頼メールを送信する。

アンケート回答は原則非公開とし、代表者が公開対象として選択した回答のみ公開できる。

---

# Accounting / Budget

StageArtでは、団体会計と公演単位の予算・実績を管理する。

## 団体会計

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line

などを利用して、団体全体の会計を管理する。

Journal Entry Lineは費目ごとに分ける。

貸借区分はFlagとして管理する。

固定資産管理や減価償却は対象としない。

## 公演予算

一つのProductionに複数のBudgetを作成できる。

Budgetには利用者が自由に名称を付ける。

例：

- A会場案
- B会場案
- 一日2公演案

Budgetでは公演に必要な収入・支出を計画する。

## 公演実績

公演終了後、実際に発生した収入・支出をActualとして記録する。

## 公演予実

BudgetとActualを比較し、

- 予算
- 実績
- 差額

を費目ごとに確認できる。

目的は単に利益・損失を確認することではなく、予算と実績の差を把握し、次回公演の計画へ活用することである。

---

# Equipment

備品管理は資産管理を目的としない。

目的は、

> 「前の公演で使ったあの備品は、今どこにあって、誰が持っているのか」

を明らかにすることである。

管理する情報：

- 名称
- 分類
- 保管場所
- 現在の管理者
- 状態
- 備考

状態には、

- 使用可能
- 貸出中
- 不明
- 廃棄

などを設定する。

取得価格・資産価値・減価償却は管理しない。

---

# Person / Organization

PersonとOrganizationは別の軸として管理する。

## Person

StageArt上の個人Identity。

PersonはOrganizationとは独立して存在する。

一人のPersonが複数のOrganizationに所属できる。

## Profile

ProfileはPerson自身が作成・編集できる。

出演履歴などの自動生成情報とは別に、本人がプロフィール情報を管理する。

## Organization

劇団・団体などの活動主体。

## Membership

PersonとOrganizationの所属関係を表す。

同じPersonでも、Organizationごとに異なるRoleを持つことができる。

例：

劇団A
→ 管理者

劇団B
→ キャスト

## Participant

Productionへの参加という事実を表す。

ParticipantのSubjectには、

- Person
- Organization

を指定できる。

そのため、

- キャスト
- スタッフ
- 客演者
- 外部団体
- 制作会社
- 協力団体

などを統一的に管理できる。

---

# History

HistoryはStageArtにおける重要なDomainである。

Historyには、

- 出演履歴
- スタッフ履歴
- 観劇履歴
- その他活動履歴

などが含まれる。

Historyは、StageArt上で発生したFactから自動的に生成される履歴と、利用者自身が登録する過去の活動履歴の両方を扱う。

例：

Participant
→ 出演・スタッフ履歴

Reservation
→ Check In
→ 観劇履歴

利用者がStageArt上で発生した活動を手入力する必要をできるだけ減らす。

---

# Domain Model

基本的なDomain構造は以下の通り。

Organization
│
└─ Project
    │
    └─ Production
        ├─ Performance
        │   └─ Reservation
        │       └─ Issued Ticket
        │           └─ Check In
        │
        ├─ Participant
        │
        ├─ Category
        │
        ├─ Genre
        │
        ├─ Ticket Master
        │   ├─ Ticket Type
        │   └─ Price
        │
        ├─ Rehearsal Candidate
        │   └─ Rehearsal Availability
        │
        ├─ Rehearsal
        │   └─ Rehearsal Attendance
        │
        ├─ Timetable
        │
        ├─ Budget
        │   └─ Budget Item
        │
        ├─ Production Actual
        │
        ├─ Budget vs Actual
        │
        ├─ Document
        │
        ├─ Announcement
        │
        └─ Survey
            └─ Survey Response

Person
│
├─ Profile
│
├─ Membership
│   └─ Organization
│
└─ History

Participant
│
└─ Subject
    ├─ Person
    └─ Organization

Organization
├─ Membership
├─ Regulation
│   └─ Regulation Version
├─ Accounting Period
│   └─ Journal Entry
│       └─ Journal Entry Line
└─ Equipment
    └─ Equipment History

---

# Important Domain Rules

## PersonとOrganizationは別軸

Personは個人Identity。

Organizationは団体Identity。

所属関係はMembershipで表現する。

## OrganizationとProjectは親子関係

基本構造は、

Organization
↓
Project
↓
Production

とする。

## Productionへの参加はParticipant

Productionへの参加情報はParticipantを正本とする。

## Historyは必須

活動履歴はStageArtの重要なDomainとして扱う。

## Profileは本人が管理する

Profileは自動生成だけではなく、Person本人による入力・編集を許可する。

## RehearsalはCandidate必須ではない

Rehearsalは、

Candidate
↓
Availability
↓
Rehearsal

でも、

直接
↓
Rehearsal

でも作成できる。

## TicketはProduction単位

Ticket TypeとPriceの組み合わせはProductionごとに管理する。

## Budgetは複数案を持てる

一つのProductionに複数のBudgetを持つことができる。

## AccountingとBudgetは別

Budgetは計画。

Accounting / Actualは実績。

両者をBudget vs Actualで比較する。

## Equipmentは資産管理ではない

Equipmentは金額ではなく、

- 所在
- 管理者
- 状態

を管理する。

## 一般観客とStageArtユーザーを区別する

一般観客はPortalへの登録を必要としない。

StageArtユーザーとして登録した観客はPersonとして管理され、自身の観劇履歴を確認できる。

## Companionは管理しない

Reservationに同行者を登録するCompanion Domainは設けない。

## Category / Genre

CategoryとGenreはProductionの属性として管理する。

## Seats

座席指定は将来実装する。

Seats / ReservationSeatについては将来の拡張を前提とするが、現在のBetaでは実装しない。

## SNS

StageArtはSNS投稿内容そのものをDomainとして管理しない。

必要なSNS連携・投稿機能を提供するが、SNS投稿本文等をStageArtの正本として保持しない。

---

# External Integration

StageArtは外部サービスと連携する。

主な対象：

- Google Drive
- Google Calendar
- SNS

## Google Drive

公演関連ファイルの保存先として利用する。

StageArtでは、

- ファイル情報
- 公演との関連
- 共有対象
- 外部ファイルへの参照

などを管理する。

## Google Calendar

確定したRehearsalをGoogle Calendarへ連携する。

Google Calendarへの登録対象は、稽古参加者に限定しない。

## SNS

OrganizationやProductionの広報を目的としてSNS連携・投稿機能を提供する。

SNS上の投稿内容自体をStageArtのDomainとして管理しない。

---

# Public / Internal Portal

StageArtでは、公演・団体の公開領域と内部領域を分ける。

## Public

一般公開する情報。

Organizationでは、

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

などを公開できる。

Productionでは、

- 公演情報
- 出演者・スタッフ等の公開情報
- チケット情報
- 予約情報

などを公開する。

## Internal

公演関係者向けの内部Portal。

主な機能：

- 稽古管理
- 稽古日程
- タイムテーブル
- ファイル共有
- 内部お知らせ
- 公演関連情報

一般観客はInternal Portalを利用しない。

---

# Beta Scope

Betaでは、公演を最初から最後まで運営できることを優先する。

主な対象：

- Account / Person
- Organization
- Membership
- Role / Delegate
- Project / Production
- Participant
- Profile
- Category / Genre
- Performance
- Ticket
- Ticket Type / Price
- Reservation
- Issued Ticket
- Check In
- History
- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Google Calendar連携
- Timetable
- Google Drive連携
- Document
- Announcement
- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement
- Accounting
- Equipment
- Regulation
- Public Page
- Internal Portal
- Survey

---

# Future Scope

将来的に以下へ対応する。

- Seats / ReservationSeat
- より高度な座席管理
- ファンクラブ
- グッズ販売
- 高度な会計機能
- その他の外部サービス連携
- モバイルアプリ
- LINE連携
- Organization / ProductionごとのTheme
- Dark Mode
- Custom Theme
- Theme Preset

---

# Repository Structure

```text
StageArt/
├── docs/                       # Blueprint（設計のSingle Source of Truth）
│   ├── 00-GoldenRule.md
│   ├── 01-Vision.md
│   ├── 02-DesignPrinciples.md
│   ├── 03-BusinessFlow.md
│   ├── 04-DomainModel/
│   └── 05-ERDiagram/
├── plugin/                     # WordPress Plugin本体
│   ├── stageart.php
│   ├── uninstall.php
│   ├── composer.json
│   ├── src/
│   │   ├── Domain/             # ドメイン層（WordPress非依存）
│   │   ├── Application/        # アプリケーション層
│   │   ├── Infrastructure/     # インフラ層
│   │   └── Presentation/       # REST API / UI
│   ├── assets/
│   ├── templates/
│   └── languages/
├── CLAUDE.md
└── README.md
```

---

# Development Principle

コードを書く前にBlueprintを更新する。

BlueprintはStageArtの設計におけるSingle Source of Truthである。

実装はBlueprintを実現するための手段であり、Blueprintと実装に矛盾がある場合はBlueprintを基準として見直す。

詳細な設計は、

- `docs/01-Vision.md`
- `docs/02-DesignPrinciples.md`
- `docs/03-BusinessFlow.md`
- `docs/04-DomainModel/`
- `docs/05-ERDiagram/`

に定義する。
