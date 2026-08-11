# StageArt Blueprint

# Domain Model : Performance

Version : 4.0

---

# Purpose

Performanceは、
Productionにおける一つの公演回を管理するDomainである。

Performanceは、
観客が実際に来場する一回の上演を表す。

Reservationおよび当日受付は、
Performance単位で行う。

---

# Concept

Performanceは、
Productionにおける個別の上演回を表す。

例：

8月1日 14:00
8月1日 18:00
8月2日 13:00

一つのProductionは、
一つ以上のPerformanceを持つことができる。

Productionが「公演」そのものを表すのに対し、
Performanceは「その公演の何回目の上演か」を表す。

---

# Identity

PerformanceはPerformanceIdによって一意に識別する。

日時は識別子ではない。

同日時のPerformanceが存在しても、
システム上はPerformanceIdによって区別する。

---

# Relationship

Performanceは必ず一つのProductionに所属する。

Production
  ↓
Performance

Performanceには、
その公演回に対するReservationが関連する。

Production
  ↓
Performance
  ↓
Reservation

ReservationのAggregate Ruleは、
Reservation Domainが管理する。

---

# Schedule

Performanceは、
以下の上演スケジュール情報を保持する。

- 開場日時
- 開演日時
- 終演予定日時
- タイムゾーン

Performanceの日時は、
公演回を識別・公開するための基本情報となる。

---

# Venue

Performanceは、
開催場所に関する情報を保持する。

例：

- 会場
- ホール
- ステージ

会場情報の詳細については、
Venue関連Domainの責務に応じて管理する。

---

# Capacity

Performanceは、
販売可能な人数・席数を管理するための
Capacity情報を持つことができる。

Version 1.0では、
座席そのものを管理するSeat Domainは実装しない。

満席判定などの詳細なルールは、
Reservation Domainと連携して管理する。

---

# Status

Performanceは以下の状態を持つ。

- DRAFT
- PUBLISHED
- SOLD_OUT
- FINISHED
- CANCELLED

---

# Draft

Performanceが作成されたが、
まだ一般公開されていない状態。

---

# Published

Performanceが一般公開され、
観客が予約可能な状態。

---

# Sold Out

予約可能数に達し、
新規予約を受け付けない状態。

---

# Finished

Performanceが終了した状態。

Performanceは終了後も削除しない。

過去の上演履歴として保持する。

---

# Cancelled

Performanceが中止された状態。

Performanceを物理削除せず、
中止されたというFactを保持する。

既存Reservationへの対応は、
Performance Cancellationに伴うBusiness Processとして処理する。

---

# Reservation

Reservationは、
Performanceに対する観客の予約というFactを表す。

基本構造：

Production
  ↓
Performance
  ↓
Reservation

Reservationは必ず対象となるPerformanceを持つ。

Performanceは、
そのPerformanceに関連するReservationを取得できる。

ただし、

- Reservationの作成
- Reservationの変更
- Reservationのキャンセル
- Reservationの状態管理

などはReservation Domainが管理する。

---

# Reservation Count

Performanceでは、
関連するReservationから以下の情報を集計できる。

- 予約件数
- 予約人数
- 残席・残数
- キャンセル件数

集計値をPerformance自身のFactとして
重複保存しないことを基本とする。

必要に応じて、
パフォーマンス上の理由からCacheや集計値を保持する場合でも、
Reservationを正本とする。

---

# Check In

受付はPerformance単位で実施する。

受付担当者は、
受付開始時に対象となるProductionおよびPerformanceを選択する。

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

Seatを個別にCheck Inすることはしない。

Reservation全体をCheck Inする。

Reservation
  ↓
CHECKED_IN

という単位で来場を確定する。

---

# Check In Validation

Check Inを実行する際は、
受付中のPerformanceと
Reservationが対象としているPerformanceが
一致していることを確認する。

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

---

# QR Check In

QRチケットに含まれる情報を利用して、
対象Reservationを特定できる。

QR Check Inでは、

1. QR情報を読み取る
2. Reservationを特定する
3. 対象Performanceを確認する
4. Reservationの状態を確認する
5. Check Inを実行する

というFlowを基本とする。

QR情報そのものをPerformanceのFactとして保持しない。

QR TicketはTicket / Reservation関連DomainのArtifactとして扱う。

---

# Manual Check In

受付担当者は、
Reservation一覧から手動でCheck Inできる。

予約番号や氏名など、
Reservationを特定するための検索条件を利用できる。

Manual Check InとQR Check Inは、
最終的に同じReservation Check Inとして扱う。

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

# Audience History

Performanceそのものを、
観劇履歴の正本として管理しない。

観客が実際に観劇したというFactは、
ReservationのCheck Inによって確定する。

基本的なFlow：

Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

CheckInCompletedは、
観劇実績の確定を表すBusiness Eventである。

これにより、
予約しただけで実際には来場しなかった場合と、
実際に観劇した場合を区別できる。

---

# Check In and Accounting

CheckInCompletedは、
チケット売上をAccounting Domainへ連携する契機となる。

基本的なFlow：

Reservation
  ↓
Issued Ticket
  ↓
Check In
  ↓
CheckInCompleted
  ├── History
  │     └── Audience History
  │
  └── Accounting
        └── Ticket Revenue

Performance Domainは、
仕訳そのものを管理しない。

Accounting Domainが、
CheckInCompletedなどのBusiness Eventを受け取り、
必要なJournal Entryを生成する。

---

# Ticket Revenue

チケット売上は、
チケットを予約した時点では
正式な会計上の売上として確定しない。

Check Inが完了し、
CheckInCompletedが発生した時点で、
チケット売上をAccounting Domainへ連携する。

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
Journal Entry

具体的な勘定科目やDebit / Creditの処理は、
Accounting Domainで定義する。

---

# Performance Completion

PerformanceがFINISHEDとなった場合、
そのPerformanceが終了したことを表す。

Performance終了時に、
過去のProduction / Performance情報を削除しない。

Performanceに関連するReservationやCheck InなどのFactも保持する。

---

# Performance Cancellation

PerformanceがCANCELLEDとなった場合でも、
既存Reservationを物理的に削除しない。

Reservationへの対応は、
Performance Cancellationに伴うBusiness Processとして処理する。

例えば、

- 中止通知
- 予約キャンセル
- 払い戻し
- 振替案内

などを必要に応じて実行する。

これらの処理をPerformance Domain自身へ
過度に集約しない。

---

# Ticket Relationship

TicketはProduction単位で管理する。

Performanceは、
Productionに設定されたTicket Type / Priceの
販売対象となる。

基本構造：

Production
  ↓
Ticket Type / Price
  ↓
Performance
  ↓
Reservation

Ticket Type / Priceの正本はProduction側にある。

Performanceは、
Productionに設定されたTicket Type / Priceを
販売対象として利用する。

Performanceごとに、
利用可能なTicket Typeを設定できる構造を将来的に持たせる。

Ticket DomainがTicketに関する詳細なルールを管理する。

---

# Seat

Seatは将来実装する。

Version 1.0では、
Performanceに対するSeat Domainを実装しない。

したがってVersion 1.0では、

- 座席マスター
- 座席レイアウト
- 座席指定
- 連席
- Reservation Seat
- 座席単位の予約状態

などをPerformance Domainでは管理しない。

---

# Future Seat Relationship

将来的には、

Production
  ↓
Performance
  ↓
Seat

および、

Reservation
  ↓
Reservation Seat
  ↓
Seat

という構造へ拡張できる。

ただし、
Seatを実装した場合でも、
Seat自身に予約状態を持たせるのではなく、
Reservation側のFactから予約状態を判断する設計を基本とする。

---

# Future Timetable Relationship

TimetableはProductionに関連する。

Performanceは、
公演回の日時を管理する。

小屋入り後の詳細な進行や
稽古内容をPerformanceに直接持たせない。

Timetable Domainで管理する。

---

# Performance and Participant

Performanceは、
出演者やスタッフを直接管理しない。

出演者・スタッフはProductionのParticipantで管理する。

基本構造：

Production
  ↓
Participant

Performanceは、
Productionに所属するParticipantを
必要に応じて参照する。

Performanceごとに出演者が異なる場合など、
将来的に必要となった場合は別Domainとして拡張する。

---

# Performance and Budget

Performanceは、
Budgetを直接管理しない。

BudgetはProduction単位で管理する。

基本構造：

Production
  ↓
Budget

Performance単位の収支を必要とする場合は、
将来的なAccounting / Production Actualの拡張として扱う。

---

# Visibility

Performanceの公開状態は、
Productionの公開状態と整合させる。

Productionが一般公開されていない場合、
そのProductionに所属するPerformanceも
一般観客向けに公開しない。

Productionが公開されている場合、
公開対象となったPerformanceを
一般観客向けに表示できる。

---

# Business Rules

- PerformanceはProductionに所属する。
- Performanceは一つの上演回を表す。
- 一つのProductionは複数のPerformanceを持てる。
- PerformanceはPerformanceIdによって一意に識別する。
- 開場日時、開演日時、終演予定日時、タイムゾーンを管理する。
- Performanceは開催場所を管理する。
- Performanceは状態を持つ。
- Performance終了後も削除しない。
- PerformanceがCancelledとなっても既存Reservationを物理削除しない。
- ReservationはPerformance単位で管理する。
- ReservationのAggregate RuleはReservation Domainが管理する。
- Check InはPerformance単位の受付として実施する。
- Check Inの対象はReservationである。
- Seatを個別にCheck Inしない。
- QR Check InとManual Check Inは同じReservation Check Inとして扱う。
- Check In時には対象Performanceの一致を検証する。
- Performance自身は個々のReservationのCheck In状態を保持しない。
- Check In状態はReservationを正本として判断する。
- CheckInCompletedは観劇実績を確定するBusiness Eventである。
- CheckInCompletedを契機としてAudience Historyを生成する。
- CheckInCompletedを契機としてTicket RevenueをAccounting Domainへ連携する。
- Performance DomainはJournal Entryを直接管理しない。
- Ticket Type / PriceはProduction単位で管理する。
- PerformanceはProductionに設定されたTicketを販売対象として利用する。
- PerformanceはParticipantを直接管理しない。
- 出演者・スタッフはProductionのParticipantで管理する。
- PerformanceはBudgetを直接管理しない。
- BudgetはProduction単位で管理する。
- SeatはVersion 1.0では実装しない。
- 座席指定はVersion 1.0では実装しない。
- Reservation SeatはVersion 1.0では実装しない。
- PerformanceはProductionの公開状態と整合する。

---

# Domain Events

Performanceに関する主なDomain Event：

- PerformanceCreated
- PerformanceUpdated
- PerformancePublished
- PerformanceSoldOut
- PerformanceFinished
- PerformanceCancelled

Check Inに関するEventは、
Check In Domainで定義する。

CheckInCompletedを契機として、
History DomainがAudience Historyを生成する。

CheckInCompletedを契機として、
Accounting DomainがTicket Revenueを会計へ連携する。

Performance Domain自身が、
HistoryやJournal Entryを直接生成・更新しない。

---

# Design Decisions

Performanceは、
Productionにおける個別の上演回を管理する。

Performanceは、
予約・受付・来場確認の単位となる。

ReservationはPerformance単位で管理する。

受付はPerformanceを選択して開始し、
Check InはReservation単位で行う。

QR Check InとManual Check Inは、
同じReservation Check Inとして扱う。

Check Inが完了すると、
CheckInCompletedが発生する。

CheckInCompletedを契機として、

- Audience History
- Ticket Revenue

をそれぞれのDomainへ連携する。

HistoryはPerformanceの子Entityではなく、
History Domainで管理する。

Ticket RevenueおよびJournal Entryは、
Accounting Domainで管理する。

Seatおよび座席指定は将来実装する。

そのためVersion 1.0では、
PerformanceからSeatを管理しない。

Performanceは出演者・スタッフを直接管理せず、
ProductionのParticipantを参照する。

PerformanceはBudgetを直接管理せず、
ProductionのBudgetを参照する。

Performance終了後も、
公演回および関連Factを保持する。

---

# Future

将来的に以下へ対応する。

- 座席管理
- 座席指定
- 連席管理
- Reservation Seat
- 上演時間変更
- 開演遅延
- 中止
- 振替公演
- 配信公演
- ライブビューイング
- リアルタイム座席状況
- Performance単位の予実分析

ただし、
将来機能を追加する場合も、
Performanceの責務を不必要に拡張しない。

---

# Design Principles

- PerformanceはProductionに所属する。
- Performanceは上演回を表す。
- Performanceは予約・受付・来場確認の単位となる。
- ReservationはPerformance単位で管理する。
- ReservationのAggregate RuleはReservation Domainが管理する。
- Check InはReservation単位で行う。
- QR Check InとManual Check Inを同じReservation Check Inとして扱う。
- Check In時には対象Performanceを検証する。
- Performanceは個々のReservationのCheck In状態を保持しない。
- ReservationをCheck Inの正本とする。
- CheckInCompletedは観劇実績を確定するBusiness Eventである。
- CheckInCompletedからAudience Historyを生成する。
- CheckInCompletedからTicket RevenueをAccounting Domainへ連携する。
- Ticket Type / PriceはProduction単位で管理する。
- ParticipantはProduction単位で管理する。
- BudgetはProduction単位で管理する。
- Performance DomainはJournal Entryを管理しない。
- SeatはVersion 1.0では実装しない。
- 座席指定はVersion 1.0では実装しない。
- Reservation SeatはVersion 1.0では実装しない。
- Performanceは終了後も削除しない。
- Performanceは過去の上演Factを保持する。
- Blueprintを唯一の設計基準とする。
