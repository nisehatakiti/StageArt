# StageArt Blueprint

# Domain Consistency Policy : Ticket

Version : 1.3

---

# V1 Front Desk Check In

当日受付は「未受付のReservationだけを処理する作業リスト」として運用する。

Check In済みのReservationは受付一覧から除外し、受付担当者が未処理の来場者だけを順次処理できる構成とする。

通常の受付では、受付担当者による料金受領とCheck In処理を同時に行う。会計上は料金受領をPayment Factとして記録し、入金とTicket Revenueを同時に計上する。

```text
未受付Reservation
    ↓
受付で現金受領
    ↓
Check In
    ↓
CHECKED_IN
    ↓
受付一覧から除外
```

したがって、通常の当日受付では料金受領とCheck Inを一連の処理として扱い、「Check In済み = 料金支払済み」とする。ただし会計計上の根拠は実際の料金受領を示すPayment Factである。

「支払済（担当者預り）」のReservationは、受付担当者がスタッフから現金を受領し、支払済みを確定した時点でPayment Factを記録し、入金とTicket Revenueを同時に計上する。既にこの処理が済んでいる場合は、Check In時に追加徴収・再計上を行わない。招待客等、代金を徴収しないReservationは、売上計上対象外であることを区別して表示する。

---

# Front Desk Reservation List

当日受付画面には、原則として未受付のReservationのみを一覧表示する。

一覧には受付担当者が処理に必要な情報を表示する。

基本表示項目：

- 予約者 / Booker
- Ticket
- GuestCount
- 受付時の金額
- 備考
- 支払済等の状態

備考欄には、例えば以下を表示できる。

- 支払済
- NoShow
- 招待客
- その他、受付上必要な注意事項

「支払済」は担当者預り等によって事前に支払済みであることを示す。

---

# NoShow

NoShowは、予約が存在するが来場受付を行わない状態として扱う。

NoShowとなるReservationは未受付一覧に残っていても、公演開始を妨げない。

受付一覧上では、予約者名の前または備考欄等に「NoShow」と表示して、通常の未受付Reservationと区別する。

NoShowは、事前にTicket代金が支払済みであることを前提とする。このためNoShowに「代金未受領」の入力状態は設けない。ただしNoShowの確定は代金受領の記録ではなく、NoShowの確定だけを根拠に入金・Ticket Revenueを計上しない。計上はTicketRevenueConsistencyPolicy「No-Show」に従い、Check In済みの計上と二重にならないようにする。NoShowになったことだけを理由に売上を取り消さない。

NoShowはCHECKED_INとは異なる状態であり、Check In実績を生成しない。

---

# 関係者予約・代済み・無料招待券

Version 1.3（2026-10-28確定）。関係者予約の区分・判定方法は別途定める。

## 代済み

- 「代済み」は関係者予約でのみ指定できる。一般向け予約・販売では指定できないよう、UIとAPIの両方で拒否する。
- 「代済み」は、観客分の代金を関係者から受け取るものとして、関係者に対する未収金をチケット管理上で管理する状態とする。
- 予約作成時、および「代済み」への状態変更時には会計仕訳を作らない。
- チケット管理上の未収金を、会計の売掛金・未収金仕訳に自動連動させない。
- 実際に代金を受領した時点で、既存の会計ルール（入金とTicket Revenueの同時計上、同一Reservationにつき1回）に従って仕訳を計上する。
- 「代済み」は会計上の入金済みを意味しない。チケット管理上の状態と、実際の入金記録（入金・売上仕訳）を区別して扱い、表示上も誤認させない。

## 無料招待券

- 無料招待券は関係者予約でのみ利用できる。一般向け予約・販売では利用を拒否する（UI・API）。
- 無料招待券には売上・入金仕訳を作らない。
- 金額が0円であることだけで招待券と判定せず、券種・予約区分で判定する。

---

# Remaining Capacity Display

当日受付画面には、対象Performanceの残席数を表示する。

例：

```text
定員：40
予約済：32
残席：8
```

残席は現在のReservation状況に基づいて更新する。

この残席表示は、当日飛び込み客を受け付ける際の判断にも使用する。

---

# Walk-in Reservation

当日、事前Reservationを持たない観客が来場した場合に備え、受付画面に「当日客を登録」等の専用Actionを用意する。

当日客の登録は、専用の別Ticket / 別売上処理を作らず、内部的には通常のReservationを1件作成することで処理する。

基本Flow：

```text
[当日客を登録]
    ↓
誰扱いを選択
    ↓
Ticket / 当日料金を選択
    ↓
枚数を入力
    ↓
残席確認
    ↓
現金受領
    ↓
Reservation作成
    ↓
Check In
```

当日客についても「誰扱い」を選択できる。

当日客は、その場で現金を受領してCheck Inまで完了するため、受付一覧には未受付Reservationとして残さない。

当日客も通常のReservationとして記録されるため、Ticket集計、誰扱い別集計、Accounting連携等で通常Reservationと同じように扱える。

---

# Walk-in Capacity Validation

当日客登録時にもPerformanceの最新残席を確認する。

残席が0の場合は当日客を登録できない。

残席が1の場合、2枚以上の当日客登録を認めない。

当日客登録時も保存直前にCapacityを再検証し、同時処理による定員超過を防止する。

---

# Existing Check In Policy

V1ではQR CodeまたはReservation一覧からCheck Inを行う。

QR Codeが存在しない場合でも、タブレットまたはWebブラウザからReservation一覧を表示して対象者を選択し、Check Inできる。

QR Codeは必須条件ではない。

---

# Existing Ticket Rules

- V1の決済方法は現金のみ、原則として劇場払いとする。
- 指定席はV1では扱わない。
- Productionの標準定員をPerformanceへ継承し、Performance単位で変更できる。
- Reservation画面表示時だけでなく保存時にもCapacityを再検証する。
- 公演ページと担当者別ページの両方から同一Reservation Domainへ登録する。
- Reservation成立時に予約コードとQR Codeをメール送信する。
- 予約変更・キャンセルは予約コード＋メールアドレスで照合する。
- Check In済みのReservationは当日受付の未受付一覧から除外する。
- 通常受付では料金受領とCheck Inを一連の処理として行い、Payment Factに基づいて入金とTicket Revenueを同時に計上する。
- スタッフ預りTicketは、受付担当者が現金を受領して支払済みを確定した後、追加徴収なしでCheck Inできる。売上・入金は受領時に一度だけ計上する。
- 招待客等の無料Ticketは、売上・入金の計上対象外としてCheck Inできる。
- NoShowは未受付一覧に残っていても公演開始を妨げない。
- 当日客は通常Reservationとして作成し、その場で現金受領とCheck Inを完了する。
- 当日客登録時にも誰扱いを選択できる。
- 当日客登録時も最新残席を再検証する。
