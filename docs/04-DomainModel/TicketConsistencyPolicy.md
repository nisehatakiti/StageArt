# StageArt Blueprint

# Domain Consistency Policy : Ticket

Version : 1.1

---

# Purpose

本書はTicket Domainについて、現在のCanonical Domain Modelおよび確定したTicket仕様との整合性を定義する。

---

# V1 Sales Scope

StageArt V1のTicket販売は、オンライン決済を行わず、現金のみ・原則として劇場払いとする。

StageArtは外部Ticket販売サイトへ予約を委譲せず、Production Public PageからStageArtのReservationへ直接つなげる。

ただし、劇団担当者が観客からTicket代金を事前に現金で預かって代理予約するケースを許可する。この場合はReservation側のStaff-Held Ticket Payment Policyに従う。

指定席はV1では扱わない。V1では全席自由席を前提とし、定員はPerformance単位で管理する。

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

# Capacity Model

定員はProduction単位で標準値を保持し、Performance作成時に引き継ぐ。

Performance側では、引き継いだ定員を個別に変更できる。

```text
Production
    ↓
Standard Capacity
    ↓
Performance Capacity
    ↓
Reservation
```

予約状況に応じてPerformanceの残席数を算出する。

例えば定員40、残席1の場合、2枚以上のReservationは成立させない。

残席数はReservationの有効状態およびGuestCountに基づいて計算する。

---

# Reservation Capacity Revalidation

Reservation画面表示時の残席数だけを信頼してはならない。

Reservation保存時に、最新の残席数を再検証する。

例：

```text
予約開始時
残席 3
    ↓
3枚を選択
    ↓
別Reservationが成立
    ↓
残席 2
    ↓
「OK」
    ↓
保存直前にCapacity再検証
    ↓
3枚 > 残席2
    ↓
Reservation不成立
```

残席が0以下となった場合はReservationを成立させない。

Reservation保存処理とCapacity Validationは一貫したTransaction / Concurrency Control単位で処理し、同時予約による定員超過を防止する。

---

# Reservation Entry Points

V1ではReservationへの入口を二つ用意する。

## Production Public Page

公演ホームページから通常の予約を行う。

基本Flow：

```text
公演ページ
    ↓
公演回を選択
    ↓
誰扱いを選択
    ↓
Ticket / 枚数を選択
    ↓
メールアドレス入力
    ↓
予約内容確認
    ↓
「OK」
    ↓
Capacity再検証
    ↓
Reservation成立
```

予約確定時点でReservationが成立する。

## Staff Sales Page

Productionごとに、担当者別の予約ページを用意できる。

このページは担当者自身がSNS等で公開し、「自分扱いのTicket」を受け付けるために使用する。

担当者別ページでは「誰扱い」を担当者に固定する。

観客は以下のみを選択する。

- 公演回
- Ticket / 枚数
- メールアドレス

基本Flow：

```text
担当者別ページ
    ↓
担当者は固定
    ↓
公演回を選択
    ↓
Ticket / 枚数を選択
    ↓
メールアドレス入力
    ↓
予約内容確認
    ↓
「OK」
    ↓
Capacity再検証
    ↓
Reservation成立
```

Production Public PageとStaff Sales Pageは入口が異なるだけで、最終的には同一のReservation Domainを使用する。

---

# Email Requirement

Reservationではメールアドレスを必須とする。

Reservation成立時に、登録されたメールアドレスへ予約情報を送信する。

送信内容には少なくとも以下を含める。

- 公演名
- 公演日時
- 会場
- Ticket情報
- 枚数 / GuestCount
- 予約コード
- QR Code
- 受付案内
- 予約変更ページへの案内

メール送信失敗によって成立済みReservationを取り消してはならない。

---

# Reservation Confirmation

予約内容確認画面で、利用者が公演回・Ticket・枚数等を確認し「OK」を押した時点で予約確定処理へ進む。

予約確定処理ではCapacityを再検証する。

再検証を通過した場合のみReservationを保存し、予約コードおよびQR Codeをメール送信する。

---

# Reservation Change / Cancellation

予約変更・キャンセル専用ページを提供する。

予約者はStageArtアカウントを作成・ログインすることなく、予約コードとメールアドレスで対象Reservationを照合できる。

```text
予約変更ページ
    ↓
予約コード + メールアドレス
    ↓
Reservation照合
    ↓
対象Reservation表示
    ├─ 枚数変更
    └─ キャンセル
    ↓
確定
    ↓
最新のReservation内容をメール送信
```

変更後は新しい内容を予約者のメールアドレスへ再送する。

枚数変更時は変更後のGuestCountについてCapacityを再検証する。

公演回の変更は直接行わず、現在のReservationをキャンセルしたうえで新しいPerformanceへ再予約する。

---

# Check In

Check InはReservation単位で行う。

V1ではQR Codeと一覧操作の二つの入口を提供する。

## QR Check In

スマートフォン等で予約者が提示するQR Codeを読み取り、Reservationを特定してCheck Inする。

QR Codeは便利な受付手段であり、必須条件ではない。

## Manual Check In

予約者がQR Codeを提示できない場合、タブレットまたはWebブラウザからReservation一覧を表示し、対象Reservationを選択してCheck Inできる。

したがって、QR Codeを持っていない予約者も一覧からCheck In可能とする。

基本Flow：

```text
QRあり
  ↓
QR読取
  ↓
Reservation特定
  ↓
Check In

QRなし
  ↓
タブレット / Web
  ↓
Reservation一覧
  ↓
対象Reservation選択
  ↓
Check In
```

Check In完了後はReservation = CHECKED_INとする。

「支払済（担当者預り）」のReservationは、受付時に代金済として扱い、追加徴収を行わない。

---

# V1 Seat Policy

指定席はV1では扱わない。

V1のCapacityはPerformance単位の総定員として管理し、Reservationでは席番号を保持しない。

指定席対応は将来拡張として扱う。

---

# Revenue Boundary

Ticket Priceは販売条件であり、会計上の売上そのものではない。

Reservationの成立だけで確定売上とはしない。

CheckInCompletedを契機としてTicket RevenueをAccounting Domainへ連携するという既存方針を維持する。

Ticket DomainはJournal Entryを直接管理しない。

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

# Deletion

過去Reservationから参照されるTicketは物理削除しない。

販売終了時はCLOSED等の状態で保持する。

これにより過去Reservationの取引条件を追跡可能とする。

---

# Business Rules

- TicketはProductionに所属する。
- TicketはProduction固有の販売条件を表す。
- V1の決済方法は現金のみ、原則として劇場払いとする。
- V1では指定席を扱わず、全席自由席を前提とする。
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
- Productionの標準定員をPerformanceへ継承する。
- Performanceは継承した定員を個別変更できる。
- Reservation作成時だけでなく保存時にもCapacityを再検証する。
- 残席が不足する場合、Reservationを成立させない。
- Reservation成立時にPrice Snapshotを保持する。
- Ticketの現在価格変更で過去Reservationを変更しない。
- Production Public Pageから通常予約を受け付ける。
- 担当者別Staff Sales Pageを用意できる。
- Staff Sales Pageでは担当者を固定し、観客は公演回・Ticket / 枚数・メールアドレスを入力する。
- 通常予約でもメールアドレスを必須とする。
- 予約内容確認後に「OK」を押し、Capacity再検証を通過した時点でReservationを成立させる。
- Reservation成立時に予約コードとQR Codeをメール送信する。
- 予約変更・キャンセルは予約コード＋メールアドレスで照合する。
- 枚数変更時はCapacityを再検証する。
- 変更後は最新のReservation内容をメール送信する。
- Check InはQR読取またはReservation一覧からの手動操作で行える。
- QR Codeを持たない予約者もタブレット / Webの一覧からCheck Inできる。
- Ticket PriceとTicket Revenueを同一視しない。
- Ticket DomainはJournal Entryを管理しない。
- Ticket StatusとPublic Visibilityを分離する。
- 過去Reservationから参照されるTicketを物理削除しない。
