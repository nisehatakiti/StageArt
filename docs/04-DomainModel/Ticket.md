# StageArt Blueprint

# Domain Model : Ticket

Version : 2.0

---

# Purpose

Ticketは、
Productionにおけるチケット販売条件を管理するDomainである。

Ticketは、
「このProductionにおいて、どの種類のチケットを、いくらで販売するか」
を表す。

TicketはProductionに所属する。

---

# Concept

Ticketは、
Productionごとに設定される販売条件である。

Ticketは一般的な共通商品マスターではなく、
Production固有の販売設定として管理する。

基本構造：

Production
  ↓
Ticket
  ↓
Performance
  ↓
Reservation

---

# Identity

TicketはTicketIdによって一意に識別する。

TicketIdは変更しない。

Ticket TypeやPriceは識別子ではない。

同じTicket Typeや同じPriceを持つTicketが
別のProductionに存在しても問題ない。

---

# Relationship

Ticketは必ず一つのProductionに所属する。

Production
  ↓
Ticket

Performanceは、
所属するProductionのTicketを販売対象として利用する。

Production
  ↓
Ticket
  ↓
Performance

Reservationは、
Performanceおよび選択されたTicketを参照する。

Production
  ↓
Performance
  ↓
Reservation
       ↓
     Ticket

---

# Ticket Type

Ticket Typeは、
チケットの販売区分を表す。

例：

- 一般
- 学生
- 当日
- 招待
- 関係者
- モニター

Ticket Typeは、
Productionにおける販売条件の一部として管理する。

Ticket Type自体を、
Productionをまたいで共有するMaster Entityとはしない。

---

# Display Name

Ticketは、
観客向けの表示名称を持つことができる。

例：

- 一般前売
- 学生前売
- 一般当日券
- 関係者招待

内部的なTicket Typeと、
観客向けのDisplay Nameは分離できる。

---

# Price

Ticketは販売価格を保持する。

PriceはTicketに設定される。

Price = 0を許可する。

無料Ticketの例：

- 招待
- 関係者
- モニター

Priceは、
Productionにおける販売条件として管理する。

---

# Ticket Price and Reservation

Reservationが作成された時点で、
そのReservationに適用されたTicketの価格を
取引Factとして保持する。

例えば、

Ticket
  Price = 3,000円

の後に、

Ticket
  Price = 3,500円

へ変更された場合でも、

既存Reservation
  Price = 3,000円

を維持する。

Ticketの現在価格を変更することによって、
過去のReservationの価格を変更してはならない。

---

# Price Snapshot

Reservationは、
予約時点の価格情報を保持する。

基本構造：

Ticket
  ↓
Reservation
  ↓
Price Snapshot

Price Snapshotは、
Reservationが成立した時点の取引条件を保持するための情報である。

Price SnapshotはTicketの現在値を参照して
過去の取引価格を再計算するためのものではない。

---

# Ticket Status

Ticketは販売状態を持つ。

基本的な状態：

- DRAFT
- ON_SALE
- SUSPENDED
- CLOSED

---

# Draft

Ticketが作成されたが、
まだ販売開始されていない状態。

---

# On Sale

Ticketが販売可能な状態。

対象となるPerformanceが公開され、
Reservationを受け付ける場合に利用する。

---

# Suspended

一時的に販売を停止している状態。

Ticket自体を削除せず、
販売停止という状態を保持する。

---

# Closed

販売を終了した状態。

過去のReservationから参照されているTicketを
物理削除しない。

---

# Public Visibility

Ticketは、
一般観客向けに表示するかどうかを管理できる。

Public Visibilityは、
内部向けTicketと公開Ticketを区別するために利用する。

例えば、

- 一般公開Ticket
- 関係者Ticket
- 招待Ticket

などを区別できる。

---

# Performance Relationship

Performanceは、
Productionに所属するTicketを販売対象として利用する。

基本構造：

Production
  ↓
Ticket
  ↓
Performance

Performanceごとに、
利用可能なTicketを制御する必要がある場合は、
Performance側の販売設定として将来的に拡張する。

Version 1.0では、
TicketをPerformanceごとに複製しない。

---

# Performance Availability

Performanceごとに、
利用可能なTicket Typeを設定できる構造を将来的に持たせる。

例えば、

Production
  ├── 一般
  ├── 学生
  └── 招待

が存在し、

Performance A
  ├── 一般
  └── 学生

Performance B
  ├── 一般
  └── 招待

のように販売対象を変更できる。

ただし、
Ticketそのものの正本はProduction側にある。

---

# Sales Period

Ticketには、
販売期間を設定できる構造を将来的に持たせる。

例：

販売開始
  ↓
2026-08-01

販売終了
  ↓
2026-08-20

Version 1.0では、
必須機能として実装しない。

---

# Ticket and Reservation

Reservationは、
選択したTicketを参照する。

基本構造：

Production
  ↓
Performance
  ↓
Reservation
  ↓
Ticket

Reservationは、
予約時点のTicket情報を取引Factとして保持する。

Ticketの現在状態や現在価格を変更しても、
過去のReservationを変更しない。

---

# Ticket and Issued Ticket

Reservationが成立し、
実際のチケットとして発行された場合、
Issued Ticketとして扱う。

基本Flow：

Ticket
  ↓
Reservation
  ↓
Issued Ticket

Issued Ticketは、
実際の来場受付に使用される。

QR Ticketなどの受付用Artifactは、
Issued Ticketに関連付ける。

---

# QR Ticket

QR Ticketは、
Issued Ticketを特定するための受付用Artifactである。

基本Flow：

Reservation
  ↓
Issued Ticket
  ↓
QR Ticket
  ↓
Check In

QR情報そのものをTicketの正本として扱わない。

QR Ticketの詳細な管理は、
QRTicket Domainで定義する。

---

# Check In

TicketそのものをCheck Inするのではなく、
Issued Ticketに対応するReservationを
Check Inする。

基本Flow：

Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted

Check Inの詳細なルールは、
CheckIn Domainで管理する。

---

# CheckInCompleted

Check Inが完了すると、
CheckInCompletedが発生する。

CheckInCompletedは、
実際に観客が来場したというFactを確定する。

基本Flow：

Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted

---

# Ticket Revenue

CheckInCompletedは、
チケット売上をAccounting Domainへ連携する契機となる。

基本Flow：

Ticket
  ↓
Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted
  ↓
Ticket Revenue
  ↓
Accounting
  ↓
Journal Entry

Ticket Domain自身は、
Journal Entryを管理しない。

Ticketは、
販売条件としてのPriceを提供する。

Ticket Revenueは、
CheckInCompletedによって確定した
チケット売上の会計連携用Factである。

具体的な勘定科目やDebit / Creditの処理は、
Accounting Domainで定義する。

---

# Ticket Price and Revenue

Ticket.PriceとTicket Revenueは、
同じものではない。

Ticket.Price：

Productionにおける
チケット販売条件としての価格。

Ticket Revenue：

CheckInCompletedによって確定した
実際のチケット売上Fact。

基本構造：

Ticket.Price
  ↓
Reservation Price Snapshot
  ↓
CheckInCompleted
  ↓
Ticket Revenue
  ↓
Accounting

Ticket.Priceを変更しても、
過去のReservationや確定済みTicket Revenueを変更しない。

---

# Refund

Refundは、
Ticketそのものの責務として管理しない。

ReservationのCancellationや
Ticket Revenueの返金処理などと連携して扱う。

Refundの詳細なルールは、
Reservation / Accountingなどの関連Domainで定義する。

---

# Ticket Deletion

過去のReservationから参照されているTicketを
物理削除してはならない。

販売終了したTicketは、
StatusをCLOSEDとして保持する。

これにより、
過去のReservationがどのTicket条件で成立したかを
追跡できる。

---

# Business Rules

- TicketはProductionに所属する。
- TicketはProduction固有の販売条件を表す。
- TicketはTicketIdによって一意に識別する。
- Ticket Typeは販売区分を表す。
- Ticket TypeをProduction横断のMaster Entityとして共有しない。
- Ticketは観客向けDisplay Nameを持つことができる。
- TicketはPriceを持つ。
- Price = 0を許可する。
- Reservationは予約時点のPriceを保持する。
- Ticketの現在Priceを変更しても過去のReservationを変更しない。
- ReservationのPrice Snapshotは取引Factとして保持する。
- Ticketは販売状態を持つ。
- TicketはPublic Visibilityを持つことができる。
- PerformanceはProductionのTicketを販売対象として利用する。
- TicketをPerformanceごとに複製しない。
- PerformanceごとのTicket Availabilityは将来拡張する。
- Sales Periodは将来拡張する。
- ReservationはTicketを参照する。
- Issued TicketはReservationに基づいて発行する。
- QR TicketはIssued Ticketに関連付ける。
- TicketそのものをCheck Inしない。
- Check InはReservation単位で実施する。
- CheckInCompletedは観劇実績を確定する。
- CheckInCompletedを契機としてTicket RevenueをAccounting Domainへ連携する。
- Ticket DomainはJournal Entryを管理しない。
- Ticket.PriceとTicket Revenueを同一視しない。
- Ticket RevenueはAccounting Domainで管理する。
- 過去のReservationから参照されるTicketを物理削除しない。
- 販売終了したTicketはCLOSEDとして保持する。

---

# Domain Events

Ticketに関する主なDomain Event：

- TicketCreated
- TicketUpdated
- TicketPublished
- TicketSuspended
- TicketClosed

Check Inに関するEvent：

- CheckInCompleted

CheckInCompletedは、
Ticket RevenueのAccounting連携を開始する契機となる。

Ticket Domain自身が、
Journal Entryを直接生成・更新しない。

---

# Design Decisions

Ticketは、
Production固有の販売条件を管理する。

Ticketは、
共通商品MasterではなくProductionに所属する。

Ticket Typeは、
Productionにおける販売区分として扱う。

Ticket Priceは、
Productionにおける販売条件として管理する。

Reservation成立時には、
その時点の価格をPrice Snapshotとして保持する。

これにより、
将来Ticket Priceが変更されても、
過去のReservationの取引価格を変更しない。

Performanceは、
Productionに所属するTicketを販売対象として利用する。

PerformanceごとのTicket Availabilityは、
将来的に実装する。

Ticketが実際の売上として会計へ連携されるのは、
CheckInCompletedが発生した時点である。

Ticket.Priceは販売条件であり、
Ticket Revenueは会計上の売上Factである。

Ticket Domainは、
Accounting DomainのJournal Entryを直接管理しない。

Ticketは、
過去のReservationから参照される可能性があるため、
物理削除しない。

---

# Future

将来的に以下へ対応する。

- PerformanceごとのTicket Availability
- Ticket Sales Period
- Ticketごとの販売数制限
- 販売チャネル
- オンライン販売
- 当日販売
- 外部販売サービス連携
- 座席指定Ticket
- Ticket Bundle
- Discount
- Coupon
- Promotional Price

ただし、
将来機能を追加する場合も、
Ticketの基本責務を
Productionにおける販売条件の管理から逸脱させない。

---

# Design Principles

- TicketはProductionに所属する。
- TicketはProduction固有の販売条件を表す。
- Ticket TypeとPriceをTicketの主要な販売条件とする。
- Ticket TypeをProduction横断のMaster Entityとして共有しない。
- Ticket PriceとReservation Price Snapshotを分離する。
- 過去のReservationの価格を変更しない。
- PerformanceはProductionのTicketを利用する。
- TicketをPerformanceごとに複製しない。
- Issued TicketはReservationに基づいて発行する。
- QR TicketはIssued Ticketの受付用Artifactである。
- Check InはReservation単位で行う。
- CheckInCompletedは観劇実績を確定する。
- CheckInCompletedをTicket RevenueのAccounting連携契機とする。
- Ticket.PriceとTicket Revenueを分離する。
- Ticket DomainはJournal Entryを管理しない。
- 過去のReservationから参照されるTicketを物理削除しない。
- Blueprintを唯一の設計基準とする。