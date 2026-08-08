# StageArt Blueprint
# Domain Events

Version : 1.0

---

# Purpose

Domain Eventは、StageArtにおいて発生したBusiness Eventを定義する。

StageArtは利用者の操作そのものではなく、
Business Eventを起点として内部処理を実行する。

利用者は「何をしたいか」だけを意識し、
その結果必要となる処理はStageArtが自動的に実行する。

---

# Concept

Domain EventはBusiness Ruleの結果として発生した「過去の事実」を表現する。

Domain Eventは画面操作やAPI呼び出しを表すものではない。

例えば、

利用者が「公演を作成する」を実行すると、

ProductionCreated

というBusiness Eventが発生する。

その後、

- 初期設定
- チェックリスト生成
- ドキュメント領域生成

などの処理はProductionCreatedを契機として実行される。

---

# Characteristics

Domain Eventは以下の特徴を持つ。

- 過去に発生した事実である。
- Immutableである。
- Business上の意味を持つ。
- Domain Layerで発生する。
- UIやFrameworkへ依存しない。
- Event単体でBusinessの意味を理解できる。

---

# Event Driven

StageArtはEvent Driven Architectureを採用する。

利用者は内部処理を意識しない。

必要なBusiness ProcessはDomain Eventを契機として自動的に実行される。

一つのDomain Eventから複数のBusiness Processが実行されることを許可する。

---

# Event Naming

Domain Eventは過去形で命名する。

例）

- AccountCreated
- OrganizationCreated
- MembershipJoined
- ProjectCreated
- ProductionCreated
- PerformanceCreated
- ReservationCreated
- ReservationCheckedIn

以下のような命名は使用しない。

- CreateOrganization
- SaveReservation
- UpdateProduction

Commandではなく、
Business Eventを表現する。

---

# Event Categories

StageArtでは以下のカテゴリのDomain Eventを定義する。

- Account Events
- Organization Events
- Membership Events
- Project Events
- Production Events
- Performance Events
- Reservation Events

将来的に必要に応じて追加する。

---

# Event Flow

StageArtでは、一つのDomain Eventから複数のBusiness Processが連鎖して実行される。

利用者は内部処理を意識する必要はない。

---

## Example 1 : Organization

```
利用者
    │
    ▼
OrganizationCreated
    │
    ├── Create Default Membership
    ├── Create Default Roles
    ├── Create Default Settings
    └── Create Document Space
```

---

## Example 2 : Production

```
利用者
    │
    ▼
ProductionCreated
    │
    ├── Initialize Production
    ├── Create Default Performances
    ├── Create Checklist
    └── Create Homepage
```

---

## Example 3 : Reservation

```
利用者
    │
    ▼
ReservationCreated
    │
    ├── Generate QR Code
    ├── Send Confirmation Mail
    ├── Update Audience History
    └── Update Sales
```

---

同じDomain Eventに対して新しいBusiness Processを追加する場合は、

既存Eventを変更するのではなく、
新しいEvent Handlerを追加することを基本とする。

---

# Event Processing

Domain Eventは同期・非同期の実装方式へ依存しない。

Eventがどのように配信・処理されるかはInfrastructure Layerの責務である。

BlueprintではBusiness Eventのみを定義する。

---

# Scope

Domain EventはBusiness Ruleを表現する。

以下はDomain Eventではない。

- Button Click
- API Request
- Database Update
- Mail Send
- File Upload

これらはInfrastructureまたはPresentation Layerの責務である。

---

# Design Principles

- Domain EventはBusiness Eventを表す。
- Domain Eventは過去形で命名する。
- Domain EventはImmutableである。
- Domain EventはUIやFrameworkへ依存しない。
- Business ProcessはDomain Eventを契機として実行される。
- 一つのDomain Eventから複数のBusiness Processが実行できる。
- Event Handlerは互いに独立している。
- 新しい処理は新しいEvent Handlerを追加して実現する。

# Relationship with Golden Rule

StageArtでは、利用者はBusiness Eventを意識しない。

利用者は

- 劇団を作る
- 公演を作る
- 予約する
- 受付する

という目的だけを操作する。

それ以外の処理はDomain Eventによって自動的に実行される。

これはGolden Ruleで定義する

「利用者は内部構造を意識しない」

という原則を実現するための設計である。
