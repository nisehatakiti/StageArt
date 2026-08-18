# StageArt Blueprint

# Use Case Consistency Policy : Production Creation Wizard

Version : 1.0

---

# Purpose

Production初回登録および過去公演登録に利用するCreation Wizardについて、現在までに確定したProduction / Project / Venue / Ticket / Performance / Budget / Participant仕様との整合性を定義する。

---

# Canonical Principle

通常のProductionと過去公演は、基本的に同一のCreation Wizardフローを利用する。

```text
Project
  ↓
Production Basic Information
  ↓
Manager
  ↓
Public Visibility
  ↓
Budget
  ↓
Members
  ↓
Venue
  ↓
Ticket
  ↓
Performance
```

過去公演についてもProductionとして登録し、必要な公開・履歴情報を同じDomain構造で管理する。

---

# Step 1 : Project Selection

Production作成時に所属Projectを指定する。

選択肢：

- 既存Projectを選択
- 新規Projectを作成

Productionは必ず一つのProjectに所属する。

---

# Step 2 : Production Basic Information

Productionの基本情報として少なくとも以下を入力する。

- 公演タイトル
- 概要
- Production Slug
- 標準予約定員
- 公演フライヤー表
- 公演フライヤー裏

フライヤー画像はこの時点でアップロード可能とする。

画像はMedia Policyに従って正規化する。

---

# Standard Reservation Capacity

Production Basic Informationで、Productionの**標準予約定員**を入力できる。

この値はProduction全体に対する標準値であり、各Performance作成時の初期値として継承する。

```text
Production
  └─ Standard Reservation Capacity
          ↓ inherit
Performance
  └─ Reservation Capacity
```

Performance作成後は、Performance単位で定員を変更できる。

Productionの標準定員を後から変更しても、既存Performanceの定員を自動的に上書きしない。

定員はReservationの受付可否判定に利用する。

---

# Step 3 : Production Manager

Production Managerを設定する。

Production ManagerはStageArt Accountを所持するUserのみ指定可能とする。

Production Managerは後から別のStageArt Account Userへ移譲可能とする。

Production ManagerはProduction Scopeの管理権限を持つ。

---

# Step 4 : Public Visibility

この時点でProductionを一般公開するかを選択できる。

ProductionのPublic VisibilityとLifecycleは別概念として扱う。

情報公開されていないProductionについてはPublic Pageを生成・公開しない。

公開後、Venue、Ticket、Performance等が未確定の場合はPublic Page上でComing Soon表示を利用する。

---

# Step 5 : Budget

Organization Accountingが有効な場合、Production Budgetを設定するか確認する。

選択肢：

- 予算を設定する
- 今回は設定しない

予算を設定する場合はBudget入力画面へ遷移する。

Budgetは複数保持可能で、過去Budgetをコピーして再利用できる。

Budget Nameを設定し、A4一枚の帳票タイトルに利用できるようにする。

---

# Step 6 : Members

Production Memberを登録する。

この時点で、Production管理上必要な代理人等も設定できる。

Member登録はCreation Wizardで完了する必要はなく、**Production作成後も追加・変更可能**であることを画面上に明示する。

MemberはOrganization Membershipの有無にかかわらず登録できる。

所属があるPersonはProduction時点の所属Snapshotを利用し、表示は「名前（所属）」、所属がない場合は「名前」とする。

---

# Step 7 : Venue

ProductionにVenueを設定する。

Productionは基本的に一つのVenueを持つ。

VenueはProductionに直接紐づき、PerformanceにはVenueを持たせない。

Venue未確定でもProduction自体の作成は可能とする。

公開済みでVenueが未確定の場合、Public PageではComing Soon等の未確定表示を利用する。

---

# Step 8 : Ticket

ProductionのTicketを設定する。

Ticket料金は必要に応じて以下の2軸を利用できる。

- 料金区分（例：一般 / 学生）
- 販売区分（例：前売 / 当日）

両軸を使用する、片軸だけ使用する、または両軸を使用しない一律料金のいずれも可能とする。

不要な組み合わせは販売対象外にできる。

TicketはProductionに所属し、PerformanceごとにTicket Masterを複製しない。

---

# Step 9 : Performance

Productionの公演回を登録する。

各PerformanceはProductionの標準予約定員を初期値として継承する。

必要に応じてPerformanceごとに定員をOverrideできる。

Performance日時はOrganization Timezoneを基準として扱う。

Performanceが未登録でもProductionは作成可能とする。

公開済みで公演回が未確定の場合、Public PageではComing Soon表示を利用する。

---

# Past Production

過去公演も同じWizardフローで登録する。

過去公演の場合、現在のProductionと同じDomain構造を利用しつつ、以下を設定可能とする。

- 過去公演としての日時
- 過去時点のMember / Staff情報
- フライヤー
- Venue
- Ticket
- Performance
- アンケート抜粋
- 公開／非公開

StageArt利用以前の公演も同じProductionとして登録できる。

---

# Post-Creation Changes

以下はProduction作成後も変更可能とする。

- 基本情報
- Slug
- Manager
- Public Visibility
- Budget
- Members
- Venue
- Ticket
- Performance
- Performance Capacity

Production作成Wizardは初期設定を効率化するためのものであり、すべての設定をWizard完了時点で固定するものではない。

---

# Design Principle

Production Creation Wizardは「公演を公開可能な状態まで一通り登録できる」ことを目的とする。

一方で、Venue、Ticket、Performance等が未確定の段階でもProductionを作成できるようにし、Public VisibilityがONの場合でも不足情報はPublic Page上でComing Soonとして扱う。

定員については、Production Basic Informationで標準予約定員を設定し、各Performanceへ継承する。

```text
Production
  ├─ Basic Information
  │    └─ Standard Reservation Capacity
  │
  ├─ Manager
  ├─ Public Visibility
  ├─ Budget
  ├─ Members
  ├─ Venue
  ├─ Ticket
  └─ Performance
         └─ Reservation Capacity (inherited / override)
```
