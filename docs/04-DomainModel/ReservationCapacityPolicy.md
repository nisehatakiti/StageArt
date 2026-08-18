# StageArt Blueprint

# Domain Model : Reservation Capacity Policy

Version : 1.0

---

# Purpose

Reservationの定員は、Production単位ではなくPerformance単位で管理する。

一つのProductionに複数のPerformanceが存在する場合でも、公演回ごとに予約受付可能人数を設定できるようにする。

---

# Capacity Ownership

予約定員の実際の適用対象はPerformanceとする。

基本構造：

Production
  ↓
Performance
  ↓
Reservation

Reservationは必ず一つのPerformanceに所属するため、定員もPerformance単位で判定する。

---

# Production Default

Productionには、Production全体の標準予約定員を設定できる。

Production登録時に設定した定員は、各Performanceの初期値として継承できる。

例：

Production
  標準予約定員 = 100

Performance A
  定員 = 100

Performance B
  定員 = 100

Performance C
  定員 = 100

---

# Performance Override

Performanceは、Productionから継承した定員を個別に変更できる。

例：

Production
  標準予約定員 = 100

Performance A
  100

Performance B
  80 ← 個別変更

Performance C
  100

Performanceごとの変更は、そのPerformanceにのみ適用する。

---

# Inheritance Rule

Performance作成時は、Productionの標準予約定員を初期値として設定する。

その後Productionの標準予約定員を変更した場合、既に作成済みのPerformanceの個別定員を自動的に上書きしてはならない。

Performance作成後にProductionの標準値を変更する場合、その変更を既存Performanceへ反映するかどうかは、明示的な管理操作として扱う。

---

# Reservation Capacity

Reservationを作成する際、対象Performanceの現在の予約定員を参照する。

基本式：

既存の有効予約人数 + 新規ReservationのGuestCount <= Performance Capacity

を満たす必要がある。

Capacityを超えるReservationを通常の予約として作成してはならない。

---

# Reservation Count

定員判定に使用する予約人数は、ReservationのGuestCountを基準とする。

例えば、

Performance Capacity = 100

既存有効予約 = 98名

新規Reservation GuestCount = 3名

の場合、通常予約は不可とする。

---

# Cancelled Reservation

CANCELLEDとなったReservationは、予約定員の使用人数に含めない。

これにより、キャンセルされた人数分の予約枠を再利用できる。

---

# Checked In / No Show

CHECKED_INおよびNO_SHOWのReservationは、当該Performanceの予約履歴として保持する。

定員判定では、予約受付時点の有効予約を基準とし、過去のReservation履歴を削除して定員を再計算しない。

具体的な締切後の定員解放ルールは、別途Business Processで定義する。

---

# Capacity Zero / Unlimited

Capacityの意味を曖昧にしないため、Version 1.0では「0 = 無制限」といった暗黙ルールを設けない。

予約受付を行わない場合は、Ticket / Reservationの受付状態によって制御する。

---

# Public Page

Public Production Pageでは、必要に応じてPerformanceの定員そのものを表示する必要はない。

定員に達して予約受付を停止した場合は、予約受付状態として「受付終了」等を表示できる。

---

# Business Rules

1. 予約定員の適用対象はPerformanceとする。
2. Productionには標準予約定員を設定できる。
3. Performance作成時、Productionの標準予約定員を初期値として継承する。
4. Performanceは継承した定員を個別に変更できる。
5. Performanceごとの個別変更は他のPerformanceへ影響しない。
6. Productionの標準定員変更で既存Performanceを暗黙に上書きしない。
7. Reservation作成時は対象Performanceの定員を使用する。
8. GuestCountを人数として定員判定する。
9. 有効予約人数と新規GuestCountの合計がCapacityを超える通常予約を許可しない。
10. CANCELLEDのReservationは定員使用人数に含めない。
11. 定員を超えた場合はReservationを作成せず、受付状態を適切に表示する。
12. Capacityの0を無制限として扱わない。
