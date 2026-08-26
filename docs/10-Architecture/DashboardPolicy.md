# StageArt Blueprint

# Dashboard Policy

Version : 1.0

---

# Purpose

StageArt Management ClientのHOME / Dashboardに表示する情報と表示ルールを定義する。

Dashboardは、ログインしたユーザーが「自分に関係する次の予定」と「自分に届いた業務通知」を確認するための入口とする。

権限ごとにDashboardを別物として設計せず、ログインユーザーを基準として表示内容を決定する。

---

# Dashboard Structure

Dashboardの主要表示は以下の2つとする。

1. 自分が関わっている公演の稽古予定
2. 自分に届いた通知

```text
HOME / Dashboard
│
├─ 自分が関わる稽古予定
│
└─ 自分宛て通知
```

---

# Rehearsal Schedule

ログインしたユーザーアカウントが関わっているProductionについて、当該ユーザーが参加対象となっているRehearsalを表示する。

Rehearsalは日付・時刻の近い順に表示する。

過去に終了したRehearsalはDashboardの今後の予定一覧には表示しない。

表示する基本情報は以下とする。

- 稽古日
- 開始時刻 / 終了時刻
- 稽古場所
- Production名

各稽古項目から当該Rehearsalの詳細画面へ遷移できる。

複数のProductionに参加している場合は、Productionをまたいで時系列に表示する。

Dashboard上でProductionごとの専用セクションを作るのではなく、「自分の次の予定」として一つの時系列にまとめる。

---

# Notification

Dashboardには、ログインユーザー自身に届いたNotificationを表示する。

Notificationの内容・既読 / 未読・履歴についてはNotification Policyに従う。

Dashboardでは通知の概要を確認でき、通知項目から通知詳細または関連する業務画面へ遷移できる構造を基本とする。

Dashboardに表示する通知は、他のMember宛ての通知を含めず、ログインユーザー自身をRecipientとするものだけとする。

---

# Role Independence

Primary Manager、稽古管理者、会計管理者、一般Member等の権限によってDashboardの基本構造を変更しない。

権限による操作可能範囲やManagement Navigationの表示・非表示は、Management Navigation Policyおよび各DomainのAuthorization Policyで定義する。

Dashboardは権限別の業務一覧を追加するための画面ではなく、ユーザー個人の予定と通知を確認するための画面とする。

例えば、会計管理者だからといってDashboardに「承認待ち経費一覧」を専用カードとして追加する設計はV1では採用しない。必要な業務通知はNotificationとして本人へ届く。

---

# Business Rules

- Management ClientのHOMEはDashboardとする。
- Dashboardはログインユーザーを基準に生成する。
- 自分が関わるProductionのRehearsalを対象とする。
- 自分が参加対象となっているRehearsalのみ表示する。
- Rehearsalは日付・時刻の近い順に表示する。
- 過去に終了したRehearsalは今後の予定一覧から除外する。
- 複数Productionの稽古をProduction横断で時系列表示する。
- Dashboardには自分宛てNotificationのみ表示する。
- Notificationの未読 / 既読管理はNotification Policyに従う。
- 権限によってDashboardの基本構造を変えない。
- 権限による業務メニューの表示・操作範囲はNavigation / Authorization側で制御する。
- V1ではDashboardに権限別の専用業務カードを追加しない。
