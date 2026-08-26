# StageArt Blueprint

# Notification Policy

Version : 1.0

---

# Purpose

StageArtのNotificationは、公演運営に必要な業務通知を扱う。

StageArtは劇団員同士の自由なコミュニケーションツールを目的としない。

自由なメッセージ発信や日常的な連絡はLINE等の外部コミュニケーション手段を利用するものとし、V1では劇団員向けメッセージ配信機能を実装しない。

---

# Notification Channels

V1では以下の2つを通知手段とする。

- StageArt内通知
- Email

業務上必要な通知は必須通知とし、利用者が通知をOFFにできないものとする。

Google CalendarはNotification Channelではなく、確定したRehearsal予定を外部Calendarへ連携するIntegrationとして扱う。

---

# Notification Read Status

StageArt内通知は以下の状態を管理する。

- 未読
- 既読

通知メニューから未読件数を確認でき、過去の通知履歴を参照できる。

---

# Rehearsal Notifications

稽古関連では以下を通知する。

## Confirmed Rehearsal Schedule

稽古日程が確定した場合、稽古参加者へ通知する。

ただし、稽古を確定するたびに個別メールを送信して通知が過剰にならないよう、確定した稽古日程は一定時点でまとめて送信できる構造とする。

まとめ通知の具体的な送信タイミング・単位はNotification Delivery仕様で定義する。

## Attendance Response

出欠回答が登録された場合、稽古管理者へ通知する。

## Attendance Deadline Reminder

出欠回答期限が近づいた場合、未回答者等の必要な対象者へ通知する。

## Rehearsal Change

稽古の日時、場所、内容等が変更された場合、当該稽古の参加者全員へ通知する。

## Day-Before Reminder

稽古前日の夜に、当該稽古の参加者全員へリマインドを送信する。

---

# Production Notifications

公演情報に変更が生じた場合、公演参加者全員へ通知する。

ここでいう公演情報変更には、Production運営上、参加者に影響する公開・管理情報の変更を含む。

通知対象は当該Productionに所属する参加者とする。

---

# Ticket Notifications

Ticket / Reservation関連では、担当者自身の「誰扱い」に関する以下の事象を通知する。

- 自分扱いでの予約成立
- 自分扱いでの予約変更
- 自分扱いでの予約キャンセル
- 当日来場者

通知対象は、該当Reservationの誰扱い担当者とする。

---

# Accounting Notifications

会計関連では、立替金の申請を通知する。

通知対象は以下とする。

- 会計管理者
- 公演管理者
- 代理人

---

# Notification Recipient Policy

基本的な通知対象は以下とする。

| Event | Recipient |
|---|---|
| 確定した稽古日程 | 稽古参加者全員 |
| 出欠回答登録 | 稽古管理者 |
| 出欠期限接近 | 必要な対象者 |
| 稽古変更 | 稽古参加者全員 |
| 稽古前日リマインド | 稽古参加者全員 |
| 公演情報変更 | Production参加者全員 |
| 自分扱い予約成立 | 誰扱い担当者 |
| 自分扱い予約変更 | 誰扱い担当者 |
| 自分扱い予約キャンセル | 誰扱い担当者 |
| 当日来場者 | 誰扱い担当者 |
| 立替金申請 | 会計管理者・公演管理者・代理人 |

---

# Mandatory Notification

V1で定義した業務通知は必須通知として扱う。

利用者が個別の通知をOFFにするための設定は設けない。

通知送信に失敗した場合でも、元となるBusiness Factをロールバックしてはならない。

Notificationの送信状態・再送可否等はNotification Infrastructureで管理する。

---

# Communication Boundary

StageArt Notificationは業務上の状態変化を伝達するための機能であり、自由なメッセージング機能ではない。

V1では以下をStageArt Notificationの対象としない。

- 劇団員から劇団員への自由なメッセージ
- 雑談
- 一般的な応援・連絡
- 公演運営Factを伴わない任意のお知らせ

これらはLINE等の外部コミュニケーション手段を利用する。

将来、劇団員が入力したメッセージを劇団ホームページ等へ公開する機能を追加する場合は、V2以降の別機能として定義する。

---

# Business Rules

- V1のNotification ChannelはStageArt内通知とEmailとする。
- 業務上必要な通知は必須通知とし、利用者によるOFF設定を設けない。
- StageArt内通知は未読・既読を管理する。
- 確定した稽古日程は稽古参加者全員へ通知する。
- 確定した稽古日程は一定時点でまとめて送信できる。
- 出欠回答登録は稽古管理者へ通知する。
- 出欠回答期限が近づいた場合は必要な対象者へ通知する。
- 稽古変更は稽古参加者全員へ通知する。
- 稽古前日の夜に参加者へリマインドする。
- 公演情報変更はProduction参加者全員へ通知する。
- 自分扱いのTicket予約成立・変更・キャンセルを誰扱い担当者へ通知する。
- 当日来場者を誰扱い担当者へ通知する。
- 立替金申請を会計管理者・公演管理者・代理人へ通知する。
- StageArtは自由な劇団員間コミュニケーションツールではない。
- V1では劇団員が入力する自由メッセージ機能を実装しない。
