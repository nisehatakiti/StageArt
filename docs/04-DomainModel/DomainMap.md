# StageArt Blueprint

# DomainMap

Version : 5.0

---

# Purpose

DomainMapはStageArt全体のDomain構造を俯瞰するための上位設計である。

ER図やDatabase設計、個別Domainの詳細Business Ruleを定義するものではない。

個別DomainのLifecycle、Permission、Event、Value Object、入力項目などは各Domain Modelで定義する。

本DomainMapでは、現在の確定仕様に基づくDomain間の責務と基本的な関係のみを定義する。

---

# 1. Core Structure

StageArtの基本構造は以下とする。

Organization
    ↓
Project
    ↓
Production
    ↓
Performance

- Organization = 団体・Tenant
- Project = 企画・活動全体を束ねる内部単位
- Production = 具体的な公演・実施単位
- Performance = Productionにおける個別の公演回

通常の単独公演ではProjectを利用者に強く意識させない。

Projectは、複数Productionを一つの企画として管理する場合に特に有効である。

例：

Project「河童ホームラン2027」
    ├─ Production「東京公演」
    └─ Production「大阪公演」

---

# 2. Authentication / Business Identity

Authentication IdentityとBusiness Identityを分離する。

UserAccount
    ↓
Person

- UserAccount = StageArtへの認証Identity
- Person = StageArt上の個人Identity

PersonはUserAccountを持たない状態も許容する。

外部Authentication ProviderはUserAccountに関連付ける。

例：

- Google
- Apple
- Microsoft
- Email / Password

Provider固有の処理はInfrastructure Layerが担当し、Domain LayerはProvider APIへ直接依存しない。

---

# 3. Person / Profile / History

Personを中心として個人情報を管理する。

Person
    ├─ Profile
    ├─ HistoricalActivity
    └─ Membership

Profileは本人が管理するプロフィール情報を表す。

プロフィールには、公開・非公開を選択できる各種個人情報を保持できる。

例：

- プロフィール画像
- 氏名
- 所属
- 年齢 / 生年月日
- 出身地
- 身長 / 体重
- BWH
- 足のサイズ
- 特技
- 資格
- 自己紹介

個人ページはSlugを持たない。

HistoricalActivityは本人が入力するStageArt外部を含む過去の出演・スタッフ等の履歴を表す。

StageArt上で発生したProduction参加実績とは区別する。

---

# 4. Organization / Membership

Organizationは舞台芸術活動を行う団体を表すTenantである。

Organization
    ↓
Membership
    ↓
Person

一人のPersonは複数Organizationに所属できる。

Membershipは、所属状態・所属期間・Organization内Role等を管理する。

Organizationの代表者・管理者はOrganization Scopeで管理する。

---

# 5. Authorization

Authorizationの主体はPersonとする。

Organization Scope：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

Production Scope：

Person
    ↓
ProductionDelegate
    ↓
Production
    ↓
Role
    ↓
Permission

UserAccountを直接Authorization主体としない。

同一Role DefinitionをOrganization ScopeとProduction Scopeで利用できる。

RoleAssignmentという独立Domain、およびDelegateRoleという別Role体系は作成しない。

ProductionにはPrimaryManagerを設定できる。

PrimaryManagerはProduction Scopeの主管理者であり、Organization Ownerとは異なる。

---

# 6. Project

ProjectはOrganizationに所属する活動・企画単位である。

Project
    ↓
Production (1..N)

一つのProjectに複数Productionを所属させることができる。

公演作成時には、既存Projectを選択するか、新規Projectを作成してProductionを所属させる。

Projectには、Project全体を対象とするBudgetを持つことができる。

Project Accountingでは、複数Productionを含む企画全体の予実を確認する。

---

# 7. Production

ProductionはProjectに所属する具体的な公演・実施単位である。

Production
    ├─ Participant
    ├─ ProductionDelegate
    ├─ Venue
    ├─ Performance
    ├─ Ticket
    ├─ Reservation (through Performance)
    ├─ Rehearsal
    ├─ Timetable
    ├─ Budget
    ├─ Document
    ├─ Announcement
    ├─ Survey
    └─ Public Page

ProductionにはSlugを持たせる。

SlugはOrganization内で一意性を確認する。

Productionの一般公開は、情報公開設定によって制御する。

情報公開前は一般向けProduction Pageを生成しない。

情報公開後でも、会場・公演回・チケット等の情報が未確定の場合は、Public Page上でComing Soon表示を行える。

---

# 8. Participant / Membership

Productionへの参加はParticipantで管理する。

Production
    ↓
Participant
    ↓
Person / Organization

Participantは出演者・スタッフ等の参加Factを表す。

Participant TypeとAuthorization Roleは別概念である。

公演ごとのMember表示では、所属が承認済みの場合は「名前（所属）」、それ以外は名前のみを基本とする。

退団後の過去Productionでは、その時点の所属表記をSnapshotとして保持する。

複数所属はVersion 1.0では考慮しない。

Member PageからPerson Pageへは、関連付けが存在する場合にリンクできる。

---

# 9. Venue

VenueはProductionに紐づく。

Production
    ↓
Venue

Productionは原則として一つのVenueを持つ。

PerformanceはVenueを直接所有・管理しない。

同一Projectで東京・大阪等の複数会場を扱う場合は、複数Productionとして表現する。

Performanceは所属ProductionのVenueを利用する。

---

# 10. Performance

PerformanceはProductionにおける一回の上演を表す。

Production
    ↓
Performance (1..N)

Performanceは以下を扱う。

- 公演日時
- 開場・開演等の時間情報
- タイムゾーン
- Capacity
- Lifecycle Status

PerformanceはVenueを直接保持しない。

Productionに設定された標準予約定員を継承し、Performanceごとに変更できる。

ReservationはPerformance単位で管理する。

---

# 11. Ticket

TicketはProduction単位で券種・価格を管理する。

Production
    ↓
Ticket Pricing
    ↓
Performance
    ↓
Reservation

料金設定は二軸を利用できる。

第1軸：
- 一般
- 学生
- その他

第2軸：
- 前売
- 当日
- その他

両軸を使用しない、片方のみ使用することも可能とする。

両軸を使用する場合はMatrixとして扱う。

---

# 12. Reservation / Capacity

Reservationは観客によるPerformanceへの予約Factである。

Reservation
    ├─ Person
    ├─ Performance
    └─ Ticket

予約定員の適用単位はPerformanceとする。

Productionに標準予約定員を設定し、Performance作成時に継承する。

Performanceごとの個別Overrideを許可する。

Productionの標準値を後から変更しても、既存PerformanceのOverride値を自動変更しない。

Reservationの定員判定はPerformanceのCapacityを使用する。

---

# 13. Check In / Audience History

Check InはPerformance当日のReservationへの入場実績を表す。

Performance
    ↓
Reservation
    ↓
Check In
    ↓
CheckInCompleted

観劇履歴はCheckInCompletedを基礎として扱う。

予約しただけで来場しなかった場合と、実際に来場した場合を区別する。

Check Inによって必要なBusiness EventをAccounting等へ連携できる。

---

# 14. Rehearsal / Timetable

ProductionにRehearsalを所属させる。

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

Rehearsalは稽古予定から実施完了までを一つのEntityとして管理する。

TimetableはProductionにおける活動予定を時系列で扱う。

---

# 15. Budget / Accounting Scope

BudgetとAccountingは、計画と会計Factを分離する。

Budgetは計画値。

Actualの会計上の正本はJournal Entryとする。

会計の表示Scopeは以下とする。

Organization
    ↓
Project
    ↓
Production

役割：

- Organization = 団体全体の財務状況
- Project = 企画全体の予実管理
- Production = 個別公演の決算・収支確認

Project Budgetは企画全体の計画を表す。

Production Budgetは個別公演の計画を表す。

Project ActualおよびProduction Settlementは、Journal Entry等の正本からScopeに応じて集計する。

同一のActualをScopeごとに二重入力・二重保存しない。

---

# 16. Accounting

Accounting Domainの基本構造は以下とする。

Accounting
    ├─ Account
    ├─ Journal Entry
    └─ Accounting Period

Journal Entryを会計Factの正本とする。

Production Domain等はJournal Entryを直接管理せず、必要なBusiness EventをAccounting Domainへ連携する。

Organization Accountingでは団体全体の財務状態を確認する。

Project Accountingでは企画全体の予実を確認する。

Production Accountingでは個別公演の決算を確認する。

---

# 17. Document / Media

画像・ファイルは各DomainのArtifactとして扱う。

主な画像用途：

Organization
    └─ Logo 1

Production
    └─ Flyer Front / Back

Person
    └─ Bust-up / Full-body

画像はアップロード時に長辺1600pxへ正規化し、600pxのサムネイルを生成して保存する。

Public PageやMember Page等では用途に応じてサムネイルを利用する。

---

# 18. Public Web

Organization Public Page
    ├─ Top
    ├─ About
    ├─ Members
    ├─ Productions
    └─ Contact

Production Public Page
    ├─ Top
    ├─ Overview
    └─ Members

Organization Topでは、次回Productionを中心に表示し、SNS情報を配置する。

About / Members等は上部メニューから遷移する。

過去Productionは最新一件を基本表示とし、管理者が公開可否を制御できる。

過去Productionでは、アンケート抜粋を掲載できる機能を必須とする。ただし実際に公開するかどうかは管理者が選択する。

Production Topでは、フライヤー、作・演出、チケット情報、会場、Performance情報を中心に表示する。

公演日時・チケット・会場は横並びの情報ブロックを基本とする。

---

# 19. External Contact

Organization Contactは、必須機能ではなく後回し可能なOptional Featureとする。

実装する場合は、団体の実メールアドレスをPublic Pageへ直接掲載せず、StageArt側のアドレスを利用した転送方式を基本候補とする。

Contact機能は管理者がOFFにできる構造とする。

---

# 20. Historical Production

StageArt利用以前の公演もProductionとして登録できる。

Historical Productionも通常のProductionと同じ基本構造を利用する。

登録後、公開するかどうかはOrganization / Productionの公開設定に従う。

過去公演として公開する場合、アンケート抜粋等の任意情報を追加できる。

---

# 21. Slug

OrganizationおよびProductionはPublic URLを構成するSlugを持つ。

Slugは登録時に入力可能とし、一意性を確認する。

Personは個人Slugを持たない。

Person PageはStageArt内部のPerson Identityを基礎に解決する。

---

# 22. Domain Responsibility Principle

各Domainは、自身のFactとBusiness Ruleを正本として管理する。

他DomainのFactを重複保存して整合性を維持する設計を基本としない。

特に、

- Reservationの正本はReservation
- Check Inの正本はCheck In / Reservation Fact
- 会計Factの正本はJournal Entry
- Production参加の正本はParticipant
- Organization所属の正本はMembership

とする。

各Domain間の集計・表示は、正本Domainから導出する。

---

# 23. Canonical Relationship Summary

```text
UserAccount
    ↓
Person
    ├─ Profile
    ├─ HistoricalActivity
    └─ Membership
           ↓
      Organization
           ↓
        Project
           ↓
      Production
       ├─ Participant ── Person / Organization
       ├─ ProductionDelegate ── Person / Role
       ├─ Venue
       ├─ Performance
       │    └─ Reservation
       │         └─ Check In
       ├─ Ticket Pricing
       ├─ Rehearsal
       │    └─ RehearsalAttendance
       ├─ Timetable
       ├─ Budget
       ├─ Document / Media
       ├─ Announcement
       └─ Survey
```

Accountingは各Scopeの会計情報をJournal Entryから集計する。

```text
Organization
    └─ Accounting Scope
          └─ Project
                └─ Production
                      └─ Journal Entry attribution / aggregation
```

---

# 24. Design Principle

StageArtでは、Domainの内部構造を複雑にしても、利用者に不要な概念を強制的に見せない。

特にProjectは、複数Productionを束ねるための重要なDomainである一方、通常の単独公演ではProduction中心のUIを維持する。

Domain間で同じ情報を二重管理せず、各Factの正本を明確にする。

このDomainMapを上位基準として、個別Domain Model、ER Diagram、Architectureの記述を整合させる。
