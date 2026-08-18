# StageArt Blueprint

# Blueprint Consistency Policy

Version : 1.0

---

# Purpose

本書は、StageArt Blueprint全体におけるDomain間の整合性を確保するための上位方針を定義する。

既存Domain文書に同一概念について異なる表現が残っている場合、本書の定義を優先する。

各Domainの詳細仕様は各Domain Modelに定義するが、Domain間の責務・正本・Scopeが衝突する場合は本書に従って修正する。

---

# 1. Core Hierarchy

StageArtの基本構造は以下とする。

Organization
    ↓
Project
    ↓
Production

Organizationは団体Scope、Projectは企画Scope、Productionは個別公演Scopeを表す。

ProjectはInternal Domainであり、通常の単独公演では利用者に強く意識させない。

一つのProjectには複数Productionを所属させることができる。

例えば、東京公演・大阪公演を同一Projectに所属させることができる。

---

# 2. Production Creation and Project Selection

Production作成時には、所属Projectを決定する。

利用者は以下を選択できる。

- 既存Projectへ所属させる
- 新規Projectを作成して所属させる

単独公演では、UI上Projectを意識させない簡略Flowを許可する。

StageArt内部ではProductionは必ずProjectに所属する。

---

# 3. Production Scope

Productionは具体的な公演・活動を表す。

Productionに関連する主なDomainは以下とする。

- Participant
- ProductionDelegate
- Performance
- Ticket
- Reservation
- CheckIn
- Rehearsal
- Timetable
- Budget
- Document
- Announcement
- Survey

Production固有のBusiness DataはProductionを正本となる関連先として管理する。

---

# 4. Venue

初期仕様ではVenueはProductionに直接紐づく。

基本構造：

Production
    ↓
Venue

一つのProductionには基本的に一つのVenueを設定する。

PerformanceにはVenueを直接持たせない。

Performanceは所属ProductionのVenueを利用する。

東京公演・大阪公演等の複数会場を必要とする場合は、同一Project内に複数Productionを作成する構造を基本とする。

将来、Production自体が複数会場を持つ必要が生じた場合は別途Domain拡張を行う。

---

# 5. Performance

PerformanceはProductionにおける個別の公演回を表す。

基本構造：

Production
    ↓
Performance
    ↓
Reservation

Performanceは以下を管理できる。

- 開場日時
- 開演日時
- 終演予定日時
- タイムゾーン
- 定員
- Status

Performance自身にVenueを保持しない。

---

# 6. Reservation Capacity

予約定員はReservationの対象となるPerformance単位で管理する。

Productionに標準定員を設定でき、その値をPerformanceへ継承する。

Performanceでは継承された定員を個別に変更できる。

基本構造：

Production
    ↓
Default Capacity
    ↓
Performance
    ↓
Reservation

定員はProduction単位の固定値ではなく、Performanceごとの予約受付条件として扱う。

---

# 7. Ticket

TicketはProductionを基本単位として管理する。

Ticketの販売条件は、必要に応じてPerformanceで利用対象を限定できる。

Ticketの料金設定は、以下の二軸を任意に使用できるマトリックス構造を基本とする。

第1軸の例：

- 一般
- 学生

第2軸の例：

- 前売
- 当日

利用しない軸も選択可能とする。

したがって、以下のような単純な券種も許可する。

- 一般
- 学生
- 前売
- 当日

---

# 8. Public Visibility

Productionは、一般公開のための情報が整うまで公開ページを生成してはならない。

Productionの情報公開可否は、ProductionのPublic Settings / Visibilityによって管理する。

情報公開前は一般向けProduction Public Pageを生成・公開しない。

情報公開後であっても、Venue、Performance、Ticket等の主要情報が未確定の場合は、Public Page上で「Coming Soon」等の準備中表示を行う。

---

# 9. Budget Scope

Budgetは計画値であり、Accounting Journal Entryとは別の概念である。

Budgetは以下の2つの計画Scopeを持つことができる。

## Project Budget

Project全体の計画を表す。

複数Productionを含む企画全体の予実管理に利用する。

## Production Budget

個別Productionの計画を表す。

個別公演の予算策定・比較に利用する。

Project BudgetとProduction Budgetは別の計画Scopeであり、同じBudgetを二重管理するものではない。

---

# 10. Budget Reuse

Budgetは一度作成したものを再利用できることを基本とする。

利用者は以下からBudgetを作成できる。

- 過去または既存Budgetのコピー
- StageArt標準テンプレート
- 空Budget

一つのProductionに複数Budgetを保持できる。

Budgetには利用者が設定できるBudget Nameを持たせる。

例：

「河童ホームラン2026予算 Version1」

A4・1ページの予算帳票としてPDF出力できる。

---

# 11. Accounting Source of Truth

Accountingの正本はJournal Entryとする。

同一Journal EntryをOrganization / Project / Productionの各Scopeから集計して参照する。

ProductionごとのActualやProjectごとのActualを別会計帳簿として二重管理しない。

基本構造：

Organization
    ↓
Journal Entry
    ↓
Project / Production Scope

---

# 12. Accounting Scope Roles

会計情報の主な見せ方は以下とする。

- Organization：団体全体の財務状況
- Project：企画全体の予実管理
- Production：個別公演の決算・収支確認

Budgetは計画値、ActualはJournal Entryから集計した実績値とする。

Production SettlementはProduction単位の最終収支確認を表す。

---

# 13. Organization Accounting Activation

AccountingはOrganization単位の任意機能とする。

Organization登録時にAccountingを有効化するか選択できる。

有効化する場合は、開始時点の流動資産を入力する。

最低限、以下を区別する。

- 現金
- 預金

Accounting未開始と残高0円は別状態として扱う。

Accountingを一度有効化した後、会計履歴を失わせる目的で単純にOFFへ戻す設計は採用しない。

---

# 14. Account Classification

Accountは一般的な会計科目分類に従う。

基本分類：

- ASSET
- LIABILITY
- EQUITY
- REVENUE
- EXPENSE

Account分類とJournal Entryの正本性を混同しない。

---

# 15. Authorization

Authentication IdentityとBusiness Identityを分離する。

UserAccount
    ↓
Person

Organization ScopeのRoleはMembershipを通じて適用する。

Production ScopeのRoleはProductionDelegateを通じて適用する。

PrimaryManagerはProductionの全管理権限を持つ。

ProductionDelegateはRoleによって限定されたPermissionを対象Productionにのみ適用する。

---

# 16. Participant / Membership

MembershipはPersonとOrganizationの所属関係を表す。

ParticipantはPersonまたはOrganizationがProductionへ参加しているFactを表す。

Productionごとの参加者表記はProduction時点のFactとして扱う。

所属があるPersonについては「名前（所属）」形式で表示できる。

所属のないPersonは名前のみ表示する。

退団後も過去Productionの表示は、そのProduction時点の所属表記をSnapshotとして保持する。

複数所属は初期仕様では考慮しない。

---

# 17. Person Profile

Person ProfileはPersonを主体として管理する。

Person固有の公開情報として、入力された項目について個別に公開・非公開を選択できる。

例：

- Profile Photo
- 年齢
- 生年月日
- 出身地
- 身長
- 体重
- BWH
- 足のサイズ
- 特技
- 資格

プロフィール画像は、バストアップ・全身を登録できる。

Person ProfileはSlugを必須としない。

Profileへの表示順は管理者が変更できる。

---

# 18. Historical Activity

Personは出演履歴・スタッフ参加履歴等を管理できる。

StageArt上に存在するProductionを履歴へ追加する場合、Person本人がProduction管理者へ自分がどのParticipantに該当するかを申告し、承認を得る。

本人が入力するStageArt外の過去実績はHistoricalActivityとして管理できる。

---

# 19. Organization Public History

過去Productionは、Organization側で公開・非公開を制御できる。

集客等の事情により公開したくない過去Productionは非表示にできる。

過去Productionでは、アンケート内容の抜粋を掲載できる機能を必須とする。

ただし、アンケート抜粋の公開は管理者が任意で選択できる。

過去Productionの情報移行では、StageArt利用以前の公演も登録できる。

---

# 20. Asset Policy

画像アップロード対象は以下を基本とする。

Organization：
- 団体ロゴ 1個

Production：
- 公演フライヤー表 1個
- 公演フライヤー裏 1個

Person：
- バストアップ 1個
- 全身 1個

アップロード時に長辺1600pxへ正規化した画像を保存する。

同時に長辺600pxのサムネイルを生成・保存する。

Public PageやMember Pageでは用途に応じてサムネイルを利用し、ストレージ消費を抑える。

---

# 21. Setup Flow

Organization Setupは以下を基本Flowとする。

1. Google等の外部Authentication連携
2. タイムゾーン設定
3. 通知設定
4. Organization Name入力
5. Organization代表者を実行Personに設定
6. Logo / Description / Slug入力
7. Accountingを有効化するか選択
8. Accounting有効時は現金・預金等の開始残高を入力
9. Organization登録完了

Contact機能は必須初期機能ではなく、後回し可能とする。

---

# 22. Production Setup Flow

Production作成は以下を基本Flowとする。

1. Project選択または新規Project作成
2. Production Title / Description / Slug入力
3. Flyer Upload
4. Production Manager / PrimaryManager設定
5. Information Public / Non-Public選択
6. Accountingが有効な場合、Budgetを作成するか確認
7. Budget作成時はBudget入力へ進む
8. Participant / Member登録
9. Production Delegate等の代理人設定
10. Venue設定
11. Ticket設定
12. Performance設定
13. Reservation Capacityの初期値を設定
14. 保存

ParticipantやMemberは作成後も追加・修正可能であることをUI上で明示する。

過去Productionの登録も同じSetup Flowを基本とする。

---

# 23. Public Page Generation Rule

Production Public PageはInformation Publicが有効になった時点で生成対象となる。

ただし、公開情報が不足している場合は、存在しない情報を推測して表示しない。

主要情報が未確定の場合は、該当セクションをComing Soon / 準備中として表示できる。

情報公開前のProductionを一般公開ページとして生成してはならない。

---

# 24. Legacy / Existing Data

StageArt利用以前のProductionは、現在のProductionと同じDomain構造へ移行できるものとする。

過去Productionについても、Venue、Ticket、Performance等の情報を入力できる。

当時存在しなかった情報は空欄を許容する。

---

# 25. Precedence Rule

Domain間で以下の既存記述が残っている場合は、本書を優先して修正対象とする。

- PerformanceがVenueを直接保持するとする記述
- BudgetをProductionだけのScopeとして扱う記述
- ProjectがBudgetを直接保持しないとする記述
- Production作成時にProjectを必ず自動生成することを唯一のFlowとする記述
- Information Public前でもPublic Pageを生成可能とする記述
- Reservation CapacityをProductionだけで固定管理するとする記述

これらは本書の確定仕様に合わせて各Domain文書・ER図・DomainMap・Architecture文書を順次整合させる。

---

# Design Principle

StageArt Blueprintでは、Domain単体の整合だけでなく、Domain間の責務・正本・Scopeの整合を優先する。

同一のFactを複数Domainで二重管理しない。

計画値と会計Factを混同しない。

Organization / Project / ProductionのScopeを明確に分離しながら、同一の会計正本から必要な粒度で集計できる構造を維持する。

Blueprintを唯一の設計基準とする。
