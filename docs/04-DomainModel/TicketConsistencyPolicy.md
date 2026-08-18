# StageArt Blueprint

# Domain Consistency Policy : Ticket

Version : 1.0

---

# Purpose

本書はTicket Domainについて、現在のCanonical Domain Modelおよび確定したTicket仕様との整合性を定義する。

---

# Canonical Position

TicketはProductionに所属するProduction固有の販売条件である。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Ticket
    ↓
Performance
    ↓
Reservation

TicketをOrganization横断の共通商品Masterとして扱わない。

---

# Two-Axis Pricing Model

Ticket料金は、最大2つの軸による組み合わせで管理できる。

標準的な軸：

第1軸：料金区分
- 一般
- 学生
- その他

第2軸：販売区分
- 前売
- 当日
- その他

ただし、各軸は使用しないことを選択できる。

したがって以下をすべて許容する。

- 2軸を使用する
- 第1軸のみ使用する
- 第2軸のみ使用する
- 2軸とも使用しない

---

# Matrix

2軸を使用する場合、Ticketは軸の組み合わせによる料金Matrixとして管理できる。

例：

|  | 前売 | 当日 |
|---|---:|---:|
| 一般 | 3000 | 3500 |
| 学生 | 2000 | 2500 |

全組み合わせを必須としない。

不要な組み合わせは販売対象外として扱える。

---

# Single Axis

第1軸のみを使用する場合：

| 料金区分 | 価格 |
|---|---:|
| 一般 | 3000 |
| 学生 | 2000 |

第2軸のみを使用する場合：

| 販売区分 | 価格 |
|---|---:|
| 前売 | 3000 |
| 当日 | 3500 |

---

# No Axis

2軸とも使用しない場合、Productionにおける単純な一律料金として扱える。

例：

チケット料金：3000円

この場合もTicket Entity自体は使用し、Ticket Typeを無理に二軸へ分類しない。

---

# Axis Labels

軸そのものを固定的なDatabase Enumとして限定しない。

StageArt UIでは標準候補として「料金区分」「販売区分」を提示するが、将来的な拡張を妨げない設計とする。

利用者が標準候補以外の区分を必要とする場合にも対応可能な構造を維持する。

---

# Display Name

各Ticketには観客向けDisplay Nameを設定できる。

例：

- 一般前売
- 学生前売
- 一般当日
- 招待

内部的な軸・区分と、公開ページ上の表示名は分離できる。

---

# Price

TicketのPriceは販売条件として管理する。

Price = 0を許可する。

無料招待、関係者、モニター等を表現できる。

---

# Performance Relationship

Ticketの正本はProductionに存在する。

Performanceは、所属ProductionのTicketを販売対象として利用する。

Performanceごとの利用可能Ticketを制御する必要がある場合は、Performance側にAvailability設定を持たせる。

TicketそのものをPerformanceごとに複製しない。

---

# Reservation Relationship

Reservationは必ずPerformanceを対象とし、選択されたTicketを参照する。

基本構造：

Production
    ↓
Ticket
    ↓
Performance
    ↓
Reservation
        ↓
      Ticket

Reservation成立時には、その時点のTicket PriceをPrice Snapshotとして保持する。

Ticket Priceの後日変更によって過去Reservationの取引価格を変更してはならない。

---

# Ticket Status

Ticketは販売Lifecycleを持つ。

基本状態：

- DRAFT
- ON_SALE
- SUSPENDED
- CLOSED

StatusとPublic Visibilityは別概念として管理する。

---

# Public Visibility

Ticketは一般観客へ表示するかどうかを制御できる。

一般公開Ticketだけでなく、招待・関係者等の内部向けTicketも表現できる。

---

# Production Public Page

Productionが一般公開される場合でも、Ticket情報が未設定または未公開である場合がある。

その場合、Production Public PageではTicket情報をComing Soon等で表示できる。

Ticketが未設定であることを理由にProduction Public Pageそのものを生成してはならない。

Public Page生成可否はProductionのInformation Public状態で決定する。

---

# Revenue Boundary

Ticket Priceは販売条件であり、会計上の売上そのものではない。

Reservationの成立だけで確定売上とはしない。

CheckInCompletedを契機としてTicket RevenueをAccounting Domainへ連携するという既存方針を維持する。

Ticket DomainはJournal Entryを直接管理しない。

---

# Deletion

過去Reservationから参照されるTicketは物理削除しない。

販売終了時はCLOSED等の状態で保持する。

これにより過去Reservationの取引条件を追跡可能とする。

---

# Business Rules

- TicketはProductionに所属する。
- TicketはProduction固有の販売条件を表す。
- Ticket料金は最大2軸で構成できる。
- 第1軸・第2軸はいずれも使用しないことができる。
- 2軸使用時はMatrixとして扱える。
- 不要な組み合わせは販売対象外にできる。
- 標準候補は料金区分＝一般/学生、販売区分＝前売/当日とする。
- 軸や区分の将来拡張を妨げない。
- Ticketには観客向けDisplay Nameを設定できる。
- Price = 0を許可する。
- TicketはPerformanceごとに複製しない。
- PerformanceごとのTicket Availabilityは別設定として扱える。
- ReservationはPerformanceとTicketを参照する。
- Reservation成立時にPrice Snapshotを保持する。
- Ticketの現在価格変更で過去Reservationを変更しない。
- Ticket StatusとPublic Visibilityを分離する。
- 過去Reservationから参照されるTicketを物理削除しない。
- Ticket PriceとTicket Revenueを同一視しない。
- Ticket DomainはJournal Entryを管理しない。
