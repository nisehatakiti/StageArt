# StageArt Blueprint

# Use Case Consistency Policy : Production Creation Wizard

Version : 1.1

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

Production Managerの変更・移譲は、Production Managementメニューから「公演管理者の移譲」を選択し、対象のStageArt Account Userを指定して保存するだけの単純な操作とする。

移譲後は新しいManagerがProduction Scopeの管理権限を持つ。

---

# Step 4 : Public Visibility

Production Basic Information上で現在の公開状態を確認できるようにする。

初期状態は「未公開」とし、この表示部分をクリックして公開設定を行う。

公開設定では、情報公開のタイミングを指定できる。

公開は、利用者が公開時刻に合わせて手動でクリックして実行するActionではなく、指定された公開日時を予約した状態として保持し、Scheduler / CRON等のBackground Jobが指定時刻にPublic Visibilityを有効化する方式を基本とする。

これにより、「午前0時公開」等の時刻指定について、利用者がその時刻に操作する必要をなくす。

Public VisibilityとLifecycleは別概念として扱う。

情報公開されていないProductionについてはPublic Pageを生成・公開しない。

公開後、Venue、Ticket、Performance等が未確定の場合はPublic Page上でComing Soon表示を利用する。

---

# Public Release Scheduling

公開予約には少なくとも以下を保持する。

- Release At
- Release Timezone
- Release Status
- Requested By
- Executed At

指定時刻にBackground JobがReleaseを処理する。

JobはIdempotentに実行できるものとし、同一Productionを二重公開しない。

公開日時の変更・取消は、公開前であれば管理権限に応じて可能とする。

---

# Step 5 : Budget

Organization Accountingが有効な場合、Production Budgetを設定するか確認する。

選択肢：

- 予算を設定する
- 今回は設定しない

予算を設定する場合はBudget入力画面へ遷移する。

Budgetは複数保持可能で、過去Budgetをコピーして再利用できる。

新規Budgetは、必要な予算カテゴリがあらかじめ表示された入力フォームから作成する。

利用者は各カテゴリの金額を入力・編集し、過去Budgetをコピーした場合はコピー元の金額を基礎として必要な数字だけ変更できる。

Budget Nameを設定し、A4一枚の帳票タイトルに利用できるようにする。

---

# Budget Input Categories

初期表示する支出カテゴリ：

- 会場費用
- 機器レンタル費用
- 外注費（スタッフ＋キャスト）
- 広告宣伝費用
- 通信費
- 車両交通費
- その他雑費

初期表示する収入カテゴリ：

- 集客予測 × チケット代
- 物販
- その他

カテゴリはテンプレートとして扱い、将来的に追加・編集可能な設計余地を残す。

Budget入力画面では、カテゴリを探して一から行追加することを基本操作とせず、必要なカテゴリが最初から並んでいて数字を埋めていく操作を基本とする。

---

# Budget Reuse Flow

過去Budgetを利用する場合は、既存Budgetを選択してコピーする。

コピーされたBudgetは新しい独立したVersionとして扱う。

コピー元のBudgetを変更しても、コピー先のBudgetには影響しない。

コピー後、利用者は必要なカテゴリの金額を変更して今回のProduction用Budgetとして保存する。

Budget Nameには、例えば「河童ホームラン2026予算Version1」のような利用者が識別しやすい名称を設定できる。

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
- Public Visibility / Release Schedule
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

公開については、利用者が指定時刻に手動操作するのではなく、公開日時を予約し、Background Jobが指定時刻に公開状態を切り替える。

予算については、必要カテゴリをあらかじめ表示した入力フォームを基本とし、過去Budgetをコピーした場合は既存金額を編集して再利用できるようにする。

```text
Production
  ├─ Basic Information
  │    └─ Standard Reservation Capacity
  │
  ├─ Manager
  │    └─ Transfer from Management Menu
  │
  ├─ Public Visibility
  │    └─ Scheduled Release
  ├─ Budget
  │    └─ Template / Copy / Edit Amounts
  ├─ Members
  ├─ Venue
  ├─ Ticket
  └─ Performance
         └─ Reservation Capacity (inherited / override)
```
