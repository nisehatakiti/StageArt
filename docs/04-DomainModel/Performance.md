# StageArt Blueprint
# Domain Model : Performance

Version : 1.0

---

# Purpose

PerformanceはProductionにおける一つの公演回を管理するドメインである。

予約・受付・座席管理はすべてPerformance単位で行う。

Productionが「作品」であるのに対し、

Performanceは「上演回」を表す。

---

# Concept

Performanceは観客が実際に来場する一回の上演を表す。

例）

8月1日 14:00

8月1日 18:00

8月2日 13:00

一つのProductionは一つ以上のPerformanceを持つ。

---

# Identity

PerformanceはPerformanceIDによって一意に識別する。

日時は識別子ではない。

同日時のPerformanceが存在しても問題ない。

---

# Relationship

Performanceは必ず一つのProductionへ所属する。

```
Production
    │
    └── Performance
            ├── Seat
            └── Reservation
```

Performanceは

- Seat
- Reservation

を管理する。

---

# Schedule

Performanceは以下を保持する。

- 開演日時
- 開場日時
- 終演予定日時
- タイムゾーン

---

# Venue

Performanceは開催場所を保持する。

- 会場
- ホール
- ステージ

将来的には座席レイアウトとも関連する。

---

# Capacity

Performanceは販売可能席数を保持する。

満席判定はReservationから算出する。

---

# Status

Performanceは以下の状態を持つ。

- Draft
- Published
- Sold Out
- Finished
- Cancelled

---

# Reservation

ReservationはPerformance単位で管理する。

PerformanceはReservation一覧を保持する。

受付もPerformance単位で実施する。

---

# Seat

PerformanceはSeatを保持する。

Seatは座席情報のみを管理する。

予約状態は保持しない。

予約状況はReservationから判断する。

---

# Check In

受付はPerformance単位で実施する。

QRコード読取

予約番号検索

氏名検索

などによって受付を行う。

受付状態はReservationによって管理する。

---

# Design Decisions

Performanceは上演回のみを管理する。

以下は保持しない。

- 出演者
- スタッフ
- 予算
- 稽古
- ドキュメント

出演者はProductionが管理する。

制作はProjectが管理する。

---

# Future

将来的に以下へ対応する。

- 上演時間変更
- 開演遅延
- 中止
- 振替公演
- 配信公演
- ライブビューイング

---

# Design Principles

- Performanceは上演回を表す。
- ReservationはPerformance単位で管理する。
- SeatはPerformanceへ所属する。
- 出演者はProductionが管理する。
- 制作情報はProjectが管理する。
- Performanceは終了後も削除しない。
