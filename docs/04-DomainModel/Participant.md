# StageArt Blueprint

# Domain Model : Participant

Version : 2.1

---

# Purpose

Participantは、
PersonまたはOrganizationがProductionへ参加するというFactを表すDomainである。

ParticipantはProductionと参加主体の関係を表し、
Productionに誰が、どのような立場で参加したかを管理する。

Participantは権限を表すDomainではない。

Organizationにおける管理権限はRoleで管理し、
Productionへの参加区分はParticipantで管理する。

Production単位の管理権限は、
PrimaryManagerまたはProductionDelegateで管理する。

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

PersonやOrganizationそのものに
Productionへの参加属性を直接追加するのではなく、
Participantという独立したEntityによって
参加関係を管理する。

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

Participantは、
SubjectそのもののIdentityではない。

---

# Production Relationship

Participantは必ず一つのProductionに所属する。

基本構造：

Production
    ↓
Participant

一つのProductionには、
複数のParticipantを登録できる。

同じPersonが複数のProductionへ参加する場合、
Productionごとに別のParticipantを持つ。

例：

Person A
    ↓
Participant
    ↓
Production A

Person A
    ↓
Participant
    ↓
Production B

Productionごとの参加関係は独立して管理する。

---

# Participant Type

Participant Typeは、
Productionへの参加区分を表す。

基本的なParticipant Type：

- CAST
- STAFF

Participant Typeは権限ではない。

Participant Typeによって、
Organization内で利用できる機能を決定しない。

Participant Typeによって、
Production管理権限を付与しない。

---

# Cast

CASTは、
Productionに出演する参加者を表す。

基本的にはPersonを対象とする。

CASTとして登録されたPersonは、
Productionの出演者として扱われる。

Organizationとして参加する場合も、
Business Ruleに応じてCASTを指定できる。

---

# Staff

STAFFは、
Productionの制作・運営・技術などに関わる
参加者を表す。

STAFFの具体的な職種については、
必要に応じてParticipantの属性を拡張できる。

初期実装では、
細かなスタッフ職種を必須としない。

STAFFであること自体は、
Production管理権限を意味しない。

---

# Role and Participant Type

Participant TypeとRoleは、
明確に分離する。

Role：

「そのScopeで何ができるか」

Participant Type：

「そのProductionにどう関わっているか」

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Permission

一方、

Production
    ↓
Participant
    ↓
Participant Type

となる。

例えば、

Person A
    ↓
Membership
    ↓
Organization A
    ↓
Role = Administrator

Production A
    ↓
Participant
    ↓
Participant Type = CAST

という状態を許可する。

また、

Person A
    ↓
Membership
    ↓
Organization A
    ↓
Role = Accounting Manager

Production B
    ↓
Participant
    ↓
Participant Type = STAFF

という状態も許可する。

Participant Typeによって
Organization Roleが変更されることはない。

Roleによって
Participant Typeが自動的に決定されることもない。

---

# Participant and ProductionDelegate

ParticipantとProductionDelegateは、
別の概念である。

Participant：

Productionへ参加しているというFact。

ProductionDelegate：

Production Scopeにおいて
PersonへRoleを適用している関係。

基本構造：

Production
    ├── Participant
    │      └── Person / Organization
    │
    └── ProductionDelegate
           ├── Person
           └── Role

同じPersonが、
ParticipantとProductionDelegateの
両方になることはできる。

例えば、

Person A
    ↓
Participant
    ↓
Production A
    ↓
Participant Type = STAFF

Person A
    ↓
ProductionDelegate
    ↓
Production A
    ↓
Role = Rehearsal Manager

という状態を許可する。

STAFFであることによって
Rehearsal Manager権限が付与されるわけではない。

ProductionDelegateによって
別途Roleが適用される。

---

# Participant and Membership

ParticipantとMembershipも、
別の概念である。

Membership：

PersonとOrganizationの所属関係。

Participant：

PersonまたはOrganizationとProductionの参加関係。

基本構造：

Person
    ↓
Membership
    ↓
Organization

Person
    ↓
Participant
    ↓
Production

OrganizationへのMembershipと、
ProductionへのParticipantは独立している。

---

# External Participant

Productionには、
Organizationに所属していないPersonも参加できる。

例えば客演の場合、

Person A
    ↓
Membership
    ↓
Organization B

Person A
    ↓
Participant
    ↓
Production A
    ↓
Participant Type = CAST

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
通常どおりCASTまたはSTAFFを設定できる。

---

# Multiple Production Participation

一人のPersonは、
複数のProductionへ参加できる。

例：

Person A

Production A
    Participant Type = CAST

Production B
    Participant Type = STAFF

Production C
    Participant Type = CAST

Productionごとに、
独立したParticipantを持つ。

一つのParticipantを
複数Productionで共有しない。

---

# Multiple Participant Types

同一Personが、
同一Productionにおいて
複数の参加区分を持つ必要がある場合に対応できる構造とする。

初期実装では、
一つのParticipantに対して
一つのParticipant Typeを設定することを基本とする。

複数の参加区分を必要とする場合は、
複数Participantを作成できる。

ただし、
同一Subject・同一Productionに対する
Participantの重複を無条件に許可するものではない。

複数Participantを許可する具体的な条件は、
Participant DomainのBusiness Ruleで定義する。

---

# Participant Information

Participantには、
Production内での表示・管理に必要な情報を保持できる。

例：

- Participant Type
- Display Name
- Credit Name
- Credit Order
- Visibility
- Status

ただし、
Person自身のプロフィール情報をParticipantへ複製して管理しない。

Person Profileを人物情報の正本とし、
Participantから参照する。

Organizationの場合も、
Organizationの基本情報をParticipantへ複製しない。

Production上で必要な表示情報のみ、
Participant側に保持できる。

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

Participantには、
公開・非公開を設定できる。

公開対象となったParticipantは、
Production Public Pageなどで表示できる。

非公開Participantは、
一般観客向け画面に表示しない。

内部管理画面では、
権限に応じてParticipant情報を確認できる。

Visibilityは、
Participantそのものの公開可否を表す。

Person ProfileやOrganization Profileの
公開設定とは別に管理する。

---

# Status

Participantには状態を持たせる。

基本的な状態：

- DRAFT
- ACTIVE
- INACTIVE
- CANCELLED

DRAFT：

Participantとして登録されたが、
Productionへの参加がまだ確定していない状態。

ACTIVE：

Productionへの参加が有効な状態。

INACTIVE：

一時的に参加対象から外れている状態。

CANCELLED：

Productionへの参加が取り消された状態。

過去の参加Factを保持するため、
Participantを物理削除することは原則として行わない。

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

参加が確定した場合：

DRAFT
    ↓
ACTIVE

一時的に参加対象から外す場合：

ACTIVE
    ↓
INACTIVE

復帰する場合：

INACTIVE
    ↓
ACTIVE

参加を取り消す場合：

ACTIVE
    ↓
CANCELLED

ParticipantのStatus変更によって、
過去の参加Factを削除しない。

---

# Participant and Rehearsal

Participantは、
Productionへの参加関係を表す。

Rehearsalは、
Productionにおける個別の稽古を表す。

基本構造：

Production
    ↓
Participant

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

ParticipantとRehearsalAttendanceは、
異なるDomainである。

Participantであることは、
Rehearsalへの参加予定を直接意味しない。

Rehearsalの参加対象者は、
Production Participantなどを基準として
設定できる。

---

# Rehearsal Attendance

Rehearsalへの参加予定および実際の出欠は、
RehearsalAttendanceで管理する。

基本構造：

Production
    ↓
Rehearsal
    ↓
RehearsalAttendance
    ↓
Person

RehearsalAttendanceは、
RehearsalのLifecycle全体を通じて保持する。

RehearsalがCONFIRMEDになっても、
参加予定者の情報を別Entityへ移行しない。

RehearsalがACTIVEになっても、
参加予定者の情報を削除しない。

参加予定から実際の出欠への変化は、
RehearsalAttendanceのStatus変更として管理する。

Participantは、
RehearsalAttendanceの作成対象者を
決定するための参照元として利用できる。

---

# Participant and Information Sharing

Participant Typeは、
Productionに関する情報共有の
対象条件として利用できる。

例えば、

Announcement

対象Participant Type
    ↓
CAST

の場合、
そのProductionのCASTを対象として
情報を共有できる。

同様に、

対象Participant Type
    ↓
STAFF

とすることで、
STAFFのみを対象にできる。

---

# Information Sharing and Role

情報共有では、
Participant TypeとRoleを
組み合わせて対象者を指定できる。

例えば、

Announcement

対象Role
    ↓
Rehearsal Manager

対象Participant Type
    ↓
CAST

とした場合、

- Organization / Production Scopeにおける
  Rehearsal Manager
- ProductionのCAST

など、
Business Ruleに従った対象者を取得できる。

RoleとParticipant Typeは、
それぞれ別のFactとして参照する。

Participant Typeそのものを
Authorizationとして使用しない。

---

# Participant and Authorization

Participant Typeそのものは、
Authorizationを付与しない。

例えば、

Participant Type = CAST

であることだけを理由に、

- Production管理
- 会計管理
- Membership管理
- Rehearsal管理

などの権限を付与してはならない。

Productionに対する管理権限が必要な場合は、

- PrimaryManager
- ProductionDelegate

を利用する。

Organization全体の権限が必要な場合は、
MembershipのRoleを利用する。

---

# Participant and History

Participantは、
PersonまたはOrganizationの
Production活動履歴を生成するためのFactとなる。

基本構造：

Participant
    ↓
History

PersonがCASTとして参加した場合：

Participant
    Participant Type = CAST
        ↓
History
    出演履歴

PersonがSTAFFとして参加した場合：

Participant
    Participant Type = STAFF
        ↓
History
    スタッフ履歴

Historyそのものは、
Participant内に保存しない。

History Domainが、
ParticipantなどのFactをもとに管理する。

ParticipantのStatusがCANCELLEDになった場合など、
Historyへ反映する具体的なルールは
History Domainで定義する。

---

# Organization History

SubjectがOrganizationの場合も、
Productionへの参加履歴をHistoryへ利用できる。

例えば、

Organization A
    ↓
Participant
    ↓
Production B
    ↓
Participant Type = STAFF

というFactから、
Organization AのProduction参加履歴を生成できる。

Historyそのものは、
Participant内に保存しない。

---

# Participant and Public Profile

Participantは、
Production Public Pageにおける
出演者・スタッフ表示の基礎情報として利用できる。

公開する場合は、

- Participant Type
- Credit Name
- Credit Order
- Visibility

などを利用する。

Person ProfileまたはOrganization Profileの
内部情報を自動的に公開しない。

---

# Participant and Production Scope

Participantは、
Production ScopeのDomainである。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Participant

Participantへの操作権限は、
Production ScopeのAuthorizationによって判定する。

PrimaryManagerは、
Productionに関する全管理権限を持つ。

ProductionDelegateは、
適用されたRoleに含まれるPermissionに応じて
Participantを管理できる。

---

# Audit

Participantの重要な変更について、
監査情報を保持できる。

基本的な監査情報：

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

参加区分変更、
Status変更、
Visibility変更などについても、
必要に応じて監査情報を保持する。

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
- 基本Participant TypeはCASTとSTAFFとする。
- Participant TypeとRoleを分離する。
- Participant TypeによってAuthorizationを付与しない。
- Production管理権限はPrimaryManagerまたはProductionDelegateで管理する。
- Organization管理権限はMembershipのRoleで管理する。
- OrganizationへのMembershipを持たないPersonもProductionへ参加できる。
- 客演はParticipantとして管理する。
- ParticipantからPersonまたはOrganizationのProfile情報を複製しない。
- Person Profileを人物情報の正本とする。
- Organization Profileを団体情報の正本とする。
- Production上のクレジット表示はParticipantで管理できる。
- Participantの公開・非公開を管理できる。
- Participantは原則として物理削除しない。
- ParticipantはHistory生成のFactとなる。
- Participant Typeは情報共有の対象条件として利用できる。
- Participant TypeそのものをAuthorizationとして利用しない。
- ParticipantはRehearsalそのものを管理しない。
- Rehearsalの参加予定・出欠はRehearsalAttendanceで管理する。
- ParticipantはRehearsalAttendanceの対象者を決定する参照元として利用できる。
- RehearsalのStatus変更によってParticipantを別Entityへ移行しない。
- RehearsalAttendanceはRehearsalのLifecycleを通じて保持する。

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

ParticipantAdded：

Productionへの参加関係が作成されたことを表す。

ParticipantTypeChanged：

Production内での参加区分が変更されたことを表す。

ParticipantActivated：

参加関係が有効になったことを表す。

ParticipantDeactivated：

参加関係が一時的に無効になったことを表す。

ParticipantCancelled：

Productionへの参加が取り消されたことを表す。

ParticipantVisibilityChanged：

Production Public Pageなどにおける
公開状態が変更されたことを表す。

---

# Design Decisions

ParticipantはStageArtにおける
Production参加のFact Domainとして残す。

PersonとProductionを直接関連付けず、
Participantによって参加関係を表現する。

Participantは、

「誰がProductionに参加しているか」

という事実を管理する。

Membershipは、

「PersonがOrganizationに所属している」

という事実を管理する。

ProductionDelegateは、

「PersonがProduction Scopeで
Roleを適用されている」

という関係を管理する。

この3つを混同しない。

Participant Typeは、

「そのProductionにどう関わっているか」

を表す。

Roleは、

「そのScopeで何ができるか」

を表す。

Participant Typeによって
権限を自動付与しない。

Production管理権限は、
PrimaryManagerまたはProductionDelegateで管理する。

初期実装ではParticipant Typeを、

- CAST
- STAFF

に限定する。

細かなスタッフ職種などは、
必要になった時点で拡張する。

Participantは、
出演履歴・スタッフ履歴などの
History生成元となる。

HistoryそのものはParticipantに保存しない。

Participantは、
Productionの公開ページにおける
出演者・スタッフ情報の基礎としても利用する。

Rehearsalへの参加予定および実際の出欠は、
Participantとは別にRehearsalAttendanceで管理する。

Participantは、
RehearsalAttendanceの対象者を
決定するための参照元として利用できる。

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
- 公演ごとのCredit Group
- Participantごとの外部Profile

などへ拡張できる構造とする。

ただし、
初期実装ではParticipant Typeを
CAST / STAFFを中心とした
シンプルな構造とする。

---

# Design Principles

- ParticipantはProductionへの参加Factを表す。
- Participantは権限を表さない。
- ParticipantはProduction Scopeに属する。
- ParticipantのSubjectはPersonまたはOrganizationとする。
- Personは複数Productionへ参加できる。
- OrganizationもProductionへ参加できる。
- Productionごとに独立したParticipantを持つ。
- Participant TypeはProductionへの参加区分を表す。
- CAST / STAFFはParticipant Typeである。
- Participant TypeとRoleを分離する。
- Participant TypeによってAuthorizationを付与しない。
- MembershipはOrganizationへの所属を表す。
- ProductionDelegateはProduction ScopeのRole適用を表す。
- ParticipantとMembershipを分離する。
- ParticipantとProductionDelegateを分離する。
- Participant TypeとAuthorizationを分離する。
- Organization Membershipを持たないPersonもProductionへ参加できる。
- 客演をParticipantとして表現する。
- Person Profileを人物情報の正本とする。
- Organization Profileを団体情報の正本とする。
- ParticipantにProfile情報を重複保存しない。
- Production上のCredit表示はParticipantで管理できる。
- ParticipantのVisibilityを管理できる。
- Participantは原則として物理削除しない。
- ParticipantはHistory生成のFactとなる。
- HistoryはParticipant内に保存しない。
- Participant Typeを情報共有の対象条件として利用できる。
- RehearsalはProductionに所属する。
- RehearsalAttendanceはRehearsalへの参加予定・出欠を管理する。
- ParticipantはRehearsalAttendanceの対象者を決定する参照元として利用できる。
- RehearsalのLifecycle変更によってParticipantを別Entityへ移行しない。
- Blueprintを唯一の設計基準とする。