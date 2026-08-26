# StageArt Blueprint

# Domain Consistency Policy : Ticket

Version : 1.2

---

# V1 Front Desk Check In

当日受付は「未受付のReservationだけを処理する作業リスト」として運用する。

Check In済みのReservationは受付一覧から除外し、受付担当者が未処理の来場者だけを順次処理できる構成とする。

通常の受付では、料金の受け渡しとCheck In処理を同時に行う。

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

したがって、通常の当日受付では「Check In済み = 料金支払済み」として扱う。

「支払済（担当者預り）」のReservationおよび招待客等、受付時に追加徴収を必要としないReservationについては、備考欄等にその状態を表示する。

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

NoShowは、事前にTicket代金が支払済みであることを前提とする。

NoShowはCHECKED_INとは異なる状態であり、Check In実績を生成しない。

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
- 通常受付では料金受領とCheck Inを同時に処理する。
- 支払済（担当者預り）および招待客等は追加徴収なしでCheck Inできる。
- NoShowは未受付一覧に残っていても公演開始を妨げない。
- 当日客は通常Reservationとして作成し、その場で現金受領とCheck Inを完了する。
- 当日客登録時にも誰扱いを選択できる。
- 当日客登録時も最新残席を再検証する。
