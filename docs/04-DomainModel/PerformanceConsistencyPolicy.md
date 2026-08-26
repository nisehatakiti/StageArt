# StageArt Blueprint

# Domain Consistency Policy : Performance

Version : 1.0

---

# Purpose

本書はPerformance Domainについて、現在のStageArt Canonical Domain ModelおよびDomainMapとの整合性を定義する。

既存のPerformance.mdを基礎とし、後から確定したVenue、Capacity、Ticket、Reservation、Public Page等の仕様を優先仕様として整理する。

---

# Canonical Position

Performanceは、Productionにおける具体的な一回の上演を表す。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Performance

Productionが「公演・活動」そのものを表し、Performanceがその公演における個別の公演回を表す。

---

# Identity

PerformanceIdを一意識別子とする。

日時や会場名をPerformanceIdとして使用しない。

日時変更後もPerformanceIdは変更しない。

---

# Venue

VenueはProductionに直接紐づく。

PerformanceはVenueを直接所有・設定しない。

基本構造：

Production
    ↓
Venue

Production
    ↓
Performance

同一Productionに属するPerformanceは、原則としてProductionに設定された同一Venueで実施する。

初期仕様では、Productionは一つのVenueを持つことを基本とする。

複数会場の企画は、Project配下に複数Productionを作成して表現する。

例：

Project「河童ホームラン2027」
    ├─ Production「東京公演」
    │    └─ Venue A
    └─ Production「大阪公演」
         └─ Venue B

PerformanceにVenueを個別入力する構造は採用しない。

---

# Schedule

Performanceは、個別公演回の日時情報を保持する。

基本情報：

- 公演日
- 開演時刻
- 必要に応じた開場時刻
- 必要に応じた終演予定時刻

StageArtの日時は、OrganizationのTimezoneを基準として管理する。

Performanceごとに別Timezoneを設定することを基本としない。

---

# Capacity

予約定員の適用単位はPerformanceである。

Productionには標準予約定員を設定できる。

Performance作成時に、Productionの標準予約定員を継承する。

Performanceでは、継承した定員を個別に変更できる。

基本構造：

Production
    ↓
Default Reservation Capacity
    ↓
Performance
    ↓
Performance Reservation Capacity
    ↓
Reservation

例えばProductionの標準予約定員が100名の場合：

Production
    Default Capacity = 100

Performance A
    Capacity = 100

Performance B
    Capacity = 80

Performance C
    Capacity = 100

のように、Performance Bだけ個別Overrideできる。

Productionの標準予約定員を後から変更しても、既存Performanceの個別設定を無条件に上書きしてはならない。

---

# Capacity Responsibility

定員は「予約を受け付けられる人数」を表す。

予約可能数の判定はReservation Domainが担当する。

Performanceは、自身のReservation Capacityを提供するが、Reservationの状態や人数Factを二重管理しない。

Reservationの人数集計を正本として、残数・満席判定等を算出する。

初期仕様では座席指定を実装しないため、Capacityは基本的に人数単位で扱う。

---

# Reservation Relationship

Reservationは必ず一つのPerformanceを対象とする。

基本構造：

Production
    ↓
Performance
    ↓
Reservation

予約定員の判定もPerformance単位で行う。

Reservationがキャンセルされた場合、そのReservationは通常の予約定員消費から除外する。

具体的なReservation StatusおよびCapacity計算ルールはReservation Domainで定義する。

---

# Ticket Relationship

TicketはProduction単位で設定する。

PerformanceはProductionに設定されたTicket Type / Priceを利用する。

基本構造：

Production
    ↓
Ticket Pricing Matrix
    ↓
Performance
    ↓
Reservation

Performance自身がTicket価格の正本を持つことは基本としない。

必要に応じて、特定Performanceで利用可能なTicket区分を制限することは許可するが、価格定義そのものの正本はProduction側に置く。

---

# Ticket Pricing Matrix

ProductionのTicket Pricingは、二つの軸を利用できる。

第1軸の標準候補：

- 一般
- 学生
- その他

第2軸の標準候補：

- 前売
- 当日
- その他

二軸を両方使用する場合、マトリックスとして価格を設定できる。

例：

|  | 前売 | 当日 |
|---|---:|---:|
| 一般 | 3000 | 3500 |
| 学生 | 2000 | 2500 |

以下も許可する。

- 第1軸のみ使用
- 第2軸のみ使用
- 両軸を使用しない一律料金

軸の具体的な選択肢は追加可能とする。

Ticketの詳細ルールはTicket Domainで定義する。

---

# Lifecycle

Performanceは個別公演回の状態を管理する。

基本状態：

- DRAFT
- PUBLISHED
- SOLD_OUT
- FINISHED
- CANCELLED

ただし、Performanceの公開状態とProductionの公開状態は同一概念ではない。

Productionが一般公開されていない場合、Performanceだけを一般公開してはならない。

Productionが公開された後、Performance情報が未確定の場合でも、Production Public Page側ではComing Soon等の表示が可能である。

---

# Public Availability

ProductionのPublic Pageは、ProductionのInformation Public状態によって生成・公開される。

PerformanceはProduction Public Pageの中で公演回情報として表示される。

Performanceの日時、チケット、会場等が未設定の場合、該当情報をComing Soonとして表示できる。

PerformanceがDRAFTであることだけを理由にProduction全体のPublic Pageを生成しないという制御はProduction Public Visibility側で行う。

---

# Check In

Check InはPerformance単位で実施する。

受付担当者は、受付対象のPerformanceを選択して処理する。

Check In対象はReservationである。

Performance自身は個々のReservationのCheck In状態を正本として保持しない。

基本構造：

Performance
    ↓
Reservation
    ↓
Check In

Check In完了時には、必要に応じてCheckInCompleted Business Eventを発生させる。

Performance DomainはJournal Entryを直接生成しない。

Accounting DomainがBusiness Eventを受けて会計処理を行う。

---

# Completion

PerformanceがFINISHEDになっても、Performance、Reservation、Check In等のFactを削除しない。

過去の公演回として参照可能な状態を維持する。

Productionが過去公演として公開される場合、Performance情報もそのProductionの履歴として表示対象になり得る。

---

# Cancellation

PerformanceがCANCELLEDになった場合でも、PerformanceおよびReservationを物理削除しない。

中止に伴うReservationのキャンセル、払い戻し、振替案内等はBusiness Processとして処理する。

Performance Domainは中止というFactを保持するが、払い戻し等の詳細な会計・予約処理を過度に内包しない。

---

# Timezone Rule

StageArtの日時はOrganizationのTimezoneで管理する。

Performanceの開場・開演・終演予定時刻もOrganization Timezoneに基づく。

複数TimezoneをまたぐProductionについては、将来必要になった場合に別途拡張する。

---

# Seat

初期仕様ではSeat Domainを実装しない。

したがってPerformanceでは以下を管理しない。

- 座席マスター
- 座席レイアウト
- 座席指定
- 連席
- Reservation Seat
- 座席単位の予約状態

将来的にSeatを導入する場合でも、Performanceの責務とReservationの責務を分離する設計を維持する。

---

# Canonical Relationship Summary

```text
Project
    ↓
Production
    ├── Venue
    ├── Performance
    │     ├── Capacity
    │     └── Reservation
    │            └── Check In
    │
    └── Ticket Pricing Matrix
              ↓
          Performance
```

Performanceは「一回の上演」を表し、VenueはProduction、Ticket PricingはProduction、ReservationはPerformanceを正本となる関連先とする。

---

# Design Principle

Performanceは、Productionの公演回を表す最小の上演単位とする。

会場はProductionで一元管理し、定員はPerformanceへ継承して個別Overrideできるようにする。

Ticket価格はProductionで定義し、ReservationはPerformance単位で管理する。

これにより、通常の小劇場公演における単純な公演回管理を維持しながら、将来的な拡張にも対応できる構造とする。
