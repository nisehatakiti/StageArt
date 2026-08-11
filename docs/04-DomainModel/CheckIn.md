# StageArt Blueprint

# Domain Model : Check In

Version : 2.0

---

# Purpose

Check Inは、
公演当日に観客が来場し、
受付を完了したというFactを管理するDomainである。

Check Inは、
Reservationに対する来場受付を表す。

また、Check Inの完了を、
チケット売上を会計へ認識するための
Business Eventとして利用する。

---

# Concept

基本構造：

Performance
  ↓
Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ├──→ History
  │
  └──→ Ticket Revenue Recognition
              ↓
         Journal Entry

Check Inは、
予約そのものではない。

Reservationは、

「公演を予約した」

というFactを表す。

Check Inは、

「予約した観客が公演当日に受付を完了した」

というFactを表す。

Check InCompletedは、
受付完了というFactが成立したことを示す
Domain Eventである。

---

# Relationship

Check Inは一つのReservationに関連付けられる。

Reservation
  ↓
Check In

Check InはReservationを正本とし、
Reservationを削除・置換しない。

---

# Performance

Check Inは、
特定のPerformanceに対して実行される。

Check In時には、

受付中Performance
  =
Reservation.Performance

であることを検証する。

異なるPerformanceのReservationを
Check Inしてはならない。

---

# Check In Methods

Check Inは、
複数の受付方法に対応する。

基本的な方法：

- QR Code
- Reservation Number
- Booker Name
- Manual Selection

どの方法で受付しても、
生成されるCheck In Factは同じである。

---

# QR Check In

QR Ticketを読み取ることで、
Reservationを特定する。

基本Flow：

QR Scan
  ↓
QR Ticket Verification
  ↓
Reservation取得
  ↓
Performance Validation
  ↓
Check In
  ↓
Reservation Status
  = CHECKED_IN
  ↓
CheckInCompleted

---

# Reservation Number Check In

QR Ticketを利用できない場合、
Reservation NumberからReservationを検索して
Check Inできる。

基本Flow：

Reservation Number
  ↓
Reservation検索
  ↓
Performance Validation
  ↓
Check In
  ↓
CheckInCompleted

---

# Booker Name Check In

Booker Nameによって
Reservationを検索できる。

複数のReservationが該当する場合は、
受付担当者が対象Reservationを特定する。

氏名検索だけで
自動的にCheck Inを確定しない。

---

# Manual Selection

受付担当者は、
対象PerformanceのReservation一覧から
Reservationを選択してCheck Inできる。

---

# Check In Validation

Check Inを実行する前に、
以下を検証する。

- Reservationが存在する
- ReservationがCANCELLEDではない
- 対象Performanceが一致する
- ReservationがまだCheck Inされていない

すべての条件を満たした場合、
Check Inを実行できる。

---

# Duplicate Check In

同一Reservationに対して、
複数回Check Inを実行してはならない。

すでにCheck In済みの場合は、

「受付済み」

として扱う。

二重受付として
新しいCheck In Factを生成しない。

また、
同一Check InCompletedから
チケット売上の会計仕訳を
二重生成してはならない。

---

# Check In Status

Check Inには、
受付結果を表す状態を持たせることができる。

基本的には、

- COMPLETED
- REVERSED

を利用する。

---

# Completed

COMPLETEDは、
受付が正常に完了した状態。

Check In完了時に設定する。

COMPLETEDとなった時点で、
CheckInCompletedを発行する。

---

# Reversed

REVERSEDは、
誤受付などにより、
管理権限を持つPersonが
受付を取り消した状態。

物理削除は行わない。

---

# Check In Reversal

誤ったReservationを受付してしまった場合、
管理権限を持つPersonは
Check Inを取り消すことができる。

基本Flow：

COMPLETED
  ↓
REVERSED

Check Inを取り消しても、
過去に発生したCheck In Factを
物理削除しない。

Reservationの状態については、
Reservation Domainのルールに従って更新する。

会計連携済みの場合は、
Accounting Domain側の
取消・修正処理を実行する。

Check In Domain自身が
Journal Entryを直接変更・削除しない。

---

# Check In Operator

Check Inには、
受付を実行したPersonを記録する。

基本情報：

- Checked In By
- Checked In At

Checked In Byは、
観客本人ではなく、
受付業務を実行したPersonを表す。

---

# Reception Staff

受付を行うPersonは、
適切な受付権限を持つ必要がある。

Organization Administratorは、
自身のOrganizationについて
全権限を持つ。

その他のPersonについては、
RoleおよびProduction単位の権限に従う。

---

# Reception Scope

受付担当者は、
担当するProduction / Performanceの
Reservationを対象としてCheck Inする。

他のOrganizationや
権限のないProductionのReservationを
Check Inしてはならない。

---

# Guest Count

Check Inは、
Reservation単位で行う。

ReservationのGuest Countが複数であっても、
Version 1.0では
同行者を個別にCheck Inしない。

例えば、

Reservation
  Guest Count = 3

の場合、

Check In
  = 1件

として扱う。

---

# Companion

Companionは、
Check In Domainでは管理しない。

同行者を個別Personとして
Check In対象にしない。

複数名の来場については、
ReservationのGuest Countを利用する。

---

# Check In Time

Check Inには、
実際に受付を完了した日時を記録する。

Checked In Atは、
受付Factの発生日時である。

Reservation Created Atとは異なる。

---

# Check In Date

Check In Dateは、
受付を実施した日時から算出できる。

Check In Dateを
独立した正本として重複管理しない。

---

# Check In and Reservation

Reservationは予約Factである。

Check Inは来場受付Factである。

基本的な関係：

Reservation
  Status = RESERVED

↓

Check In

↓

Reservation
  Status = CHECKED_IN

Check Inが完了した場合、
Reservation StatusをCHECKED_INへ変更する。

---

# Check In and QR Ticket

QR Ticketは、
Check Inを行うための識別・認証手段である。

QR Ticket自体がCheck In Factではない。

基本構造：

QR Ticket
  ↓
Reservation
  ↓
Check In

---

# Check In and History

Check In完了時に、
CheckInCompletedを発行する。

History Domainは、
このEventを利用して
観劇履歴を生成できる。

基本Flow：

Check In
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

---

# Check In and Accounting

CheckInCompletedは、
チケット売上を会計へ認識するための
Domain Eventとして利用する。

基本Flow：

Check In
  ↓
CheckInCompleted
  ↓
Ticket Revenue Recognition
  ↓
Journal Entry
  ↓
Journal Entry Line

予約時点では、
チケット売上を会計へ計上しない。

Check Inが完了した時点で、
対象Ticketの売上を
会計上の実績として認識する。

---

# Ticket Revenue Recognition

Ticket Revenue Recognitionは、
CheckInCompletedを契機として生成される。

Revenue Recognitionは、
Check In Domain自身がJournal Entryを
直接生成する責務を持たない。

Check In Domainは、

CheckInCompleted

というEventを発行する。

Accounting Domainは、
このEventを購読し、
対象ReservationのTicket情報をもとに
チケット売上を会計へ連携する。

---

# Revenue Amount

Ticket Revenue Recognitionに利用する金額は、
Reservationの予約時点の取引価格を利用する。

Ticketの現在価格を参照して、
過去のReservationの売上金額を
再計算してはならない。

例えば、

Ticket
  一般 3,000円

予約後に、

Ticket
  一般 3,500円

へ変更された場合でも、

Check Inされた既存Reservationの売上
  = 3,000円

とする。

---

# Revenue and Guest Count

Guest Countが複数の場合、
Reservationに記録された
取引金額を会計連携の基礎とする。

例えば、

Guest Count
  = 3

の場合でも、
Reservationに記録された
実際の取引金額を利用する。

単純に、

Ticket Price × Guest Count

だけで金額を再計算しない。

---

# Journal Entry

Accounting Domainは、
CheckInCompletedを契機として
Journal Entryを生成できる。

例えば、

Ticket Revenue
  3,000円

の場合、

借方：
  現金等       3,000円

貸方：
  チケット売上 3,000円

という仕訳を生成できる。

具体的な勘定科目、
決済手段、
税区分などはAccounting Domainで定義する。

Check In Domainは
勘定科目を直接管理しない。

---

# Journal Entry Line

Journal Entryは、
費目ごとにJournal Entry Lineを持つ。

例えば、

Journal Entry
  ↓
Journal Entry Line
  ├── 現金等       3,000円
  └── チケット売上 3,000円

貸借は、
Journal Entry Lineの
Debit / Credit Flagによって表現する。

Check In Domainは、
Journal Entry Lineの構造を
直接管理しない。

---

# Accounting Link

Check Inと会計連携の関係を追跡できるよう、
Accounting Domain側で
Check InまたはCheckInCompletedとの
関連情報を保持する。

これにより、

「このチケット売上の仕訳は、
どのCheck Inから生成されたか」

を追跡できる。

---

# Duplicate Accounting

同一CheckInCompletedを
複数回処理しても、
同一のチケット売上を
二重計上してはならない。

Accounting Domainは、
CheckInCompletedの識別情報などを利用して
Idempotentに処理する。

基本構造：

CheckInCompleted
  ↓
Accounting Processing
  ↓
Journal Entry

すでに処理済みの場合、
新しいJournal Entryを生成しない。

---

# Accounting Reversal

Check InがREVERSEDとなった場合、
すでに売上計上されている場合は、
Accounting Domainへ取消・修正を依頼する。

基本Flow：

Check In
  COMPLETED

↓

CheckInCompleted

↓

Journal Entry
  売上計上

↓

Check In
  REVERSED

↓

Accounting Reversal

Accounting側の取消方法は、
Journal Entry Domainのルールに従う。

Check In Domainは、
既存Journal Entryを直接変更しない。

---

# Accounting Boundary

Check In DomainとAccounting Domainの責務を分離する。

Check In Domain：

- 来場受付
- Check In状態
- Check In Operator
- Check In日時
- CheckInCompleted Event

Accounting Domain：

- 売上認識
- 勘定科目
- Journal Entry
- Journal Entry Line
- Debit / Credit
- 会計取消
- 会計集計

---

# Check In and Budget

Check Inによって生成された
チケット売上の実績は、
将来的にProductionの予実管理へ
連携できる。

基本構造：

Budget
  ↓
Production
  ↓
Check In
  ↓
Ticket Revenue
  ↓
Journal Entry
  ↓
Actual

BudgetとActualの比較は、
Accounting / Budget Domainで管理する。

Check In Domainは、
予算情報を管理しない。

---

# Audience History

観劇履歴の対象者は、
ReservationのBookerを基本とする。

BookerがStageArtユーザーとして登録されている場合、
そのPersonの観劇履歴として表示できる。

一般観客がStageArtの
Internal Portalを利用する必要はない。

ただし、
ユーザー登録してチケットを予約したPersonは、
自身の観劇履歴を確認できる。

---

# Check In and Attendance

Rehearsal Attendanceとは
別のDomainである。

Rehearsal Attendance：

「稽古に参加する予定」

Check In：

「公演当日に来場受付を完了した」

両者を混同しない。

---

# Check In and Ticket

Ticketは、
Productionにおける販売条件である。

Check Inは、
Reservationに対する来場受付である。

基本構造：

Production
  ↓
Ticket
  ↓
Reservation
  ↓
Check In
  ↓
Ticket Revenue Recognition

Check InはTicketそのものを
状態変更するものではない。

---

# Check In Result

受付完了時に、
受付担当者へ結果を表示する。

例えば、

- 受付完了
- 受付済み
- キャンセル済み
- 別公演回の予約
- 予約が存在しない

など。

表示内容はUI / Application Layerで定義する。

---

# Error Handling

Check Inできない場合は、
Reservationを変更しない。

例えば、

CANCELLED
  ↓
Check In Attempt
  ↓
Rejected

とする。

Rejectedされた処理は、
正常なCheck In Factとして記録しない。

必要に応じて、
Security / Audit Logで追跡する。

---

# Mobile

Check Inは、
公演当日の受付業務で利用されるため、
Mobile Firstで設計する。

特に、

- QR Scan
- Reservation Search
- Check In
- Check In Result

をスマートフォンで
簡単に操作できるようにする。

---

# QR Scanner

QR Scanは、
スマートフォンまたはタブレットなどの
カメラから利用できる。

QR Scanner自体は、
Check In Domainではなく
UI / Infrastructureの責務とする。

---

# Search

受付担当者は、
QR以外にもReservationを検索できる。

基本的な検索条件：

- Reservation Number
- Booker Name

将来的には、

- Email
- Ticket Type
- その他予約情報

などへ拡張できる。

---

# Reception Screen

受付画面では、
対象Performanceを明確に表示する。

例えば、

本日 14:00公演

を選択した状態で受付を行う。

これにより、
別PerformanceのReservationを
誤って受付することを防止する。

---

# Audit

Check Inには、
受付操作を追跡できるよう監査情報を保持する。

基本情報：

- Checked In By
- Checked In At
- Created At
- Updated At

また、
会計連携についてはAccounting Domain側で
Check Inとの関連を追跡する。

---

# Privacy

受付画面には、
必要なReservation情報だけを表示する。

観客の個人情報を
権限のないPersonへ公開してはならない。

特に、

- Booker Name
- Contact Information
- Reservation Memo

などは、
受付業務に必要な範囲でのみ表示する。

---

# Business Rules

- Check Inは来場受付というFactである。
- Reservationは予約Factである。
- ReservationとCheck Inを分離する。
- Check InはReservationに関連付けられる。
- Check In時にPerformanceを検証する。
- 異なるPerformanceのReservationをCheck Inできない。
- QR CodeによるCheck Inを許可する。
- Reservation NumberによるCheck Inを許可する。
- Booker Nameによる検索・Check Inを許可する。
- Reservation一覧からのManual Check Inを許可する。
- QR Check InとManual Check Inは同じCheck In Factとして扱う。
- CANCELLEDのReservationはCheck Inできない。
- CHECKED_IN済みのReservationを二重Check Inしない。
- Check In完了時にReservation StatusをCHECKED_INへ変更する。
- Check In完了時にCheckInCompletedを発行する。
- CheckInCompletedをHistory生成の契機として利用できる。
- CheckInCompletedをTicket Revenue Recognitionの契機として利用する。
- 予約時点ではチケット売上を会計計上しない。
- Check In完了時点でチケット売上を会計上の実績として認識する。
- 売上金額はReservationの予約時点の取引価格を利用する。
- Ticketの現在価格を過去Reservationの売上計算に利用しない。
- Guest Countから単純計算して売上金額を再構成しない。
- Accounting DomainがCheckInCompletedを受け取りJournal Entryを生成する。
- Check In Domain自身がJournal Entryを直接生成しない。
- Check In Domain自身が勘定科目を管理しない。
- Journal Entry LineはAccounting Domainで管理する。
- Journal Entry Lineは費目ごとに分ける。
- Journal Entry Lineの貸借はDebit / Credit Flagで管理する。
- 同一CheckInCompletedから売上仕訳を二重生成しない。
- Accounting処理はIdempotentに実行する。
- Check Inを取り消した場合、会計計上済みであればAccounting Domainへ取消・修正を連携する。
- Check In DomainはJournal Entryを直接変更・削除しない。
- Check Inによる売上実績は将来的にProductionの予実管理へ連携できる。
- Check InはRehearsal Attendanceとは別のDomainである。
- Check InはTicketの販売状態を直接変更しない。
- Guest Countが複数でもVersion 1.0では同行者を個別管理しない。
- CompanionをCheck In Domainで管理しない。
- Check InはMobile Firstで利用できるようにする。
- Check Inには適切な権限が必要である。
- 個人情報の表示は権限と必要性に基づいて制限する。

---

# Domain Events

Check Inに関する主なDomain Event：

- CheckInCompleted
- CheckInReversed

CheckInCompletedは、
以下のDomain処理の契機として利用できる。

- History
- Ticket Revenue Recognition
- Accounting Integration

CheckInReversedは、
会計計上済みの場合に
Accounting Domainへ取消・修正処理を
依頼する契機として利用できる。

---

# Design Decisions

Check Inは、
公演当日の来場受付というFactを表す。

ReservationとCheck Inを分離することで、

「予約した」

と

「実際に来場して受付した」

を明確に区別する。

QR TicketはCheck Inの手段であり、
Check Inそのものではない。

QR Check In、
Reservation Number Check In、
Booker Nameによる検索、
Manual Check Inのいずれも、
最終的には同じReservationに対する
Check Inとして扱う。

Check InはReservation単位で行う。

Version 1.0では、
同行者を個別Personとして管理しない。

---

# Accounting Design

StageArtでは、
チケット売上の会計認識を
Reservation作成時ではなく、
Check In完了時に行う。

これにより、

「予約された売上」

と

「実際に来場した公演実績」

を分離する。

CheckInCompletedをAccounting Domainへ連携し、
Accounting DomainがTicket Revenue Recognitionを行う。

その結果としてJournal Entryを生成する。

基本構造：

CheckInCompleted
  ↓
Ticket Revenue Recognition
  ↓
Journal Entry
  ↓
Journal Entry Line

この仕組みにより、
公演終了後に、

Budget
  ↓
Actual
  ↓
Variance

という予実管理へ
チケット売上を連携できる。

---

# Future

将来的に以下へ対応できる。

- 入場時刻の詳細分析
- 再入場
- 途中退場
- 同行者単位の受付
- 座席情報との連携
- 入場ゲート管理
- 複数受付端末
- オフライン受付
- 受付端末同期
- 入場者数リアルタイム集計
- QR不正利用検知
- 受付ログ分析
- Ticket Revenueの詳細分析
- 決済手段別売上
- 税区分別売上
- 公演別売上集計
- Production別予実分析

ただし、
将来機能を追加する場合も、
Reservation、
Check In、
Accountingの責務を分離する。

---

# Design Principles

- Check Inは来場受付Factである。
- Reservationは予約Factである。
- ReservationとCheck Inを分離する。
- QR TicketはCheck Inの手段である。
- QR TicketをCheck In Factとして扱わない。
- QR Check InとManual Check Inを同じFactとして扱う。
- Check In時にPerformanceを検証する。
- CANCELLEDのReservationはCheck Inできない。
- 二重Check Inを発生させない。
- Check InはReservation単位で行う。
- Guest CountとCheck Inを同行者単位に分解しない。
- CompanionをVersion 1.0では管理しない。
- CheckInCompletedをHistory生成の契機として利用する。
- CheckInCompletedをTicket Revenue Recognitionの契機として利用する。
- 予約時点ではチケット売上を会計計上しない。
- Check In完了時点でチケット売上を会計上の実績として認識する。
- Check In DomainとAccounting Domainの責務を分離する。
- Check In Domainが勘定科目やJournal Entryを直接管理しない。
- 同一CheckInCompletedから二重仕訳を生成しない。
- 会計処理はIdempotentにする。
- 会計取消はAccounting Domainで処理する。
- Ticket Revenueを将来の公演予実管理へ連携できる構造とする。
- 一般観客にInternal Portalへの参加を要求しない。
- Check InはMobile Firstで設計する。
- Check Inには適切な権限を要求する。
- 個人情報を必要最小限で扱う。
- Blueprintを唯一の設計基準とする。
