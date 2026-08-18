# StageArt Blueprint

# Domain Consistency Policy : Production

Version : 1.0

---

# Purpose

本書はProduction Domainについて、現在のStageArt Canonical Domain Model、DomainMap、Project Policyおよびこれまでに確定した公演仕様との整合性を定義する。

既存Production Domainの基本設計を維持しつつ、後から確定したProject、Slug、公開状態、Venue、Budget、Member、Performance、Ticket、Reservation等の仕様を優先仕様として整理する。

---

# Canonical Position

ProductionはProjectに所属する具体的な公演・活動の実施単位である。

基本構造：

Organization
    ↓
Project
    ↓
Production

ProductionはOrganizationに直接所属するものとして扱わない。

通常の小劇場公演ではProjectとProductionが実質的に一対一となることを許容する。

複数の実施地・実施形態を持つ企画では、一つのProjectに複数Productionを所属させる。

---

# Production Identity

Productionはシステム上のProductionIdによって一意に識別する。

Productionには、一般公開URLに使用するProduction Slugを設定できる。

Production SlugはOrganization Slugと組み合わせてPublic URLを構成する。

基本Public URL：

/StageArt/[Organization Slug]/[Production Slug]/

Production Slugは同一Organization内で一意であることを基本とする。

Slug登録・変更時には一意性を確認し、既存Productionとの衝突を許可しない。

PersonにはPublic Slugを持たせない。

---

# Production Creation

Production作成はSetup Wizard形式で行う。

基本的な入力順序：

1. Project選択または新規Project作成
2. Production基本情報
3. Production Manager
4. 情報公開設定
5. Budget設定（Accounting有効時）
6. Member登録
7. Venue設定
8. Ticket設定
9. Performance設定

Production作成後も各情報は管理画面から追加・修正できる。

Historical Productionについても、基本的に同じProduction Setup Flowを利用する。

---

# Production Basic Information

基本情報として以下を管理する。

- Production Name
- Description
- Production Slug
- Flyer Front
- Flyer Back
- Status
- Public Visibility

Production作成時点でフライヤー画像が存在する場合はアップロードできる。

フライヤー画像は表面・裏面の2枠を持つ。

---

# Production Manager

ProductionにはPrimary Managerを設定する。

Production Managerとして指定できるのはStageArt Accountを所持するユーザーに限る。

Production ManagerはProductionの管理権限を持つ。

Production Managerは後から別のStageArt Account保有者へ移譲できる。

Production ManagerとOrganization代表者・Organization Ownerは別概念である。

必要に応じてProductionDelegateによってProduction管理業務の一部を他のPersonへ委任できる。

---

# Public Visibility

ProductionにはLifecycleとは別に一般公開状態を持たせる。

情報公開状態の変更は管理者が公開タイミングを判断して行う。

Productionが情報公開されていない場合、一般向けProduction Public Pageを生成・公開しない。

Public VisibilityとProduction Lifecycleを混同してはならない。

---

# Public Page Generation

Production Public Pageは、情報公開が有効になった時点で公開対象となる。

基本URL：

/StageArt/[Organization Slug]/[Production Slug]/

Production Public Pageの基本表示内容は、以下を中心とする。

- フライヤー
- 作・演出等の基本クレジット
- 公演日時
- チケット情報
- 会場情報

公演概要やメンバー等の詳細情報は、Public Page内の別メニュー・別ページへ分離できる構造とする。

---

# Coming Soon

Productionが公開状態であっても、公開に必要な情報が未確定の場合はPublic Pageを生成できる。

その場合、未確定部分はComing Soonとして表示する。

対象例：

- Venue未確定
- Performance未確定
- Ticket未確定

したがって、公開前にページが存在してしまうことを防ぎつつ、公開後の情報追加を段階的に行える。

---

# Venue

Productionは初期仕様では一つのVenueを持つ。

VenueはProductionに直接紐づく。

PerformanceはVenueを直接保持しない。

Productionに設定されたVenueを、そのProductionに属するPerformanceが利用する。

東京公演・大阪公演等の複数会場を含む企画は、Project配下に複数Productionを作成して表現する。

Production Venueの基本情報には、少なくとも以下を想定する。

- Venue Name
- Postal Code
- Address
- Venue URL
- Map Information
- Notes

---

# Performance

Productionには複数のPerformanceを設定できる。

Performanceは具体的な公演回を表す。

基本情報には少なくとも以下を持たせる。

- Performance Date
- Open Time（必要に応じて）
- Start Time
- End Time（必要に応じて）
- Status
- Reservation Capacity

PerformanceごとのVenue指定は行わない。

Performance作成時にはProductionの標準Reservation Capacityを継承し、必要な場合はPerformance単位で変更できる。

---

# Reservation Capacity

Reservation Capacityの実際の適用単位はPerformanceとする。

Productionには標準Reservation Capacityを設定できる。

Performance作成時にProductionの標準値を継承する。

Performance側で個別Capacityを設定できる。

Productionの標準Capacityを後から変更しても、既存Performanceの個別設定を自動的に上書きしない。

ReservationはPerformanceのCapacityを基準に受け付ける。

Capacity = 0は無制限を意味しない。

---

# Ticket

TicketはProductionを基本的な券種定義のScopeとする。

Productionでは、必要に応じてTicketの料金体系を設定する。

Ticket料金は、以下の二軸を基本として構成できる。

第1軸：料金区分

- 一般
- 学生
- その他

第2軸：販売区分

- 前売
- 当日
- その他

二軸は固定必須ではない。

以下をすべて許容する。

- 両方使用
- 第1軸のみ使用
- 第2軸のみ使用
- 両方使用しない

両軸を使用する場合はマトリックスとして扱う。

例：

|  | 前売 | 当日 |
|---|---:|---:|
| 一般 | ¥3,000 | ¥3,500 |
| 学生 | ¥2,000 | ¥2,500 |

不要な組み合わせは必ずしも設定しなくてよい。

標準候補以外の区分も追加できる。

Ticketの詳細な販売・予約ルールはTicket DomainおよびReservation Domainで定義する。

---

# Reservation

Reservationは具体的なPerformanceに対する予約Factとして扱う。

基本構造：

Production
    ↓
Performance
    ↓
Reservation

Reservationは必要に応じてTicketと関連付ける。

ProductionはReservationの親Scopeではあるが、定員判定の実体はPerformance単位とする。

Reservationの定員判定はPerformanceのReservation Capacityを利用する。

---

# Member / Participant

Productionに参加するPersonはParticipantとして管理する。

Production Member表示はParticipant情報を基礎とする。

Production単位のMemberは、団体所属の有無に関係なく登録できる。

所属Organizationの承認済みMembershipが存在するPersonについては、Member表示上で「名前（所属）」の形式を利用できる。

所属がないPersonは名前のみを表示する。

Production Memberは、個々の公演における座組を表すため、プロデュース公演等で毎回異なる構成を許容する。

Production Member情報は後から追加・修正できる。

Memberの並び順はProduction管理者が変更できる。

退団等によって現在のOrganization Membershipが終了しても、過去ProductionのMember表示は当時の所属表記をSnapshotとして維持する。

複数所属を初期仕様では考慮しない。

画像が存在するPersonについては、Production Member Pageでバストアップ画像の縮小版を表示できる。

画像がない場合は名前のみ表示する。

Person Public Profileへ遷移可能な関係が存在する場合、Member Pageからリンクできる。

---

# Person Profile Relationship

PersonはProductionにおける参加履歴とは別に、自身のプロフィール情報を管理できる。

Person Public Profileでは、基本的に以下を表示できる。

- 氏名
- 所属
- プロフィール写真
- 出演・スタッフ参加履歴

任意入力項目として、以下を持つことができる。

- 年齢
- 生年月日
- 出身地
- 身長
- 体重
- BWH
- 足のサイズ
- 特技
- 資格

各項目について公開／非公開を選択できる。

PersonにはPublic Slugを持たせない。

---

# Historical Production

StageArt導入前の過去公演もProductionとして登録できる。

Historical Productionも基本的に通常Productionと同じDomain構造を利用する。

ただし、過去公演では現在のStageArt運用上存在しない情報が未入力でも登録できるものとする。

過去公演の公開可否は管理者が制御できる。

表示したくない過去公演はPublic Pageへ表示しない。

---

# Survey / Customer Voice

過去公演では、アンケート内容の抜粋をPublic Pageへ掲載できる機能を必須とする。

ただし、すべての過去公演でアンケートを公開することを必須とはしない。

管理者が掲載しない判断をした場合は、アンケート抜粋を公開しない。

Surveyの回答内容や匿名性等の詳細はSurvey Domainで定義する。

---

# Budget

ProductionはProduction Budgetを保持できる。

Project Budgetとは異なり、Production単位の計画を表す。

一つのProductionに複数Budgetを保持できる。

Budgetには利用者が設定するBudget Nameを持たせる。

Budget作成方法として以下を提供する。

- 過去・既存Budgetをコピー
- 標準テンプレートを利用
- 空のBudgetを作成

Production Budget入力では、標準的な収入・支出項目を利用できる。

チケット収入については、簡易的に想定集客数 × チケット単価で計画できる。

Budgetの詳細な入力UIおよびA4 PDF出力についてはBudget Management Policyに従う。

---

# Accounting Scope

Production単位の会計表示は、個別公演の予算・実績・決算を確認するためのScopeである。

Production ActualおよびProduction Settlementは独立した会計帳簿ではない。

Journal Entryを正本としてProduction ScopeからActualを集計する。

会計表示の役割分担：

Organization
    = 団体全体の財務状況

Project
    = 企画全体の予実管理

Production
    = 個別公演の決算・収支確認

同一の会計FactをProductionへ二重入力しない。

---

# Production Lifecycle and Public Visibility

Production LifecycleとPublic Visibilityは別概念として管理する。

Lifecycleは制作・公演活動そのものの状態を表す。

Public Visibilityは一般公開の状態を表す。

例えば、ProductionがPLANNING中でも情報公開を行うことができる。

逆に、Productionが存在していてもPublic VisibilityがOFFなら一般公開しない。

情報公開後、VenueやPerformance等の情報が未確定の場合はComing Soonで公開できる。

---

# Production Completion

Productionの公演終了後は、Production Actualを確認し、最終的なProduction Settlementを確認できる。

過去ProductionとしてPublic Pageを残す場合、管理者が公開対象として設定したものだけを表示する。

---

# Canonical Relationship Summary

```text
Organization
    │
    └── Project
          │
          └── Production
                ├── Venue
                ├── Participant
                ├── Performance
                │     └── Reservation
                ├── Ticket
                ├── Rehearsal
                ├── Timetable
                ├── Production Budget
                ├── Production Document
                ├── Announcement
                └── Survey
```

会計はJournal Entryを正本として各Scopeから集計する。

---

# Design Principle

ProductionはStageArtにおける「具体的な公演実施単位」である。

Projectが企画全体を束ね、Productionが実際の公演・活動を表現する。

Productionでは、公開ページ、会場、メンバー、Performance、Ticket、Reservation、Budget等を一体として管理できる一方、それぞれのDomainの責務を混在させない。
