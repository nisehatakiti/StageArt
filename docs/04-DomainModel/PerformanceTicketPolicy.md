# StageArt Blueprint

# Performance / Ticket Policy

Version : 1.0

---

# Purpose

本書は、Production・Performance・Ticket・Reservationの関係について、確定した販売条件の継承ルールを定義する。

既存のPerformance / Ticket / Reservation Domainを補完するPolicyであり、新しいDomain Entityを追加するものではない。

---

# 1. Performance

Performanceは、Productionに属する個々の公演回を表す。

一つのProductionは複数のPerformanceを持つことができる。

Performanceは、Production Manager（PrimaryManager）またはProduction Delegateが作成・更新・削除する。

Performanceは最低限、以下の公演回固有情報を扱う。

- 公演日時
- 追記事項

追記事項は自由記述とする。

例：

- A班
- B班
- ○○の代わりに××が出演予定
- アフタートークあり
- ゲスト出演あり

これらを初期段階で個別の構造化属性へ分割しない。

---

# 2. Production Ticket

TicketはProductionに対して設定する販売条件である。

Productionに対して、基本となるTicket Type / Priceを設定する。

例：

Production
  ├── 一般 3,000円
  ├── 学生 2,000円
  └── 招待 0円

Ticketの正本はProduction側にある。

TicketはProduction横断の共通商品Masterではなく、そのProduction固有の販売条件として扱う。

---

# 3. Performance Ticket Default

Performanceは、原則として所属Productionに設定されたTicketを販売条件として採用する。

基本構造：

Production
  ↓
Ticket
  ↓
Performance
  ↓
Reservation

PerformanceごとにTicketを複製して独立したTicket Masterを作らない。

ProductionのTicket設定が通常のPerformanceに対するデフォルトとなる。

---

# 4. Performance Override

公開ゲネプロ等、Performanceごとに価格その他の販売条件を変更する必要がある場合は、Performance単位のOverrideを許可する。

例：

Production Ticket
  一般 3,000円
  学生 2,000円

通常Performance
  → Production設定をそのまま利用

公開ゲネPerformance
  → 一般 1,500円
  → 学生 1,000円

Performance Overrideを設定した場合、そのPerformanceでは個別設定を優先する。

Overrideを設定していないPerformanceではProductionのTicket設定を利用する。

---

# 5. Inheritance Rule

Ticket設定の基本関係は「Production設定をPerformanceが継承する」とする。

ProductionのTicket設定変更は、Performance側でOverrideされていない販売条件に反映される。

Performance Overrideを設定した販売条件は、Production側の変更よりもPerformance側の設定を優先する。

基本構造：

Production Ticket
       │
       ├── Performance A
       │      └── Default
       │
       ├── Performance B
       │      └── Default
       │
       └── Performance C
              └── Override

---

# 6. Reservation Price Snapshot

Reservationは、予約成立時点で適用されたTicket販売条件を取引Factとして保持する。

最低限、予約成立時点の単価および金額を保持する。

TicketまたはPerformance Overrideの現在値を再参照して、既存Reservationの金額を再計算してはならない。

例：

予約成立時
  一般 3,000円 × 2
  = 6,000円

その後Production Ticketを3,500円へ変更しても、既存Reservationは6,000円のままとする。

---

# 7. Accounting Relationship

Reservation成立時の取引金額は、Accounting Domainと連携して未収金として扱う。

基本Flow：

Reservation成立
  ↓
未収金 / チケット売上
  ↓
受付・入金
  ↓
現金等 / 未収金

キャンセル、免除、金額変更等によって未収金を調整する場合も、元の取引Factを破壊せず、必要なAdjustment / Reversal等として記録する。

具体的なJournal Entryの構造はAccounting / Journal Entry Domainに従う。

---

# 8. Public Relationship

Productionに設定されたTicketは、Public Ticketとして公開可能なものとInternal向けのものを区別できる。

PerformanceのPublic表示では、ProductionのTicket設定およびPerformance Overrideによる最終的な販売条件を表示対象とする。

---

# Business Rules

- 一つのProductionは複数のPerformanceを持つことができる。
- Performanceは一つのProductionに所属する。
- Performanceの作成・更新・削除はPrimaryManagerまたはProductionDelegateが行う。
- TicketはProductionに対して設定する。
- TicketはProduction固有の販売条件である。
- PerformanceはProductionのTicketを原則として採用する。
- PerformanceごとにTicketを複製しない。
- Performanceごとの販売条件Overrideを許可する。
- Overrideがない場合はProductionのTicket設定を利用する。
- Overrideがある場合はPerformanceの個別設定を優先する。
- ProductionのTicket変更は、OverrideされていないPerformanceに反映可能とする。
- ReservationはPerformanceに紐付く。
- Reservationは予約成立時点の単価・金額をSnapshotとして保持する。
- Ticket価格変更によって既存Reservationの金額を再計算しない。
- Reservation成立時の金額はAccounting Domainへ連携する。
