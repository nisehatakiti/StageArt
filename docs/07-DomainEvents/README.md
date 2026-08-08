# StageArt Blueprint
# Domain Events

Version : 1.0

---

# Purpose

Domain Eventsは、StageArtにおいて発生したBusiness Eventを定義する。

StageArtは利用者の操作ではなく、Business Eventを起点として内部処理を実行する。

利用者は「やりたいこと」を実行するだけであり、その結果発生する処理はDomain Eventによって自動的に連鎖する。

---

# Concept

Domain EventはBusiness Ruleの結果として発生する。

画面遷移やAPI呼び出しを表すものではない。

利用者の操作によって発生したBusiness Eventを表現する。

例）

利用者

↓

「公演を作る」

↓

ProductionCreated

↓

Project初期化

↓

チェックリスト生成

↓

ホームページ生成

---

# Characteristics

Domain Eventは以下の特徴を持つ。

- 過去に発生した事実である。
- Immutableである。
- Business上の意味を持つ。
- Domain Layerで発生する。
- UIやFrameworkへ依存しない。

---

# Event Driven

StageArtはEvent Driven Architectureを採用する。

利用者は内部処理を意識しない。

必要な処理はDomain Eventを契機として自動的に実行される。

例）

OrganizationCreated

↓

Default Membership作成

↓

Default Role作成

↓

Organization初期設定

---

# Event Naming

Domain Eventは過去形で命名する。

例）

- OrganizationCreated
- ProductionCreated
- ReservationCreated
- ReservationCheckedIn
- MembershipJoined

以下のような命名は使用しない。

- CreateOrganization
- SaveReservation
- UpdateProduction

CommandではなくEventを表現する。

---

# Event Categories

StageArtでは以下の種類のDomain Eventを定義する。

- Account Events
- Organization Events
- Production Events
- Performance Events
- Reservation Events
- Membership Events

将来的に必要に応じて追加する。

---

# Event Processing

一つのDomain Eventから複数の処理が実行される場合がある。

例）

ReservationCreated

↓

QRコード生成

↓

確認メール送信

↓

観客履歴更新

↓

売上集計更新

各処理は互いに独立している。

---

# Design Principles

- Domain EventはBusiness Eventを表す。
- Domain EventはImmutableである。
- Domain Eventは過去形で命名する。
- UIやFrameworkへ依存しない。
- Domain Eventを起点としてBusiness Ruleを実行する。
- 利用者は内部イベントを意識しない。
