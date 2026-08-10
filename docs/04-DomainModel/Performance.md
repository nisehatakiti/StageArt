# StageArt Blueprint
# Domain Model : Performance

Version : 2.0

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

PerformanceはPerformanceIdによって一意に識別する。

日時は識別子ではない。

同日時のPerformanceが存在しても問題ない。

---

# Relationship

Performanceは必ず一つのProductionへ所属する。

Production
    │
    └── Performance
            ├── Seat
            └── Reservation
                    └── ReservationSeat

Performanceは、
その公演回に必要なSeatを管理する。

Reservationは、
Performanceに対する予約を表す。

ただしReservationのAggregate Ruleは
Reservation Domainが管理する。

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

満席判定はReservationの予約状況から算出する。

Seat自体は予約状態を保持しない。

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

Performanceは、
そのPerformanceに属するReservationを取得できる。

Reservationそのものの作成・更新・キャンセルは
Reservation Domainが管理する。

Reservationは以下のような関係を持つ。

Performance
    ↓
Reservation

ReservationはPerformanceに対して作成されるため、
Reservationには必ず対象Performanceが存在する。

---

# Reservation Seat

ReservationSeatは、
Reservationが予約しているSeatを表す。

ReservationSeatはReservation Aggregate内部で管理する。

PerformanceはSeatを管理するが、
ReservationSeatを直接管理しない。

関係は以下のようになる。

Performance
    ↓
Seat

Reservation
    ↓
ReservationSeat
    ↓
Seat

ReservationSeatは、
Performanceに存在するSeatを参照する。

---

# Seat

PerformanceはSeatを保持する。

Seatは座席情報のみを管理する。

Seat自体は予約状態を保持しない。

例えば、

Seat
    = A-12

について、

「予約済み」
「空席」
「チェックイン済み」

といった状態をSeat自身には保持しない。

Seatの予約状況はReservationおよびReservationSeatから判断する。

---

# Seat Reservation

ReservationがSeatを予約すると、
ReservationSeatによってSeatとの関係を保持する。

例えば、

Reservation
    ↓
ReservationSeat
    ├── A-12
    └── A-13

の場合、

A-12
A-13

はそのReservationによって予約されていると判断する。

---

# Seat Addition

Reservationの人数変更によって、
追加のSeatが必要になる場合がある。

例えば、

2名
    ↓
3名

へ変更した場合、
追加のSeatを確保する。

追加されたSeatは、
ReservationSeatとしてReservationに追加する。

Seatそのものの情報は変更しない。

---

# Seat Release

Reservationの人数変更によって、
不要になったSeatはReservationから解放する。

例えば、

3名
    ↓
2名

へ変更した場合、
不要になったReservationSeatを解放する。

解放されたSeatは、
そのReservationによる予約がなくなるため、
再び予約可能なSeatとして扱われる。

---

# Seat Change

Reservationの変更によって、
予約するSeatそのものを変更する場合がある。

その場合は、

既存ReservationSeatを解放
        ↓
新しいSeatをReservationSeatとして追加

という形でReservation側の状態を変更する。

Seat自体の情報は変更しない。

---

# Consecutive Seats

座席指定があるPerformanceでReservation人数を変更した場合、
連続したSeatを確保できない可能性がある。

例えば、

2名
A-12 / A-13

から、

3名

へ変更した場合、

A-12 / A-13 / B-12

のように連席にならない場合がある。

そのため、
座席指定があるPerformanceでは、
人数変更によって連席を確保できない可能性があることを
予約者へ事前に告知する。

---

# Check In

受付はPerformance単位で実施する。

Check Inを開始する前に、
受付担当者はProductionおよびPerformanceを選択する。

Production
    ↓
Performance
    ↓
Check In受付開始

選択されたPerformanceが、
その受付でCheck In対象となる公演回である。

---

# Check In Target

Check Inの対象はReservationである。

SeatはCheck Inの対象ではない。

例えば、

Reservation
    ├── A-10
    ├── A-11
    └── A-12

という3席のReservationであっても、
A-10、A-11、A-12を個別にCheck Inすることはない。

Reservation全体をCheck Inする。

Reservation
    ↓
CHECKED_IN

という単位で来場を確定する。

---

# Check In Validation

Check Inを実行する際は、
受付中のPerformanceと
ReservationのPerformanceが一致していることを確認する。

例えば、

受付中Performance
    = 10月12日 14:00

Reservation
    = 10月12日 18:00

の場合、

Check In
    = 不可

とする。

これにより、
別の公演回に予約されたReservationを
誤ってCheck Inすることを防止する。

---

# Check In Methods

Check Inは以下の方法で実行できる。

- Reservation一覧からの手動Check In
- QRコードによるCheck In

どちらの場合も、
最終的にはReservation単位のCheck Inとして扱う。

受付状態はReservationによって管理する。

---

# Check In Status

Performance自身は、
個々のReservationのCheck In状態を保持しない。

ReservationがCHECKED_INとなった場合、
そのReservationがCheck In済みと判断する。

Performance単位の受付画面では、
Reservationの状態から以下を集計できる。

- 予約人数
- 未チェックイン人数
- チェックイン済み人数

---

# Unchecked In List

受付中のPerformanceでは、
未チェックインのReservation一覧を表示できる。

ReservationがCheck Inされると、
未チェックイン一覧から除外する。

Check In済みReservationは、
別途チェックイン済み一覧から確認できる。

---

# Performance Cancellation

PerformanceがCancelledとなった場合でも、
既存Reservationを物理的に削除しない。

Reservationへの対応は、
Performance Cancellationに伴う別Business Processとして処理する。

払い戻しなどの処理はPerformance Domain自身ではなく、
必要なBusiness Processによって実行する。

---

# Performance Completion

PerformanceがFinishedとなった場合、
そのPerformanceが終了したことを表す。

Audience Historyは、
PerformanceFinishedそのものではなく、
ReservationCheckedInを契機として生成する。

ReservationCheckedIn
    ↓
History Domain
    ↓
Audience History

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

ReservationのAggregate RuleはReservation Domainが管理する。

SeatはPerformanceに所属する。

ReservationSeatはReservationに所属する。

---

# Future

将来的に以下へ対応する。

- 上演時間変更
- 開演遅延
- 中止
- 振替公演
- 配信公演
- ライブビューイング
- リアルタイム座席状況

---

# Design Principles

- Performanceは上演回を表す。
- PerformanceはProductionに所属する。
- ReservationはPerformance単位で管理する。
- ReservationのAggregate RuleはReservation Domainが管理する。
- SeatはPerformanceへ所属する。
- Seatは座席情報のみを管理する。
- Seat自身は予約状態を保持しない。
- ReservationSeatはReservationに所属する。
- ReservationSeatはReservation Aggregate内部で管理する。
- ReservationSeatはPerformanceのSeatを参照する。
- Reservationの人数変更によってSeatを追加できる。
- Reservationの人数変更によって不要なSeatを解放する。
- Reservationの座席変更によってReservationSeatを変更する。
- 座席指定公演では人数変更によって連席を確保できない場合がある。
- Seatは個別にCheck Inしない。
- Check InはReservation単位で行う。
- Check In対象Performanceを受付開始時に選択する。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- 手動Check InとQR Check Inは同じReservation Check Inとして扱う。
- Performanceは個々のReservationのCheck In状態を保持しない。
- Reservationの状態からCheck In状況を判断する。
- Performance単位で予約人数・未チェックイン人数・チェックイン済み人数を集計できる。
- Performanceは終了後も削除しない。
- Audience HistoryはReservationCheckedInを契機としてHistory Domainが生成する。
- 出演者はProductionが管理する。
- 制作情報はProjectが管理する。
