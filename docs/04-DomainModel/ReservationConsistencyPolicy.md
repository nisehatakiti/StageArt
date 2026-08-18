# StageArt Blueprint

# Domain Consistency Policy : Reservation

Version : 1.2

---

# Purpose

Reservation Domainについて、Performance / Ticket / Capacity / Price Snapshot / Check In / Accounting / Reservation Notificationに加え、予約者自身による予約変更・キャンセルの業務フローを定義する。

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

別の公演回へ変更したい場合は、**現在の予約をキャンセルしたうえで、新しい公演回へ改めて予約を取り直す必要がある**ことを予約変更フォームおよび予約確認メール等で明示する。

既存Reservationを別Performanceへ直接移動させてはならない。

理由：

- Performanceごとの定員管理
- Reservation Factの履歴保持
- Ticket / Price Snapshot
- Check In
- Accounting

の整合性を維持するため。

---

# Reservation Change Flow

```text
Reservation Change Form
        ↓
Email + Reservation Number
        ↓
Reservation Verification
        ↓
Current Reservation
        ↓
Change Name / Email / Ticket / GuestCount等
        ↓
Capacity / Ticket Validation
        ↓
Reservation Update
        ↓
変更完了メール
        ↓
最新の予約内容・必要に応じてQR Codeを再通知
```

変更後は予約者へ最新の予約内容をメール送信する。

QR Codeに変更前のReservation状態を識別する情報が含まれる場合は、変更後も正しくCheck Inできるよう更新・再発行する。

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

# Booker

Bookerは予約者を表す。

BookerとCreatedByは別概念とする。

観客本人がBookerで、劇団スタッフが代理入力のCreatedByとなる状態を許可する。

---

# Guest Count

GuestCountはReservationで確保する人数を表す。

初期仕様では同行者を独立Person / Companionとして管理しない。

---

# Check In

Check InはReservation単位で行う。

QR Codeを読み取った場合、Reservationを安全に検証して対象Reservationを特定し、通常のCheck In処理へ進む。

Check In完了後はCHECKED_INとなり、通常のReservation変更を行わない。

---

# Accounting

Reservation DomainはJournal Entryを管理しない。

CheckInCompletedを契機としてTicket Revenue等の会計連携をAccounting Domainへ渡す。

```text
Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue
    ↓
Accounting Journal Entry
```

Accounting上の正本はJournal Entry。

Reservationが会計Factを二重管理しない。

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
          ├── Ticket Reference
          ├── Price Snapshot
          ├── Guest Count
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

予約成立時には自動生成された予約番号とCheck In用QR Codeを予約確認メールで送信する。

予約者は専用フォームでメールアドレスと予約番号を入力することで、ログインなしに予約内容の変更・キャンセルを行える。

ただし、公演回そのものは変更できず、別公演回への変更はキャンセル後の再予約を必要とする。
