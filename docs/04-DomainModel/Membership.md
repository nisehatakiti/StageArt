# StageArt Blueprint

# Domain Model : Membership

Version : 2.1

---

# Purpose

Membershipは、
PersonとOrganizationの所属関係を表すDomainである。

StageArtでは、
PersonとOrganizationを直接関連付けない。

所属という事実は、
Membershipによって表現する。

Membershipは、
PersonがOrganizationに所属しているという
関係そのものを表す。

---

# Concept

Membershipは、

Person
    ↓
Membership
    ↓
Organization

という関係を表す。

Personは複数のOrganizationへ所属できる。

Organizationは複数のPersonを所属させることができる。

同一Personが複数のOrganizationへ所属することもできる。

Membershipは、
PersonとOrganizationの組み合わせによって
単純に表現されるものではなく、
独立したIdentityを持つ所属関係として扱う。

---

# Identity

MembershipはMembershipIdによって一意に識別する。

PersonIdとOrganizationIdの組み合わせを
MembershipのIdentityとはしない。

Membership自身が独立したIdentityを持つ。

MembershipIdによって、
一つの所属関係を継続的に識別できる。

MembershipのStatusやRoleが変更された場合でも、
MembershipIdは変更しない。

---

# Organization Scope

Membershipは一つのOrganizationに所属する。

Membership

    ├── Person
    └── Organization

MembershipはOrganizationをまたいで共有しない。

Personが複数Organizationに所属する場合も、
Organizationごとに別のMembershipを持つ。

例えば、

Person A
    ↓
Membership A
    ↓
Organization A

Person A
    ↓
Membership B
    ↓
Organization B

となる。

---

# Role

Membershipには、
そのOrganizationにおけるPersonのRoleが関連する。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

Person A

    Membership
        Organization = 劇団A
        Role = Administrator

    Membership
        Organization = 劇団B
        Role = Cast

RoleはOrganization Contextにおける
権限・役割を表す。

PersonがどのOrganizationを利用しているかによって、
適用されるRoleが変わる。

RoleをPerson自身の属性として保持してはならない。

Roleの定義および具体的なPermissionは、
Role Domainで管理する。

---

# Organization Context

StageArtでは、
Personが複数のOrganizationに所属できる。

利用者がOrganizationを切り替えた場合、
そのOrganizationに対するMembershipとRoleに基づいて
利用可能な機能・権限を決定する。

例：

Person
    │
    ├── Membership
    │      └── Organization A
    │             └── Role = Administrator
    │
    └── Membership
           └── Organization B
                  └── Role = Cast

Organization Aを利用している場合はAdministratorとして扱い、
Organization Bを利用している場合はCastとして扱う。

RoleはPerson自身に付与されるのではなく、
Organization ContextにおけるMembershipを通じて適用される。

---

# Membership Status

Membershipは以下の状態を持つ。

- REQUESTED
- INVITED
- ACTIVE
- SUSPENDED
- LEFT
- REJECTED

Statusは、
MembershipのLifecycleを表す。

MembershipのStatusが変更されても、
MembershipIdは変更しない。

---

# Requested

REQUESTEDは、
PersonがOrganizationへの所属を申請した状態。

基本構造：

Person
    ↓
Membership Request
    ↓
Membership
    ↓
REQUESTED

Organization側の適切な権限を持つPersonが承認すると、
ACTIVEへ遷移する。

---

# Invited

INVITEDは、
Organization側からPersonへ
所属招待を行った状態。

基本構造：

Organization
    ↓
Personを招待
    ↓
Membership
    ↓
INVITED

招待を受けたPersonが承認すると、
ACTIVEへ遷移する。

---

# Active

ACTIVEは、
Organizationへの所属が有効な状態。

ACTIVEのMembershipを持つPersonは、
そのOrganizationのRoleに従って
Organization内の機能を利用できる。

---

# Suspended

SUSPENDEDは、
一時的にOrganizationの利用・所属を停止している状態。

Membership自体は削除しない。

必要に応じて、
ACTIVEへ復帰できる。

---

# Left

LEFTは、
Organizationから退会・退団した状態。

過去の所属関係を保持するため、
Membershipを削除しない。

LEFTとなったMembershipには、
LeaveDateを記録する。

---

# Rejected

REJECTEDは、
所属申請または招待が拒否された状態。

過去の申請・招待の事実を保持するため、
Membershipそのものを物理削除しない。

---

# Membership Request

Personは、
自分が所属したいOrganizationへ
所属申請を行うことができる。

基本的なFlow：

Person
    ↓
Organizationを選択
    ↓
所属申請
    ↓
Membership = REQUESTED
    ↓
Organization管理者が確認
    ↓
承認
    ↓
Membership = ACTIVE

Organization側から招待する場合は、

Organization
    ↓
Personを招待
    ↓
Membership = INVITED
    ↓
Personが承認
    ↓
Membership = ACTIVE

とする。

所属申請と招待は、
異なるLifecycleとして扱う。

---

# Approval

REQUESTEDのMembershipをACTIVEへ変更するには、
Organization側の適切な権限を持つPersonによる承認が必要。

承認者は、
監査情報として記録できるようにする。

承認によってMembershipのStatusを変更するが、
MembershipIdは変更しない。

---

# Invitation

Organizationは、
PersonをOrganizationへ招待できる。

招待によってMembershipを作成し、
INVITED状態とする。

Personが招待を承認すると、
ACTIVEへ遷移する。

招待を拒否した場合は、
REJECTEDへ遷移する。

---

# Membership Period

Membershipは、
その所属関係の期間を管理する。

基本情報：

- JoinDate
- LeaveDate

ACTIVEとなった時点をJoinDateとして記録する。

LEFTとなった場合はLeaveDateを記録する。

MembershipのStatusと所属期間は関連する。

例えば、

REQUESTED
    ↓
ACTIVE
    ↓
LEFT

の場合、

ACTIVEとなった時点
    ↓
JoinDate

LEFTとなった時点
    ↓
LeaveDate

となる。

過去のMembershipを削除することなく、
所属関係として保持する。

---

# Membership History

Membership自身が、
PersonとOrganizationの所属というFactを表す。

Personから見ると、

- 所属Organization
- 所属期間
- OrganizationごとのRole
- Membership Status

を確認できる。

Organizationから見ると、

- 現在のメンバー
- 過去のメンバー
- 所属期間
- Organization内Role
- Membership Status

を確認できる。

Role変更やStatus変更が発生した場合でも、
Membership自身のIdentityは維持する。

---

# Role Change

RoleはMembershipに関連付けられる。

Role変更は、
Membershipに対する変更として扱う。

例えば、

Membership
    Organization = 劇団A
    Role = Rehearsal Manager

から、

Membership
    Organization = 劇団A
    Role = Accounting Manager

へ変更する。

MembershipIdは変更しない。

Role変更に関する監査情報は、
必要に応じて保持する。

Roleそのものの定義やPermissionは、
Role Domainで管理する。

---

# Multiple Roles

一人のPersonに複数のRoleを付与できる。

例えば、

Person A

    Membership
        Organization = 劇団A
        Role =
            Administrator
            Rehearsal Manager
            Accounting Manager

という構成を許可できる。

ただし、
Administratorを持つ場合は
Organizationの全権限を持つため、
他のRoleを追加する必要はない。

具体的なRoleの組み合わせや
PermissionについてはRole Domainで管理する。

---

# Authorization

Membershipは、
Organization Contextにおける
Authorizationの基礎となる。

ただし、
Membershipそのものが
すべての権限ロジックを実装するわけではない。

RoleおよびDelegateRoleによって、
具体的な権限を決定する。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Authorization

Production単位の権限については、
ProductionDelegateを利用する。

---

# Ownership

OrganizationのOwnerもMembershipとして管理する。

OwnerはPerson自身の属性ではない。

Organizationに対するMembershipのRoleによって、
Ownerとしての権限を表現する。

Ownerが変更された場合も、
Membership / Roleの変更として管理できる。

---

# Production Delegate

Production単位の権限は、
Membership / Organization Roleとは別に管理する。

基本構造：

Production
    ↓
ProductionDelegate
    ↓
Production Management Permission

ProductionDelegateは、
Organization全体のRoleを変更しない。

例えば、
OrganizationのMembershipを持たないPersonへ
Production単位の権限を委任することもできる。

具体的なProduction単位の権限については、
ProductionDelegate Domainで管理する。

---

# Public Information

Membershipそのものは内部情報である。

Membershipの詳細情報や権限情報を
一般公開してはならない。

Organization Public Profileに
メンバーを掲載する場合は、
公開対象として選択されたPersonのみを表示する。

公開情報として、

- Personの公開Profile
- Organization内での公開Role
- その他公開対象情報

などを表示できる。

ただし、
内部権限や管理情報を公開しない。

---

# Lifecycle

MembershipのLifecycle：

REQUESTED
    ↓
ACTIVE
    ↓
SUSPENDED
    ↓
ACTIVE
    ↓
LEFT

または、

INVITED
    ↓
ACTIVE

申請が拒否された場合：

REQUESTED
    ↓
REJECTED

招待が拒否された場合：

INVITED
    ↓
REJECTED

Membershipは原則として物理削除しない。

Statusの変更によって、
MembershipのLifecycleを管理する。

---

# Membership and Organization

Membershipは、
Organizationのメンバー一覧を構成する。

Organizationから見た場合、

Organization
    ↓
Membership[]
    ↓
Person

という構造になる。

Organizationは、
Personを直接所有・管理するのではなく、
Membershipを通じて所属関係を管理する。

---

# Membership and Person

Personから見た場合、

Person
    ↓
Membership[]
    ↓
Organization

という構造になる。

Personは、
複数OrganizationのMembershipを持つことができる。

Organizationごとに、
別のRoleとMembership Statusを持つことができる。

---

# Membership and Participant

MembershipとParticipantは、
異なるDomainである。

Membership：

PersonがOrganizationに所属している事実。

Participant：

PersonまたはOrganizationが
Productionへ参加している事実。

基本構造：

Person
    ↓
Membership
    ↓
Organization

Organization
    ↓
Production
    ↓
Participant

Membershipが存在することだけで、
ProductionへのParticipantになるわけではない。

Participant Typeは、
Productionにおける参加区分を表す。

---

# Membership and Role

Membershipは、
Organization ContextにおけるRoleの適用対象となる。

基本構造：

Person
    ↓
Membership
    ↓
Organization
    ↓
Role

RoleはPersonの属性ではない。

同一Personでも、
Organizationごとに異なるRoleを持つことができる。

Roleの具体的な定義はRole Domainで管理する。

---

# Membership and Rehearsal

Membershipは、
Rehearsalへの参加そのものを表さない。

Rehearsalへの参加対象者は、
ProductionのParticipantなどから決定する。

基本構造：

Organization
    ↓
Production
    ↓
Participant
    ↓
Rehearsal
    ↓
Rehearsal Attendance

MembershipがACTIVEだからといって、
そのPersonがProductionやRehearsalへ
自動的に参加するわけではない。

---

# Membership and Timetable

Membershipは、
Timetableの対象者を直接決定しない。

Timetable Itemの対象者は、
Production Participantや
Participant Typeなどによって決定する。

基本構造：

Membership
    ↓
Organization

Production
    ↓
Participant
    ↓
Timetable

Organizationに所属していることだけで、
Timetableの対象者になるわけではない。

---

# Membership and Accounting

Membershipは、
Accountingの仕訳や会計処理を直接管理しない。

Organizationにおける
Accounting ManagerなどのRoleによって、
会計機能へのアクセス権を決定する。

基本構造：

Person
    ↓
Membership
    ↓
Role
    ↓
Accounting Permission

Membership Domainは、
Journal EntryやAccountなどの
Accounting情報を管理しない。

---

# Audit

Membershipには、
重要な状態変更を追跡できるよう
監査情報を保持する。

例えば、

- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt
- ApprovedBy
- ApprovedAt
- InvitedBy
- InvitedAt
- SuspendedBy
- SuspendedAt
- LeftAt

など。

具体的なAudit構造は、
共通Audit設計に従う。

---

# Business Rules

- PersonとOrganizationは直接関連付けない。
- 所属関係はMembershipで表現する。
- MembershipはPersonとOrganizationの所属というFactを表す。
- Membershipは独立したIdentityを持つ。
- MembershipIdはStatusやRoleが変更されても変わらない。
- 一人のPersonは複数Organizationへ所属できる。
- Organizationは複数Personを所属させることができる。
- OrganizationごとにMembershipを持つ。
- OrganizationごとにRoleを持つ。
- RoleはPersonの属性ではない。
- Organization Contextによって適用されるRoleが変わる。
- Personからの所属申請を許可する。
- OrganizationからPersonへの招待を許可する。
- 所属申請と招待は異なるLifecycleとして扱う。
- 所属申請はOrganization側の承認を必要とする。
- 招待はPerson側の承認を必要とする。
- MembershipはREQUESTED / INVITED / ACTIVE / SUSPENDED / LEFT / REJECTEDを持つ。
- Membershipは原則として物理削除しない。
- Membershipの所属期間を管理する。
- ACTIVEとなった時点をJoinDateとする。
- LEFTとなった時点をLeaveDateとする。
- RoleはMembershipに関連付ける。
- Role変更はMembershipに対する変更として扱う。
- Role変更によってMembershipIdを変更しない。
- 詳細なAuthorizationはRole / DelegateRoleによって決定する。
- Production単位の権限はProductionDelegateで管理する。
- Membershipが存在するだけではParticipantにはならない。
- Membershipが存在するだけではRehearsalへの参加者にはならない。
- Membershipが存在するだけではTimetableの対象者にはならない。
- MembershipはAccountingの仕訳や会計処理を管理しない。
- Membershipの内部情報を一般公開しない。
- Public Profileに表示するメンバーは公開対象として選択されたPersonに限定する。

---

# Domain Events

Membershipに関する主なDomain Event：

- MembershipRequested
- MembershipInvited
- MembershipApproved
- MembershipRejected
- MembershipSuspended
- MembershipReactivated
- MembershipLeft
- MembershipRoleChanged

Domain Eventには、
認証情報などのSecret情報を含めない。

---

# Event Meaning

MembershipRequested

PersonがOrganizationへの所属を申請したことを表す。

MembershipInvited

OrganizationがPersonをOrganizationへ招待したことを表す。

MembershipApproved

Membershipが承認され、
ACTIVEとなったことを表す。

MembershipRejected

所属申請または招待が拒否されたことを表す。

MembershipSuspended

Membershipが一時停止されたことを表す。

MembershipReactivated

SUSPENDEDのMembershipが
再びACTIVEとなったことを表す。

MembershipLeft

PersonがOrganizationから
退会・退団したことを表す。

MembershipRoleChanged

Membershipに関連付けられたRoleが
変更されたことを表す。

---

# Design Decisions

Membershipは、
PersonとOrganizationの所属というFactを表す。

Membershipは、
PersonとOrganizationの単純な関連テーブルではなく、
独立したIdentityを持つDomain Entityとして扱う。

MembershipIdによって、
一つの所属関係を継続的に識別する。

StatusやRoleが変更されても、
MembershipIdは変更しない。

RoleはMembershipに関連付ける。

RoleはPerson自身の属性ではなく、
Organization Contextにおける権限・役割である。

同じPersonでも、
Organizationによって異なるRoleを持つことができる。

Membership Statusによって、
所属関係のLifecycleを管理する。

所属申請とOrganizationからの招待は、
異なるLifecycleとして扱う。

Membershipは過去の所属関係を保持するため、
原則として物理削除しない。

MembershipとParticipantを分離する。

MembershipはOrganizationへの所属を表し、
ParticipantはProductionへの参加を表す。

Membershipが存在することだけで、
ProductionへのParticipantにはならない。

Membershipが存在することだけで、
RehearsalやTimetableの対象者にはならない。

Organization単位の権限はRoleによって管理する。

Production単位の権限はProductionDelegateによって管理する。

Membership Domainは、
具体的なPermissionや
他DomainのBusiness Ruleを管理しない。

---

# Future

将来的に、

- 招待メール
- 招待URL
- 招待期限
- 所属申請メッセージ
- 承認コメント
- Role変更履歴
- 一時休団
- 復団
- Membership単位の監査履歴
- Organization独自のMembership Rule

などへ拡張できる構造とする。

ただし、
Membershipの基本責務は、

Person
    ↓
Membership
    ↓
Organization

という所属関係の管理に限定する。

---

# Design Principles

- MembershipはPersonとOrganizationの所属関係を表す。
- PersonとOrganizationを直接関連付けない。
- Membershipは独立したIdentityを持つ。
- MembershipIdは所属関係のLifecycleを通じて維持する。
- Personは複数Organizationへ所属できる。
- OrganizationごとにMembershipを持つ。
- OrganizationごとにRoleを持つ。
- RoleはPersonの属性ではない。
- RoleはMembershipを通じてOrganization Contextに適用される。
- Organization Contextによって適用されるRoleが変わる。
- Membership Statusによって所属Lifecycleを管理する。
- Personからの所属申請を許可する。
- Organizationからの招待を許可する。
- 所属申請と招待を別のLifecycleとして扱う。
- 所属申請はOrganization側の承認を必要とする。
- 招待はPerson側の承認を必要とする。
- Membership履歴は削除しない。
- Membershipの所属期間を管理する。
- Role変更はMembershipに対する変更として扱う。
- RoleとParticipant Typeを分離する。
- MembershipとParticipantを分離する。
- Membershipが存在するだけではParticipantにはならない。
- Membershipが存在するだけではRehearsal参加者にはならない。
- Membershipが存在するだけではTimetable対象者にはならない。
- Organization単位の権限はRoleで管理する。
- Production単位の権限はProductionDelegateで管理する。
- Membership Domainは具体的なPermissionを定義しない。
- Membershipの内部情報を公開しない。
- Blueprintを唯一の設計基準とする。