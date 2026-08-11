# StageArt Blueprint

# Domain Model : Participant

Version : 2.0

---

# Purpose

Participantは、
PersonまたはOrganizationがProductionへ参加するというFactを表すDomainである。

ParticipantはProductionと参加主体の関係を表し、
Productionに誰が、どのような立場で参加したかを管理する。

Participantは権限を表すDomainではない。

Organizationにおける管理権限はRoleで管理し、
Productionへの参加区分はParticipantで管理する。

---

# Concept

Participantの基本構造は、

Production
  ↓
Participant
  ↓
Subject
  ├── Person
  └── Organization

とする。

Participantは、
Productionに対する参加というFactを表す。

---

# Subject

Subjectは、
Productionへの参加主体を表す。

Subjectには、

- Person
- Organization

を指定できる。

Person：

個人としてProductionへ参加する場合。

Organization：

団体としてProductionへ参加する場合。

例えば、

- 個人の俳優
- 個人のスタッフ
- 制作会社
- 協力団体
- 協賛団体

などをParticipantとして表現できる。

---

# Participant Identity

ParticipantはParticipantIdによって一意に識別する。

ParticipantIdは変更しない。

PersonやOrganizationそのものを
Participantとして扱うのではなく、
Productionへの参加関係をParticipantとして管理する。

---

# Production Relationship

Participantは必ず一つのProductionに所属する。

Production
  ↓
Participant

一つのProductionには、
複数のParticipantを登録できる。

同じPersonが複数のProductionへ参加する場合、
Productionごとに別のParticipantを持つ。

---

# Participant Type

Participant Typeは、
Productionへの参加区分を表す。

基本的なParticipant Type：

- キャスト
- スタッフ

Participant Typeは権限ではない。

Participant Typeによって、
Organization内で利用できる機能を決定しない。

---

# Cast

キャスト。

Productionに出演するPersonまたはOrganizationを表す。

基本的にはPersonを対象とする。

Castとして登録されたPersonは、
Productionの出演者として扱われる。

---

# Staff

スタッフ。

Productionの制作・運営・技術などに関わる
PersonまたはOrganizationを表す。

スタッフの具体的な職種については、
必要に応じてParticipant Role等へ拡張できる。

初期実装では、
細かなスタッフ職種を必須としない。

---

# Role and Participant Type

Participant TypeとOrganization Roleは、
明確に分離する。

Organization Role：

「その団体で何ができるか」

Participant Type：

「その公演にどう関わっているか」

例えば、

Person A
  Organization A
    Role = 管理者

Production A
  Participant Type = キャスト

という状態を許可する。

また、

Person A
  Organization A
    Role = 会計管理者

Production B
  Participant Type = スタッフ

という状態も許可する。

Participant Typeによって
Organization Roleが変更されることはない。

---

# Multiple Production Participation

一人のPersonは、
複数のProductionへ参加できる。

例：

Person A

  Production A
    Participant Type = キャスト

  Production B
    Participant Type = スタッフ

Productionごとに、
独立したParticipantを持つ。

---

# Multiple Participant Types

同一Personが、
同一Productionにおいて
複数の参加区分を持つ必要がある場合に対応できる構造とする。

ただし、
初期UIでは一つのParticipantに対して
一つのParticipant Typeを設定することを基本とする。

複数の役割が必要な場合は、
必要に応じて複数Participantを作成できる。

---

# Participant Information

Participantには、
Production内での公開・表示に必要な情報を保持できる。

例：

- Participant Type
- Display Name
- Credit Name
- Credit Order
- Visibility
- Status

ただし、
Person自身のプロフィール情報をParticipantへ複製して管理しない。

Person Profileを正本とし、
Participantから参照する。

---

# Credit

Participantは、
Productionの出演者・スタッフ情報として
公開ページへ表示できる。

Credit表示では、

- 表示名
- 表示順
- Participant Type
- その他公開対象情報

などを利用する。

Person ProfileとCredit表示を分離することで、
個人プロフィール上の名前と
公演上のクレジット表記を異なるものにできる。

例：

Person Profile
  山田 太郎

Production Credit
  山田太郎

---

# Visibility

Participantには公開・非公開を設定できる。

公開対象となったParticipantは、
Production Public Pageなどで表示できる。

非公開Participantは、
一般観客向け画面に表示しない。

内部管理画面では、
権限に応じてParticipant情報を確認できる。

---

# Status

Participantには状態を持たせる。

基本的な状態：

- ACTIVE
- INACTIVE
- CANCELLED

ACTIVE：

Productionへの参加が有効。

INACTIVE：

一時的に参加対象から外れている状態。

CANCELLED：

Productionへの参加が取り消された状態。

過去の参加Factを保持するため、
Participantを物理削除することは原則として行わない。

---

# Participant History

Participantは、
Personの活動履歴を生成するためのFactとなる。

基本構造：

Participant
  ↓
History

Personがキャストとして参加した場合：

Participant
  Participant Type = キャスト
      ↓
History
  出演履歴

Personがスタッフとして参加した場合：

Participant
  Participant Type = スタッフ
      ↓
History
  スタッフ履歴

Historyそのものは、
Participant内に保存しない。

History Domainが、
ParticipantなどのFactをもとに管理する。

---

# Organization History

SubjectがOrganizationの場合も、
Productionへの参加履歴をHistoryへ利用できる。

例えば、

Organization A
  Participant
    Production B
      Participant Type = スタッフ

というFactから、
Organization AのProduction参加履歴を生成できる。

---

# Information Sharing

Participant Typeは、
Productionに関する情報共有の対象条件として利用できる。

例えば、

Announcement

  対象Participant Type
    → キャスト

の場合、
そのProductionのキャストを対象として
情報を共有できる。

同様に、

  対象Participant Type
    → スタッフ

とすることで、
スタッフのみを対象にできる。

---

# Information Sharing and Organization Role

情報共有では、
Participant TypeとOrganization Roleを
組み合わせて対象者を指定できる。

例えば、

Announcement

  対象Role
    → 稽古管理者

  対象Participant Type
    → キャスト

とした場合、

- Organizationの稽古管理者
- Productionのキャスト

を対象として共有できる。

RoleとParticipant Typeは、
それぞれ別のFactとして参照する。

---

# Participant and Authorization

Participant Typeそのものは、
Authorizationを付与しない。

例えば、

Participant Type = キャスト

であることだけを理由に、

- Production管理
- 会計管理
- Membership管理

などの権限を付与してはならない。

Productionに対する管理権限が必要な場合は、
PrimaryManagerまたはProductionDelegateを利用する。

Organization全体の権限が必要な場合は、
MembershipのRoleを利用する。

---

# Participant and Production Delegate

ParticipantとProductionDelegateは別の概念である。

Participant：

Productionへ参加しているというFact。

ProductionDelegate：

Productionを管理する権限を委任されたFact。

同じPersonが両方を持つことはできる。

例：

Person A
  Participant
    Type = スタッフ

  ProductionDelegate
    Permission = 稽古管理

---

# Participant and Membership

ParticipantとMembershipも別の概念である。

Membership：

PersonとOrganizationの所属関係。

Participant：

PersonまたはOrganizationとProductionの参加関係。

例えば、

Person A
  Membership
    Organization A

  Participant
    Production B

という状態を持つことができる。

---

# External Participant

Productionには、
Organizationに所属していないPersonも参加できる。

例えば客演の場合、

Person A
  Membership
    Organization B

  Participant
    Production A
    Participant Type = キャスト

という状態を許可する。

Participantのために、
Production所属OrganizationへのMembershipを
必須としない。

---

# Guest Appearance

客演は、
Participantとして表現する。

客演者は、
ProductionのParticipantとして登録できる。

所属Organizationが異なる場合でも、
Productionへの参加を妨げない。

Participant Typeは、
通常どおりキャストまたはスタッフを設定する。

---

# Participant Lifecycle

基本的なLifecycle：

DRAFT
  ↓
ACTIVE
  ↓
INACTIVE
  ↓
ACTIVE
  ↓
CANCELLED

Participantの作成後、
Productionへの参加が確定した場合にACTIVEとなる。

---

# Business Rules

- ParticipantはProductionへの参加Factを表す。
- Participantは権限を表さない。
- ParticipantはProductionに所属する。
- ParticipantのSubjectはPersonまたはOrganizationとする。
- Personは複数Productionへ参加できる。
- OrganizationもProductionへ参加できる。
- Productionごとに独立したParticipantを持つ。
- Participant TypeはProductionへの参加区分を表す。
- 基本Participant Typeはキャストとスタッフとする。
- Participant TypeとOrganization Roleを分離する。
- Participant TypeによってAuthorizationを付与しない。
- Production管理権限はPrimaryManagerまたはProductionDelegateで管理する。
- Organization管理権限はMembershipのRoleで管理する。
- OrganizationへのMembershipを持たないPersonもProductionへ参加できる。
- 客演はParticipantとして管理する。
- ParticipantからPersonまたはOrganizationのProfile情報を複製しない。
- Person Profileを人物情報の正本とする。
- Production上のクレジット表示はParticipantで管理できる。
- Participantの公開・非公開を管理できる。
- Participantは原則として物理削除しない。
- ParticipantはHistory生成のFactとなる。
- Participant Typeは情報共有の対象条件として利用できる。

---

# Domain Events

Participantに関する主なDomain Event：

- ParticipantAdded
- ParticipantUpdated
- ParticipantTypeChanged
- ParticipantActivated
- ParticipantDeactivated
- ParticipantCancelled
- ParticipantVisibilityChanged

Participantの変更によって、
必要に応じてHistory関連の処理を実行する。

---

# Design Decisions

ParticipantはStageArtにおける重要なFact Domainとして残す。

PersonとProductionを直接関連付けず、
Participantによって参加関係を表現する。

Participantは、
「誰が公演に参加したか」
という事実を管理する。

Organization Roleは、
「その団体で何ができるか」
を表す。

Participant Typeは、
「その公演にどう関わったか」
を表す。

この2つを混同しない。

初期実装ではParticipant Typeを、

- キャスト
- スタッフ

に限定する。

細かなスタッフ職種などは、
必要になった時点で拡張する。

Participantは、
出演履歴・スタッフ履歴の生成元となる。

Participantは、
情報共有の対象者を決定する際にも利用できる。

---

# Future

将来的に必要となった場合、

- スタッフ職種
- 役名
- 制作担当
- 技術担当
- クレジットグループ
- Participantごとの追加属性
- 複数Participant Type
- Participant Order
- 公演ごとの役職

などへ拡張できる構造とする。

ただし、
初期実装ではParticipantを複雑化しない。

---

# Design Principles

- ParticipantはProductionへの参加Factである。
- Participantは権限を表さない。
- ParticipantのSubjectはPersonまたはOrganizationである。
- ParticipantはProductionに所属する。
- Participant TypeとOrganization Roleを分離する。
- キャストとスタッフはParticipant Typeで管理する。
- Participant TypeによってAuthorizationを付与しない。
- Organization権限はMembership / Roleで管理する。
- Production権限はPrimaryManager / ProductionDelegateで管理する。
- 客演はParticipantとして表現する。
- OrganizationへのMembershipを持たないPersonもProductionへ参加できる。
- ParticipantからProfile情報を複製しない。
- ParticipantはCredit情報を管理できる。
- Participantは公開・非公開を管理できる。
- ParticipantはHistory生成のFactとなる。
- Participant Typeは情報共有に利用できる。
- Blueprintを唯一の設計基準とする。
