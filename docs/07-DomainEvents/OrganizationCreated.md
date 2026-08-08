# StageArt Blueprint
# Domain Event : OrganizationCreated

Version : 1.0

---

# Purpose

OrganizationCreatedは、新しいOrganizationが作成されたことを表すDomain Eventである。

OrganizationCreatedはStageArtにおける組織作成完了を通知するBusiness Eventであり、
Organization作成後に必要となるBusiness Processの起点となる。

---

# Trigger

以下の場合に発生する。

- 利用者が新しいOrganizationを作成した。
- Organizationの登録が正常に完了した。

Organizationの保存が完了する前には発生しない。

---

# Event Data

OrganizationCreatedは以下の情報を保持する。

- OrganizationId
- OrganizationName
- OwnerPersonId
- CreatedAt

必要に応じてCorrelationIdやTraceIdなどの技術情報を付加してもよい。

---

# Business Meaning

OrganizationCreatedは

「新しい舞台芸術団体がStageArtへ登録された」

というBusiness上の事実を表す。

OrganizationCreatedはUI操作ではなく、
Business Ruleの結果として発生する。

---

# Event Flow

OrganizationCreatedを契機として、
以下のBusiness Processが実行される。

- Create Default Membership
- Create Default Roles
- Create Default Settings
- Create Document Space

将来的には以下を追加できる。

- Create Public Profile
- Send Welcome Notification
- Initialize Dashboard

各Business Processは互いに独立している。

---

# Preconditions

Organizationが正常に作成されていること。

OrganizationIdが発行されていること。

Owner(Person)が決定していること。

---

# Postconditions

StageArtはOrganizationを利用できる状態となる。

初期設定はBusiness Processによって順次実行される。

OrganizationCreated自身は初期設定を実行しない。

---

# Failure Handling

Business Processの一部が失敗しても、
OrganizationCreatedという事実は取り消さない。

失敗したBusiness Processは再実行可能であることが望ましい。

---

# Design Decisions

OrganizationCreatedはBusiness Eventのみを表現する。

Business Processを保持しない。

Infrastructureの実装方式には依存しない。

同期・非同期はBlueprintでは定義しない。

---

# Related Events

将来的に以下のEventと連携する。

- MembershipJoined
- OrganizationArchived
- OrganizationDeleted

---

# Design Principles

- OrganizationCreatedはBusiness Eventである。
- OrganizationCreatedはImmutableである。
- Business ProcessはEvent Handlerが実行する。
- 一つのEventから複数のBusiness Processを開始できる。
- UIやFrameworkへ依存しない。
