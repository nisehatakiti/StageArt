# StageArt Blueprint

# Domain Consistency Policy : Reservation

Version : 1.3

---

# Purpose

Reservation Domainについて、Performance / Ticket / Capacity / Price Snapshot / Check In / Accounting / Reservation Notificationに加え、予約者自身による予約変更・キャンセル、および担当者による代理予約・事前集金の業務フローを定義する。

---

# Canonical Position

Reservationは、特定のPerformanceに対する観客の予約Factを表す。

```text
Production
    ↓
Performance
    ↓
Reservation
    ↓
Ticket
```

Reservationは必ず一つのPerformanceに所属する。

---

# Reservation Number

Reservationが正常に成立した時点で、StageArtは予約番号を自動生成する。

予約番号はReservationを識別するための公開可能な識別子とし、推測されにくい値を使用する。

予約番号は予約確認メールに記載する。

予約番号そのものを認証秘密として扱うのではなく、予約者メールアドレスとの組み合わせによって予約変更・キャンセル対象を照合する。

---

# Reservation Confirmation

Reservationが正常に成立した場合、予約者のメールアドレスへ予約受付完了通知を送信する。

通知には少なくとも以下を含める。

- 公演名
- 公演日時
- 会場
- 券種
- 枚数 / GuestCount
- 予約者名
- 自動生成された予約番号
- 予約変更・キャンセルフォームへの案内
- Check Inに使用するQR Code
- 受付時の案内

メール送信の失敗によって、成立済みReservationを取り消してはならない。

送信失敗時は再送可能なNotification処理として扱う。

---

# QR Code

Reservation成立時にCheck In用QR Codeを発行する。

QR CodeはReservationを安全に識別・検証できる情報を表現する。

QR Codeの実体画像をReservationへ永続保存する必要はなく、必要に応じて表示・再生成できる設計を基本とする。

具体的な署名方式・Token形式・有効性検証方式はSecurity / Application Architectureで定義する。

---

# Reservation Change / Cancellation Access

予約者はStageArtアカウントを作成・ログインすることなく、専用の「予約変更・キャンセルフォーム」から自身のReservationを管理できるものとする。

対象Reservationの照合には、以下の2項目を使用する。

- 予約時に登録したメールアドレス
- 自動生成された予約番号

両方が一致した場合のみ対象Reservationを表示し、変更・キャンセル操作を許可する。

基本フロー：

```text
予約変更・キャンセルフォーム
        ↓
メールアドレス入力
        ↓
予約番号入力
        ↓
Reservation照合
        ↓
予約内容表示
        ├── 予約変更
        └── 予約キャンセル
```

Reservation検索時には、メールアドレスまたは予約番号だけで予約情報を開示してはならない。

試行回数制限、レート制限等のSecurity対策を適用する。

---

# Changeable Reservation Information

Check In前のRESERVED Reservationについて、専用フォームから以下を変更可能とする。

- 予約者名
- 予約者メールアドレス
- Ticket / 券種
- GuestCount / 枚数
- その他、Reservation Domainで変更可能と定義した予約者情報

変更時には、変更後の内容が現在のPerformance / Ticket / Capacityルールを満たすことを再検証する。

Price Snapshotは、成立済みReservationの過去取引Factとして不用意に書き換えない。

Ticket変更や枚数変更によって金額が変わる場合の新しいPrice Snapshotの扱いは、Reservation変更時のAccounting / Payment Policyとして別途定義する。

---

# Performance Change Prohibition

予約者自身によるReservation変更で、予約対象のPerformanceを変更することはできない。

別の公演回へ変更したい場合は、現在の予約をキャンセルしたうえで、新しい公演回へ改めて予約を取り直す必要があることを予約変更フォームおよび予約確認メール等で明示する。

既存Reservationを別Performanceへ直接移動させてはならない。

理由：

- Performanceごとの定員管理
- Reservation Factの履歴保持
- Ticket / Price Snapshot
- Check In
- Accounting

の整合性を維持するため。

---

# Cancellation Flow

```text
Reservation Change / Cancellation Form
        ↓
Email + Reservation Number
        ↓
Reservation Verification
        ↓
Cancellation Confirmation
        ↓
Reservation = CANCELLED
        ↓
Capacity Countから除外
        ↓
キャンセル完了メール
```

Reservation自体は物理削除しない。

CANCELLEDとなったReservationは履歴として保持する。

---

# Reservation Status

基本状態：

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

RESERVEDは有効な予約状態。

CHECKED_INは来場受付完了。

CANCELLEDはキャンセルされた予約Factを保持する状態。

NO_SHOWは予約が存在したが来場が確認されなかった状態。

Check In後のReservationは、予約者向け変更・キャンセルフォームから変更・キャンセルできない。

---

# Capacity

Productionには標準予約定員を設定でき、Performance作成時に継承する。

Performanceは継承した定員を個別に変更できる。

Reservation受付時はPerformanceの定員を使用する。

```text
Production
    ↓
Standard Reservation Capacity
    ↓
Performance Reservation Capacity
    ↓
Reservation
```

CANCELLED ReservationはCapacity Countから除外する。

CHECKED_IN Reservationは公演終了までCapacity Countに含める。

---

# Capacity Validation

Reservation作成・GuestCount変更・Ticket変更時には、対象Performanceの定員と現在の予約使用数を検証する。

同時予約によって定員を超過しないよう、Reservation成立処理とCapacity Validationを一貫したTransaction / Concurrency Control単位で処理する。

具体的なDatabase Lock等はInfrastructure / Application Architectureで定義する。

---

# Price Snapshot

Reservation成立時には、その時点のTicket PriceをPrice Snapshotとして保持する。

Ticketの現在Priceが後から変更されても、既存ReservationのPrice Snapshotを単純に再計算してはならない。

Reservation変更で金額が変動する場合は、差額・再決済・返金等の会計処理をAccounting / Payment Policyで定義する。

---

# Booker and CreatedBy

BookerはReservation上の予約者・予約を取りまとめた担当者を表す。

BookerとCreatedByは別概念とする。

通常の予約ではBookerは観客本人となる。

劇団スタッフが観客から予約を受けて代理入力する場合は、スタッフをBookerとして登録できる。CreatedByもそのスタッフとなる。

この場合、スタッフは当該Reservationに係るTicket代金を観客から預かっている担当者として扱う。

代理予約のためだけに別のReservation Entityを作成しない。

---

# Staff-Held Ticket Payment

StageArt V1では、Ticketの決済方法は現金のみ、原則として劇場払いとする。

ただし、劇団スタッフが代理予約時に観客からTicket代金を事前に受領している場合、そのReservationを「支払済（担当者預り）」として登録できる。これは観客からスタッフへの支払いが済んでいることを示すが、この段階では劇団側はまだ現金を受領していないため、標準運用上のTicket売上・入金は計上しない。

基本Flow：

```text
観客
  ↓ 現金支払い（劇団側の売上・入金は未計上）
劇団スタッフ
  ↓ 代理予約登録
Reservation
  └─ 支払済（担当者預り）
        ↓
受付担当者がスタッフから現金を受領
        ↓
Reservationの支払済を確定
        ↓
Payment Fact
        ↓
入金とTicket Revenueを同時に計上
        ↓
Check In（来場Fact。売上・入金は再計上しない）
```

受付担当者がスタッフから現金を実際に受け取り、Reservationを支払済みとして確定した時点で、劇団側の受領が成立する。この受領FactをPayment Factとして記録し、入金とTicket Revenueを同時に計上する。例えば3,000円の場合、決済用Accountに応じた現金等を借方、チケット売上を貸方として記録する。

受付担当者が現金を受け取る前にCheck Inされた場合は、来場Factのみを記録し、Payment Factが記録されるまで売上・入金を計上しない。逆に受領と売上計上がCheck Inより先に完了している場合、後続のCheck Inで同じTicketの売上・入金を再計上しない。

会計期間末には、決算未確定Productionに関連する既存の売上Journal Entry残高を仮売上へ振り替える。Production決算確定時には、その仮売上を正式な売上へ振り替える。これらは既存売上の振替処理であり、新たな売上を二重計上する処理ではない。

会計FactはJournal Entryを正本とする。Reservationは支払状態と預り担当者など、業務上必要な情報を保持する。
 
---

# Staff Settlement and Netting

劇団スタッフが保有するTicket代金の未収金は、同じスタッフに対する劇団側の未払金と最終精算時に相殺できるものとする。

対象となる未払金には、例えば以下を含む。

- Ticket Back
- 承認済み立替経費
- その他、スタッフに対して確定した未払金

例：

```text
スタッフA

劇団側未払金
  Ticket Back       20,000
  立替経費           8,000
  -----------------------
  未払合計           28,000

スタッフ側未収金
  預かりTicket代     15,000

最終精算
  劇団 → スタッフ      13,000
```

逆にスタッフ側未収金が未払金を上回る場合は、差額をスタッフから劇団へ精算する。

相殺後の残額のみを実際の現金・預金の精算対象とする。

相殺処理は、未収金・未払金の残高を直接書き換えるのではなく、Accounting DomainのJournal EntryによるSettlement / Nettingとして記録する。

スタッフ別に未収金・未払金の残高を確認でき、最終精算額を明確にできることを基本とする。

---

# Guest Count

GuestCountはReservationで確保する人数を表す。

初期仕様では同行者を独立Person / Companionとして管理しない。

---

# Check In

Check InはReservation単位で行う。

QR Codeを読み取った場合、Reservationを安全に検証して対象Reservationを特定し、通常のCheck In処理へ進む。

「支払済（担当者預り）」のReservationは、受付時に支払済として扱い、追加徴収なしでCheck Inできる。

Check In完了後はCHECKED_INとなり、通常のReservation変更を行わない。

---

# Accounting

Reservation DomainはJournal Entryを管理しない。

標準運用ではReservation成立だけで売上・入金・未収金を計上しない。劇団側が実際に代金を受領した時点でPayment Factを記録し、入金とTicket Revenueを同時に計上する。当日支払いでは受付担当者が観客から受領した時点、スタッフ預りでは受付担当者がスタッフから現金を受け取りReservationを支払済みにした時点が該当する。

CheckInCompletedは来場Factを表す。Payment Factに基づく売上・入金が既に計上されている場合、CheckInCompletedによって重複計上してはならない。CheckInCompletedを仮売上振替の契機にも使用しない。

会計期間末に決算未確定Productionの既存売上残高を仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。

```text
Reservation成立
    ↓
Price Snapshot（予約金額の確定。仕訳なし）
    ↓
劇団側が実際に代金を受領
    ↓
Payment Fact
    ↓
入金とTicket Revenueを同時計上
    ↓
Check In / CheckInCompleted（来場Fact。再計上なし）
    ↓
会計期間末に既存売上を仮売上へ振替
    ↓
Production決算確定時に正式売上へ振替
```

Accounting上の正本はJournal Entry。

Reservationが会計Factを二重管理しない。

スタッフ預りTicketについて、Reservationは支払済状態と預り担当者を業務上参照できる情報として保持する。会計上の入金・売上および必要な担当者別精算残高は、Journal Entryを正本として管理する。

---

# History

予約しただけでは観劇履歴を生成しない。

CheckInCompletedを契機として必要なHistory Domainが観劇履歴を生成する。

---

# Notification

Reservation成立、Reservation変更、Reservationキャンセルについて、予約者へのメール通知を行う。

メール送信はReservation FactのTransactionと疎結合にし、一時的な送信障害によって予約Factをロールバックしない。

通知失敗は再送可能な状態として管理する。

---

# Deletion

Reservationは物理削除を基本としない。

キャンセルはCANCELLEDへの状態変更で表現する。

過去Reservationを削除してCapacity、Accounting、Historyの整合性を壊してはならない。

---

# Canonical Relationship Summary

```text
Organization
    ↓
Project
    ↓
Production
    ├── Venue
    ├── Ticket
    └── Performance
          ↓
       Reservation
          ├── Reservation Number
          ├── Booker / Email
          ├── CreatedBy
          ├── Ticket Reference
          ├── Price Snapshot
          ├── Guest Count
          ├── Payment Status
          ├── QR Code
          └── Notification
                ├── Confirmation
                ├── Change
                └── Cancellation
```

予約者はStageArt Accountを必要とせず、メールアドレス＋予約番号によって自身の予約を変更・キャンセルできる。

公演回の変更は直接変更ではなく、現在のReservationをキャンセルして新しいPerformanceへ再予約する。

---

# Design Principle

Reservationは「誰が、どの公演回に、何人で、どのTicketを予約したか」という取引Factを管理する。

通常の予約では劇場で現金を支払い、受付担当者が実際に代金を受領した時点で入金と売上を同時に計上する。通常はCheck In受付時の受領となるが、売上計上の根拠はCheckInCompletedではなくPayment Factとする。

劇団スタッフによる代理予約では、スタッフをBookerとして登録でき、観客から事前に受領したTicket代金を「支払済（担当者預り）」として扱える。

スタッフが観客から事前に預かったTicket代金は、受付担当者が現金を受領して支払済みを確定するまでは劇団側の売上・入金として計上しない。受領・売上計上後のスタッフとの精算や、スタッフ側のTicket Back・承認済み立替経費等との相殺は、実際の精算FactとJournal Entryに基づいて管理する。
