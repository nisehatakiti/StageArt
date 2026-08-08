# StageArt Blueprint
# Domain Event : ProductionCreated

Version : 1.0

---

# Purpose

ProductionCreatedは、新しいProductionが作成されたことを表すDomain Eventである。

ProductionCreatedは公演制作開始を通知するBusiness Eventであり、
公演運営に必要な初期処理の起点となる。

---

# Trigger

以下の場合に発生する。

- 利用者が新しい公演を作成した。
- Productionの登録が正常に完了した。

Productionの保存が完了する前には発生しない。

---

# Event Data

ProductionCreatedは以下の情報を保持する。

- ProductionId
- ProjectId
- OrganizationId
- ProductionName
- CreatedBy(PersonId)
- CreatedAt

必要に応じてCorrelationIdやTraceIdなどの技術情報を付加してもよい。

---

# Business Meaning

ProductionCreatedは

「新しい公演が作成された」

というBusiness上の事実を表す。

ProductionCreatedはUI操作ではなく、
Business Ruleの結果として発生する。

---

# Event Flow

ProductionCreatedを契機として、
以下のBusiness Processが実行される。

- Create Default Performance
- Initialize Production Settings
- Create Public Page
- Create Document Workspace
- Create Production Checklist

将来的には以下を追加できる。

- Create Budget
- Create Rehearsal Schedule
- Create Task Board
- Create Ticket Settings
- Create Notification Settings

各Business Processは互いに独立している。

---

# Preconditions

以下を満たしていること。

- Organizationが存在する。
- Projectが存在する。
- Productionが正常に作成されている。
- ProductionIdが発行されている。

---

# Postconditions

Productionは利用可能な状態となる。

初期設定はBusiness Processによって順次実行される。

ProductionCreated自身はBusiness Processを実行しない。

---

# Failure Handling

Business Processの一部が失敗しても、
ProductionCreatedという事実は取り消さない。

失敗したBusiness Processは再実行可能であることが望ましい。

---

# Design Decisions

ProductionCreatedはBusiness Eventのみを表現する。

Business Processを保持しない。

Infrastructureの実装方式には依存しない。

同期・非同期はBlueprintでは定義しない。

ProductionCreatedはProjectを作成しない。

ProjectはProduction作成前に存在していることを前提とする。

---

# Related Events

将来的に以下のEventと連携する。

- PerformanceCreated
- ParticipantAdded
- ProductionPublished
- ProductionArchived

---

# Design Principles

- ProductionCreatedはBusiness Eventである。
- ProductionCreatedはImmutableである。
- Business ProcessはEvent Handlerが実行する。
- 一つのEventから複数のBusiness Processを開始できる。
- UIやFrameworkへ依存しない。
