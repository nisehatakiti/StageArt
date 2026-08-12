# StageArt Blueprint

# Domain Model : Reservation

Version : 6.0

---

# Purpose

Reservationは、
観客が特定のPerformanceへ予約するというFactを表すDomainである。

Reservationは、
「誰が」「どの公演回を」「どのTicketで」予約したかを管理する。

ReservationはAggregate Rootとして、
予約に関するBusiness Ruleを管理する。

---

# Concept

Reservationは、
特定のPerformanceに対する予約を表す。

基本構造：

Performance
  ↓
Reservation
  ↓
Booker

Reservationは、
観客による予約というFactを管理する。

---

# Responsibility

Reservationは以下を管理する。

- Performance
- Booker
- Ticket
- Price Snapshot
- Guest Count
- Status
- Created By
- Created At
- Updated By
- Updated At

ReservationはHistoryを直接管理しない。

Reservationは、
Ticketそのものの販売条件を変更しない。

---

# Identity

ReservationはReservationIdによって一意に識別する。

ReservationIdは変更しない。

ReservationNumberは、
利用者へ表示する予約番号として利用する。

ReservationNumberはReservation生成時に採番する。

ReservationIdとReservationNumberは別の概念として扱う。

---

# Performance

Reservationは必ず一つのPerformanceに所属する。

ReservationはProductionではなく、
特定のPerformanceに対する予約を表す。

基本構造：

Production
  ↓
Performance
  ↓
Reservation

Reservation作成後、
対象Performanceを変更することはできない。

別のPerformanceへ変更する場合は、
既存Reservationをキャンセルし、
新しいReservationを作成する。

---

# Booker

Bookerは予約者を表す。

BookerはPersonを参照する。

Bookerは、

「誰の予約か」

を表す。

BookerとCreatedByは異なる概念である。

例えば、

Booker
  = 観客A

CreatedBy
  = 劇団スタッフB

という状態を許可する。

---

# Guest Count

GuestCountは、
Reservationによって予約される人数を表す。

Version 1.0では、
同行者を個別のDomainとして管理しない。

例えば、

GuestCount
  = 2

の場合、
2名分の予約として扱う。

GuestCountは、
予約人数および受付時の来場人数集計に利用する。

---

# Companion

StageArtでは、
Companionを独立したDomainとして管理しない。

同行者の氏名やPerson情報を
Reservation内で管理することもしない。

同行者を含む予約人数は、
GuestCountによって表現する。

将来的に同行者管理が必要となった場合は、
別Domainとして設計する。

---

# Ticket

Reservationには、
利用するTicketを設定する。

Ticketは、
Productionに所属する販売条件である。

基本構造：

Production
  ↓
Ticket
  ↓
Performance
  ↓
Reservation

Reservationは、
対象Performanceで利用するTicketを参照する。

例えば、

- 一般 / 3,000円
- 学生 / 2,000円
- 当日券 / 3,500円

などのTicketを利用できる。

Ticketの正本はProduction側にある。

Reservationは、
Ticketの現在の販売条件を変更しない。

---

# Price Snapshot

Reservationは、
予約確定時点のTicket Priceを
取引Factとして保持する。

基本構造：

Ticket
  ↓
Reservation
  ↓
Price Snapshot

Price Snapshotは、
Reservationが成立した時点の販売価格を表す。

例えば、

Ticket
  Price = 3,000円

でReservationを作成した後、

Ticket
  Price = 3,500円

へ変更された場合でも、

既存Reservation
  Price Snapshot = 3,000円

を維持する。

Ticketの現在Priceを参照して、
過去のReservationの価格を再計算してはならない。

---

# Ticket and Price

TicketはProductionにおける現在の販売条件である。

Reservationは、
予約成立時点の販売条件を取引Factとして保持する。

したがって、

Ticket.Price
  = 現在の販売条件

Reservation.Price Snapshot
  = 予約成立時点の取引価格

として分離する。

Ticket Priceを変更しても、
過去のReservationのPrice Snapshotを変更しない。

---

# Issued Ticket

Reservationが成立し、
実際のチケットとして発行された場合、
Issued Ticketとして扱う。

基本構造：

Reservation
  ↓
Issued Ticket

Issued Ticketは、
実際の来場受付に使用される。

Issued Ticketの詳細な管理は、
Ticket / QRTicket関連Domainで定義する。

---

# QR Ticket

QR Ticketは、
Issued Ticketを特定するための受付用Artifactである。

基本構造：

Reservation
  ↓
Issued Ticket
  ↓
QR Ticket

QRコードから、
対象となるReservationを特定できる。

QRコードそのものを、
Reservation情報の正本として扱わない。

QR Ticketの詳細な管理は、
QRTicket Domainで定義する。

---

# Status

ReservationStatusは予約状態を表す。

基本的な状態：

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

---

# Reserved

RESERVEDは、
予約が有効であり、
まだCheck Inされていない状態を表す。

RESERVEDのReservationは、
Check In前であれば変更・キャンセルできる。

---

# Checked In

CHECKED_INは、
予約者が受付を完了した状態を表す。

CHECKED_INとなったReservationは、
原則として変更できない。

Check InはReservation単位で行う。

---

# Cancelled

CANCELLEDは、
予約がキャンセルされた状態を表す。

Reservationそのものは削除しない。

キャンセルされたReservationは、
Check Inできない。

---

# No Show

NO_SHOWは、
予約が存在したものの、
公演終了時点で来場が確認できなかった状態を表す。

NO_SHOWの判定タイミングや自動更新については、
別Business Processで定義する。

NO_SHOWは、
予約そのものが存在しなかったことを意味しない。

---

# Status Transition

基本的な状態遷移：

RESERVED
  ↓
CHECKED_IN

または、

RESERVED
  ↓
CANCELLED

公演終了後に、

RESERVED
  ↓
NO_SHOW

へ変更できる。

CHECKED_INとなったReservationは、
通常の予約変更を行わない。

CANCELLEDとなったReservationは、
Check Inできない。

---

# Create

Reservation作成時に以下を確定する。

- Performance
- Booker
- Ticket
- Price Snapshot
- Guest Count
- Status
- Created By
- Created At
- Updated By
- Updated At

初期状態は、

Status
  = RESERVED

とする。

初期値として、

Updated By
  = Created By

Updated At
  = Created At

とする。

Reservation作成後、
ReservationCreatedを発行する。

---

# Update

Reservationは、
Check In前であれば変更できる。

変更可能な情報には、

- Booker
- Ticket
- Guest Count

などを含む。

ReservationのPerformanceは変更できない。

ReservationのPrice Snapshotは、
予約成立後に変更しない。

Ticketの現在Priceが変更されても、
ReservationのPrice Snapshotは変更しない。

変更はAggregate RootであるReservationを経由して行う。

変更完了時に、

Updated By
Updated At

を更新する。

変更完了後、

ReservationUpdated

を発行する。

---

# Update Restriction

CHECKED_INのReservationは変更できない。

以下の変更を禁止する。

- Performance
- Booker
- Ticket
- Price Snapshot
- Guest Count

CANCELLEDのReservationについても、
通常のReservation Updateを行わない。

Price Snapshotは、
Reservationの通常Updateによって変更しない。

---

# Cancellation

ReservationはCheck In前であればキャンセルできる。

キャンセルによって、

RESERVED
  ↓
CANCELLED

へ変更する。

Reservation自体は削除しない。

キャンセル時に、

Updated By
Updated At

を更新する。

その後、

ReservationCancelled

を発行する。

---

# Cancellation Restriction

CHECKED_INのReservationはキャンセルできない。

CANCELLEDのReservationは、
再度キャンセルできない。

CANCELLEDのReservationは、
Check Inできない。

---

# Check In

Check InはReservation単位で行う。

受付開始時に、
受付担当者は対象となるProductionおよびPerformanceを選択する。

基本Flow：

Production
  ↓
Performance
  ↓
Check In受付開始
  ↓
Reservation特定
  ↓
Check In

ReservationのPerformanceと
受付中Performanceが一致する場合のみ、
Check Inを実行できる。

---

# Check In Methods

Check Inは以下の方法で実行できる。

- Reservation一覧からの手動Check In
- QRコードによるCheck In

どちらの場合も、
同じReservation Check Inとして扱う。

Check In方法によって、
異なる予約Factを作成しない。

---

# Check In Target

Check Inの対象はReservationである。

Guest Countが複数名であっても、
Reservation全体をCheck Inする。

例えば、

Reservation
  Guest Count = 3

の場合、

3名分の予約を一つのReservationとしてCheck Inする。

Version 1.0では、
同行者を個別にCheck Inする機能は提供しない。

---

# Check In Validation

受付中Performanceと
ReservationのPerformanceが一致しない場合、
Check Inできない。

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

# Check In Completion

Check In完了時に、

ReservationStatus
  = CHECKED_IN

へ変更する。

同時に、

Updated By
  = Check In実行者

Updated At
  = Check In完了日時

へ更新する。

Check In完了後、
Reservationは通常の変更を受け付けない。

Check In完了後、

CheckInCompleted

を発行する。

---

# CheckInCompleted

CheckInCompletedは、
ReservationのCheck Inが完了したことを表すBusiness Eventである。

CheckInCompletedは、
観客が実際に来場したというFactを確定する。

基本Flow：

Reservation
  ↓
Check In
  ↓
CheckInCompleted

CheckInCompletedは、
Reservation Domainだけで消費するEventではない。

必要な関連Domainが、
CheckInCompletedを契機として処理を行う。

---

# Check In and History

Reservationは、
Historyを直接生成・管理しない。

CheckInCompletedを契機として、
History Domainが観劇履歴を生成する。

基本Flow：

Reservation
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

予約しただけでは、
観劇履歴を生成しない。

実際にCheck Inされた場合に、
観劇したというFactとしてHistoryを生成する。

---

# Check In and Accounting

CheckInCompletedを契機として、
チケット売上をAccounting Domainへ連携する。

基本Flow：

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

Ticket RevenueおよびJournal Entryは、
Accounting Domainで管理する。

Reservation Domain自身は、
Journal Entryを管理しない。

---

# Ticket Revenue

Ticket Revenueは、
CheckInCompletedによって確定した
チケット売上の会計連携用Factである。

Ticket.Priceは販売条件であり、
Ticket Revenueそのものではない。

基本構造：

Ticket.Price
  ↓
Reservation.Price Snapshot
  ↓
CheckInCompleted
  ↓
Ticket Revenue

具体的な勘定科目やDebit / Creditの処理は、
Accounting Domainで定義する。

---

# Audience History

Audience HistoryのSubjectは、
ReservationのBookerである。

BookerがStageArtユーザーとして登録されている場合、
そのPersonの観劇履歴として表示できる。

一般観客は、
StageArtのポータルを利用する必要はない。

ただし、
StageArtへユーザー登録してチケットを予約・購入したPersonは、
自身の観劇履歴を確認できる。

---

# General Audience

一般観客にStageArtのInternal Portalへの参加を要求しない。

観客は、
公開されたProductionページから
公演情報を確認し、
チケットを予約できる。

Reservationを行った観客が
StageArtユーザーとして登録されている場合は、
PersonとReservationを関連付けることで、
観劇履歴などを利用できる。

---

# Created By

CreatedByは、
Reservationを作成した主体を表す。

CreatedByには、
実際にReservation作成処理を実行した
認証済み利用者を記録する。

CreatedByはBookerとは異なる場合がある。

例えば、

Booker
  = 観客A

CreatedBy
  = 劇団スタッフB

という状態を許可する。

CreatedByは、
Reservation作成後に変更しない。

---

# Created At

CreatedAtは、
Reservationが作成された日時を表す。

CreatedAtはReservation作成時に設定する。

CreatedAtは変更しない。

---

# Updated By

UpdatedByは、
Reservationを最後に変更した主体を表す。

Reservationを変更した認証済み利用者を設定する。

例えば、

CreatedBy
  = 劇団スタッフB

UpdatedBy
  = 劇団スタッフC

という状態を許可する。

Check Inを実行した利用者も、
Check In完了時にUpdatedByとして記録する。

---

# Updated At

UpdatedAtは、
Reservationが最後に変更された日時を表す。

Reservationの変更時に更新する。

Check In完了時にも更新する。

---

# Reservation Number

ReservationNumberは、
観客および受付担当者が予約を識別するための番号である。

Reservation生成時に採番する。

ReservationNumberは、
ReservationIdとは別の識別子として扱う。

---

# Memo

Reservationには、
必要に応じて内部Memoを保持できる。

Memoは一般観客へ公開しない。

Memoの利用範囲や権限については、
Reservationを扱うOrganizationのRoleおよび
Production権限に従う。

---

# Business Rules

- ReservationはAggregate Rootである。
- Reservationは特定のPerformanceに対する予約を表す。
- Reservationは必ず一つのPerformanceに所属する。
- Reservation作成後にPerformanceを変更しない。
- 別Performanceへ変更する場合は既存Reservationをキャンセルし、新しいReservationを作成する。
- Bookerは予約者を表す。
- BookerはPersonを参照する。
- BookerとCreatedByは異なることができる。
- GuestCountは予約人数を表す。
- Companionを独立Domainとして管理しない。
- 同行者情報をReservation内で管理しない。
- TicketはProductionに所属する販売条件である。
- ReservationはTicketを参照する。
- Ticketの正本はProduction側にある。
- Reservationは予約成立時点のPrice Snapshotを保持する。
- Ticketの現在Priceを変更しても過去のPrice Snapshotを変更しない。
- ReservationはIssued Ticketと関連付けることができる。
- QR TicketはIssued Ticketに関連する受付用Artifactである。
- QRコードをReservation情報の正本として扱わない。
- ReservationStatusはRESERVED、CHECKED_IN、CANCELLED、NO_SHOWを基本とする。
- CHECKED_INのReservationは通常変更できない。
- CANCELLEDのReservationは物理削除しない。
- CANCELLEDのReservationはCheck Inできない。
- NO_SHOWは公演終了時点で来場が確認できなかった状態を表す。
- Check InはReservation単位で行う。
- GuestCountが複数でもReservation全体をCheck Inする。
- Version 1.0では同行者を個別にCheck Inしない。
- Check In時には受付中PerformanceとReservationのPerformanceが一致していることを確認する。
- Manual Check InとQR Check Inは同じReservation Check Inとして扱う。
- Check In完了時にReservationStatusをCHECKED_INへ変更する。
- Check In完了時にUpdatedByとUpdatedAtを更新する。
- Check In完了時にCheckInCompletedを発行する。
- ReservationはHistoryを直接管理しない。
- CheckInCompletedを契機としてHistory DomainがAudience Historyを生成する。
- Audience HistoryのSubjectはReservation.Bookerである。
- CheckInCompletedを契機としてTicket RevenueをAccounting Domainへ連携する。
- Reservation DomainはJournal Entryを管理しない。
- CreatedByはReservation作成後に変更しない。
- CreatedAtは変更しない。
- ReservationNumberはReservationIdとは別の識別子として管理する。
- Memoは一般観客へ公開しない。

---

# Domain Events

Reservationに関する主なDomain Event：

- ReservationCreated
- ReservationUpdated
- ReservationCancelled
- CheckInCompleted

CheckInCompletedは、
ReservationのCheck In完了を表すBusiness Eventである。

CheckInCompletedを契機として、

- History Domain
- Accounting Domain

などの関連Domainが必要な処理を行う。

Reservation Domain自身が、
HistoryやJournal Entryを直接生成・更新しない。

---

# Design Decisions

Reservationは、
特定のPerformanceに対する観客の予約Factを管理する。

ReservationはAggregate Rootとして、
予約に関するBusiness Ruleを管理する。

ReservationはPerformanceに所属し、
作成後にPerformanceを変更しない。

TicketはProduction単位で管理し、
ReservationはそのTicketを参照する。

Reservationは、
予約成立時点のTicket PriceをPrice Snapshotとして保持する。

Ticketの現在価格を変更しても、
過去のReservationの価格を変更しない。

Reservationが成立した後、
Issued Ticketを発行できる。

QR Ticketは、
Issued Ticketを受付で特定するためのArtifactとして扱う。

Check InはReservation単位で行う。

Check Inが完了すると、
CheckInCompletedを発行する。

CheckInCompletedを契機として、

- Audience History
- Ticket Revenue

をそれぞれのDomainへ連携する。

ReservationはHistoryを直接管理しない。

ReservationはAccountingのJournal Entryを直接管理しない。

CompanionはVersion 1.0では独立管理しない。

一般観客はStageArtのInternal Portalへの参加を要求しない。

StageArtユーザーとして登録されたBookerは、
自身の観劇履歴を参照できる。

---

# Future

将来的に以下へ対応する。

- 同行者管理
- 同行者単位のCheck In
- PerformanceごとのTicket Availability
- Reservationごとの複数Ticket
- Ticket数量管理
- 座席指定
- Reservation Seat
- 払い戻し
- 振替公演
- 外部チケット販売サービス連携

ただし、
将来機能を追加する場合も、
Reservationの基本責務を
「特定Performanceへの予約Factの管理」から逸脱させない。

---

# Design Principles

- ReservationはAggregate Rootである。
- ReservationはPerformanceへの予約Factを表す。
- Reservationは一つのPerformanceに所属する。
- Reservation作成後にPerformanceを変更しない。
- BookerとCreatedByを分離する。
- GuestCountによって予約人数を表現する。
- CompanionをVersion 1.0では独立管理しない。
- TicketはProduction単位で管理する。
- ReservationはTicketを参照する。
- ReservationはPrice Snapshotを保持する。
- Ticket PriceとReservation Price Snapshotを分離する。
- Issued TicketはReservationに基づいて発行する。
- QR TicketはIssued Ticketの受付用Artifactである。
- QR Ticketを予約情報の正本としない。
- Check InはReservation単位で行う。
- Check In時にはPerformanceを検証する。
- Check In完了時にCheckInCompletedを発行する。
- CheckInCompletedからAudience Historyを生成する。
- CheckInCompletedからTicket RevenueをAccountingへ連携する。
- Reservation DomainはHistoryを直接管理しない。
- Reservation DomainはJournal Entryを管理しない。
- CANCELLEDのReservationを物理削除しない。
- CHECKED_INのReservationを通常変更しない。
- 一般観客にInternal Portalへの参加を要求しない。
- StageArtユーザーとして登録されたBookerは自身の観劇履歴を参照できる。
- Blueprintを唯一の設計基準とする。