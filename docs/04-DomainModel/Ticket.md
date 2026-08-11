# StageArt Blueprint

# Domain Model : Ticket

Version : 1.0

---

# Purpose

Ticketは、
Productionごとに設定される
チケット販売条件を管理するDomainである。

Ticketは、

「この公演で、どのTicket Typeを、いくらで販売するか」

というFactを表す。

Ticketは一般的なチケット商品そのものではなく、
Productionごとの販売設定を表す。

---

# Concept

TicketはProductionに所属する。

基本構造：

Organization
  ↓
Project
  ↓
Production
  ↓
Ticket

Ticketは、
Ticket TypeとPriceの組み合わせとして管理する。

例えば、

Production A

  一般
    3,000円

  学生
    2,000円

  当日
    3,500円

という販売設定を持つ。

---

# Relationship

Ticketは必ず一つのProductionに所属する。

Production
  ↓
Ticket

一つのProductionには、
複数のTicketを設定できる。

TicketはProductionをまたいで
共有するものではない。

同じ「一般」というTicket Typeであっても、
Productionごとに異なるPriceを設定できる。

---

# Ticket Type

Ticket Typeは、
チケットの販売区分を表す。

例：

- 一般
- 学生
- シニア
- U-18
- 当日
- 招待
- 関係者

Ticket Typeの名称は、
Productionごとの販売条件に合わせて設定できる。

---

# Price

Priceは、
Ticketの販売価格を表す。

例えば、

Ticket Type
  一般

Price
  3,000円

という組み合わせを持つ。

Priceは金額として管理し、
表示用文字列を正本としない。

---

# Ticket Master

Productionは、
公演ごとにTicket Masterを持つ。

基本構造：

Production
  ↓
Ticket Master
  ↓
Ticket

Ticket Masterには、
そのProductionで販売するTicketの一覧が登録される。

Ticket Masterの内容は、
他のProductionへ自動的に共有しない。

---

# Ticket Identity

TicketはTicketIdによって
一意に識別する。

TicketIdは変更しない。

Ticket Typeの名称やPriceを
識別子として利用しない。

---

# Display Name

Ticketには、
観客向けに表示する名称を設定できる。

例えば、

- 一般
- 学生
- 前売一般
- 当日一般

など。

内部のTicket Typeと
表示名称を分離する必要がある場合に対応できる。

---

# Sale Price

Ticketには、
販売価格を設定する。

Sale Priceは、
ProductionにおけるTicketの販売条件の一部である。

Reservationが作成された場合、
Reservationは利用したTicketを参照する。

---

# Reservation Relationship

Reservationは、
Productionに設定されたTicketを利用する。

基本構造：

Production
  ↓
Ticket
  ↓
Performance
  ↓
Reservation

Reservationは、
どのTicket Type / Priceで予約されたかを識別できる。

---

# Performance Relationship

TicketはProduction単位で管理する。

PerformanceごとにTicket Masterを複製しない。

基本構造：

Production
  ↓
Ticket
  ↓
Performance
  ↓
Reservation

同じTicketを複数のPerformanceで利用できる。

---

# Performance Availability

将来的に、
特定のTicketを特定のPerformanceだけで
販売する必要が生じた場合に対応できる構造とする。

例えば、

Ticket
  一般 3,000円

Performance A
  販売

Performance B
  販売

Performance C
  販売しない

など。

ただし、
Version 1.0では詳細なTicket Availabilityを
必須Domainとしない。

---

# Sales Status

Ticketには、
販売状態を設定できる。

基本的な状態：

- DRAFT
- ON_SALE
- SUSPENDED
- CLOSED

---

# Draft

DRAFTは、
Ticketが作成されたが、
まだ販売開始していない状態。

---

# On Sale

ON_SALEは、
Ticketが販売可能な状態。

Reservationで利用できる。

---

# Suspended

SUSPENDEDは、
一時的に販売を停止している状態。

Ticketそのものは削除しない。

---

# Closed

CLOSEDは、
販売を終了した状態。

過去のReservationから
利用されたTicket情報を確認できるよう、
Ticket自体は削除しない。

---

# Sales Period

将来的に、
Ticketごとの販売期間を設定できる。

例えば、

前売券
  8/1 〜 9/10

当日券
  9/11のみ

など。

Version 1.0で販売期間を実装する場合は、
Ticketの販売条件として管理する。

---

# Ticket and Reservation Price

TicketのPriceは、
Productionにおける販売価格の正本である。

Reservationが作成された後に
TicketのPriceが変更された場合でも、
過去のReservationの取引価格を
変更してはならない。

そのため、

Ticket
  = 現在の販売条件

Reservation
  = 予約時点の取引Fact

として扱う。

Reservation側には、
予約確定時点の販売価格を
取引情報として保持できる。

---

# Price Change

TicketのPriceは、
販売中でも変更される可能性がある。

ただし、
既存Reservationに遡及して適用してはならない。

例えば、

Ticket
  一般 3,000円

から、

Ticket
  一般 3,500円

へ変更した場合、

変更後に作成されるReservation
  → 3,500円

変更前に作成されたReservation
  → 3,000円

となる。

---

# Ticket Type Change

Ticket Typeの名称や内容を変更する場合も、
既存ReservationのFactを変更しない。

過去のReservationは、
予約時点のTicket情報を基準として扱う。

必要に応じて、
Ticketの履歴またはVersionを利用する。

---

# Free Ticket

Price = 0

のTicketを許可する。

例えば、

- 招待
- 関係者
- モニター

など。

Priceが0円であっても、
Ticketとして管理する。

---

# Invitation

招待券もTicketとして管理できる。

例えば、

Ticket Type
  = 招待

Price
  = 0円

とする。

招待の対象者や配布管理については、
別Domainとして必要になった時点で設計する。

---

# Public Visibility

Ticketには、
観客向けに公開するかどうかを設定できる。

例えば、

公開：

- 一般
- 学生

非公開：

- 関係者
- 招待

など。

非公開Ticketは、
一般観客向け予約画面に表示しない。

ただし、
管理者による予約や代理予約などで
利用できる場合がある。

---

# Order

Ticketには、
表示順を設定できる。

例えば、

1. 一般
2. 学生
3. U-18
4. 当日

など。

Orderは、
観客向けTicket選択画面の表示順などに利用する。

---

# Description

Ticketには、
観客向けの説明を設定できる。

例えば、

「高校生以下」

「当日受付で学生証をご提示ください」

など。

説明文はTicketの表示情報として扱う。

---

# Ticket Artifact

Ticketは、
販売条件を表すDomainである。

QR Ticketは、
Reservationから生成されるArtifactであり、
Ticket Domainそのものではない。

基本構造：

Ticket
  ↓
Reservation
  ↓
QR Ticket

QR Ticketは、
Ticket Masterの情報を直接管理するものではない。

---

# Seat

TicketとSeatは、
現時点では直接結び付けない。

Version 1.0ではSeat機能を実装しない。

将来的に座席指定を実装する場合は、

Ticket
  ↓
Reservation
  ↓
Reservation Seat
  ↓
Seat

などへ拡張できる。

---

# Audit

Ticketには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Deletion

Ticketは、
過去のReservationから参照される可能性がある。

そのため、
販売終了後に物理削除しない。

販売終了したTicketは、
CLOSEDなどの状態で保持する。

---

# Authorization

Ticketの作成・変更・販売状態変更は、
Productionを管理する権限を持つPersonが行う。

Organization管理者は、
自身のOrganizationについて
全権限を持つ。

Production Delegateとして
公演管理権限を委任することもできる。

Participant Typeは、
Ticket管理権限を付与するものではない。

---

# Business Rules

- TicketはProductionに所属する。
- TicketはProductionごとの販売条件を表す。
- TicketはTicket TypeとPriceの組み合わせとして管理する。
- ProductionごとにTicket Masterを持つ。
- TicketはProductionをまたいで自動共有しない。
- TicketIdによって一意に識別する。
- Ticket Typeは販売区分を表す。
- Priceは販売価格を表す。
- Price = 0を許可する。
- 招待券などをTicketとして管理できる。
- TicketはDRAFT / ON_SALE / SUSPENDED / CLOSEDの状態を持つ。
- ON_SALEのTicketをReservationで利用できる。
- CLOSEDのTicketを新規Reservationで利用しない。
- TicketのPrice変更を既存Reservationへ遡及させない。
- Reservationは予約時点の取引価格を保持できる。
- Ticket Typeの変更を既存Reservationへ遡及させない。
- Ticketは複数Performanceで利用できる。
- TicketのPerformance単位販売制御は将来拡張とする。
- Ticketには公開・非公開を設定できる。
- Ticketには表示順を設定できる。
- Ticketには説明文を設定できる。
- QR TicketはReservationから生成されるArtifactである。
- TicketはQR Ticketそのものではない。
- SeatはVersion 1.0では実装しない。
- Ticketは原則として物理削除しない。
- Ticketには監査情報を保持する。
- Ticket管理権限はRoleまたはProduction Delegateで管理する。
- Participant Typeは権限を付与しない。

---

# Domain Events

Ticketに関する主なDomain Event：

- TicketCreated
- TicketUpdated
- TicketPublished
- TicketSuspended
- TicketClosed
- TicketPriceChanged

Ticketの変更によって、
必要に応じてReservationや
Public Production Pageへ影響を通知する。

---

# Design Decisions

Ticketは、
ProductionごとのTicket Type / Priceの
組み合わせを管理する。

Ticket TypeやPriceを
システム全体の共通マスタとして管理しない。

同じ「一般」というTicket Typeでも、
Productionごとに異なる価格や販売条件を
設定できる。

TicketはProductionの販売条件の正本である。

一方、
Reservationは実際の予約というFactであり、
予約時点の取引価格を保持する。

この分離により、
将来Ticket価格が変更された場合でも、
過去のReservationの取引Factを壊さない。

---

# Future

将来的に以下へ対応できる。

- Ticket販売期間
- Performance単位の販売可否
- 前売 / 当日価格
- 割引
- クーポン
- 招待券配布
- Ticket販売枚数制限
- Ticket販売チャネル
- 外部販売サイト連携
- 座席指定
- Ticket Version
- Price History

ただし、
将来機能を追加する場合も、
Ticketを不必要に複雑化しない。

---

# Design Principles

- TicketはProduction単位の販売条件である。
- TicketはTicket TypeとPriceの組み合わせである。
- Ticket MasterはProductionごとに持つ。
- Ticket TypeとPriceをGlobal Masterとして管理しない。
- Ticketの正本はProductionに所属するTicketである。
- Reservationは予約時点の取引Factである。
- Ticket価格変更を過去Reservationへ遡及させない。
- Ticket Type変更を過去Reservationへ遡及させない。
- QR TicketはReservationから生成されるArtifactである。
- SeatはVersion 1.0では実装しない。
- Ticketは原則として物理削除しない。
- Ticket管理権限はRoleまたはProduction Delegateで管理する。
- Participant Typeは権限を表さない。
- Blueprintを唯一の設計基準とする。
