# StageArt Blueprint

# Domain Model : Membership

Version : 2.0

---

# Purpose

Membershipは、
PersonとOrganizationの所属関係を表すDomainである。

StageArtでは、
PersonとOrganizationを直接関連付けない。

所属という事実は、
Membershipによって表現する。

---

# Concept

Membershipは、

```text
Person
    ↓
Membership
    ↓
Organization
```

という関係を表す。

Personは複数のOrganizationへ所属できる。

Organizationは複数のPersonを所属させることができる。

同一Personが複数のOrganizationへ所属することもできる。

---

# Identity

MembershipはMembershipIdによって一意に識別する。

PersonIdとOrganizationIdの組み合わせを
Membershipの識別子とはしない。

Membership自身が独立したIdentityを持つ。

これにより、
同一Personの同一Organizationにおける
所属履歴や状態変更を管理できる。

---

# Organization Scope

Membershipは一つのOrganizationに所属する。

```text
Membership
    ├── Person
    └── Organization
```

MembershipはOrganizationをまたいで共有しない。

Personが複数Organizationに所属する場合も、
Organizationごとに別のMembershipを持つ。

---

# Role

Membershipには、
そのOrganizationにおけるPersonのRoleが関連する。

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

```text
Person A

    Membership
        Organization = 劇団A
        Role = Administrator

    Membership
        Organization = 劇団B
        Role = Cast
```

RoleはOrganization Contextにおける権限・役割を表す。

PersonがどのOrganizationを利用しているかによって、
適用されるRoleが変わる。

---

# Organization Context

StageArtでは、
Personが複数のOrganizationに所属できる。

利用者がOrganizationを切り替えた場合、
そのOrganizationに対するMembershipとRoleに基づいて
利用可能な機能・権限を決定する。

例：

```text
Person
    │
    ├── Membership
    │      └── Organization A
    │             └── Role = Administrator
    │
    └── Membership
           └── Organization B
                  └── Role = Cast
```

Organization Aを利用している場合はAdministratorとして扱い、
Organization Bを利用している場合はCastとして扱う。

RoleをPerson自身の属性として保持してはならない。

---

# Membership Status

Membershipは以下の状態を持つ。

- REQUESTED
- INVITED
- ACTIVE
- SUSPENDED
- LEFT
- REJECTED

---

# Requested

PersonがOrganizationへの所属を申請した状態。

```text
Person
    ↓
Membership Request
    ↓
Membership
    ↓
REQUESTED
```

Organization側の権限を持つ利用者が承認すると、
ACTIVEへ遷移する。

---

# Invited

Organization側からPersonへ
所属招待を行った状態。

招待を受けたPersonが承認すると、
ACTIVEへ遷移する。

---

# Active

Organizationへの所属が有効な状態。

ACTIVEのMembershipを持つPersonは、
そのOrganizationのRoleに従って
Organization内の機能を利用できる。

---

# Suspended

一時的にOrganizationの利用・所属を停止している状態。

Membership自体は削除しない。

---

# Left

Organizationから退会・退団した状態。

過去の所属履歴を保持するため、
Membershipを削除しない。

---

# Rejected

所属申請または招待が拒否された状態。

過去の申請・招待の事実を保持するため、
Membershipそのものを物理削除しない。

---

# Membership Request

Personは、
自分が所属したいOrganizationへ
所属申請を行うことができる。

基本的なFlow：

```text
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
```

Organization側から招待する場合は、

```text
Organization
    ↓
Personを招待
    ↓
Membership = INVITED
    ↓
Personが承認
    ↓
Membership = ACTIVE
```

とする。

---

# Approval

REQUESTEDのMembershipをACTIVEへ変更するには、
Organization側の適切な権限を持つPersonによる承認が必要。

承認者は監査情報として記録できるようにする。

---

# Invitation

Organizationは、
PersonをOrganizationへ招待できる。

招待によってMembershipを作成し、
INVITED状態とする。

Personが招待を承認するとACTIVEへ遷移する。

---

# Membership Period

Membershipは所属期間を管理する。

基本情報：

- JoinDate
- LeaveDate

ACTIVEとなった時点をJoinDateとして記録する。

LEFTとなった場合はLeaveDateを記録する。

過去のMembershipを削除することなく、
所属履歴として保持する。

---

# Membership History

Membership自身が所属というFactを表す。

Personから見ると、

- 所属Organization
- 所属期間
- OrganizationごとのRole

を確認できる。

Organizationから見ると、

- 現在のメンバー
- 過去のメンバー
- 所属期間
- Organization内Role

を確認できる。

---

# Authorization

MembershipはOrganization Contextにおける
Authorizationの基礎となる。

ただし、
Membershipそのものがすべての権限ロジックを実装するわけではない。

RoleおよびDelegateRoleによって、
具体的な権限を決定する。

```text
Person
    ↓
Membership
    ↓
Organization
    ↓
Role
    ↓
Authorization
```

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

```text
REQUESTED
    ↓
ACTIVE
    ↓
SUSPENDED
    ↓
ACTIVE
    ↓
LEFT
```

または、

```text
INVITED
    ↓
ACTIVE
```

申請が拒否された場合：

```text
REQUESTED
    ↓
REJECTED
```

招待が拒否された場合：

```text
INVITED
    ↓
REJECTED
```

Membershipは原則として物理削除しない。

---

# Business Rules

- PersonとOrganizationは直接関連付けない。
- 所属関係はMembershipで表現する。
- 一人のPersonは複数Organizationへ所属できる。
- Organizationは複数Personを所属させることができる。
- OrganizationごとにMembershipを持つ。
- OrganizationごとにRoleを持つ。
- PersonがOrganizationを切り替えると、そのOrganizationのRoleが適用される。
- PersonからOrganizationへの所属申請を許可する。
- OrganizationからPersonへの招待を許可する。
- 所属申請はOrganization側の承認によってACTIVEになる。
- 招待はPerson側の承認によってACTIVEになる。
- Membershipの履歴は削除しない。
- Membershipの所属期間を管理する。
- Membershipの詳細な権限はRole / DelegateRoleによって決定する。
- Production単位の権限はProductionDelegateで管理する。
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

# Design Decisions

Membershipは、
PersonとOrganizationの所属というFactを表す。

Person自身にOrganization Roleを保持させない。

RoleはOrganization Contextに属する。

同じPersonでも、
Organizationによって異なるRoleを持つ。

Organization Contextを切り替えることで、
適用されるMembership / Roleが変わる。

Personからの所属申請を許可する。

Organizationからの招待を許可する。

所属申請と招待は異なるLifecycleとして扱う。

Membershipは過去の所属履歴を保持するため、
原則として削除しない。

Companionなどの観客同行者管理とは関係しない。

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

などへ拡張できる構造とする。

---

# Design Principles

- MembershipはPersonとOrganizationの所属関係を表す。
- PersonとOrganizationを直接関連付けない。
- Personは複数Organizationへ所属できる。
- OrganizationごとにMembershipを持つ。
- OrganizationごとにRoleを持つ。
- RoleはPersonの属性ではない。
- Organization Contextによって適用されるRoleが変わる。
- Personからの所属申請を許可する。
- Organizationからの招待を許可する。
- 所属申請はOrganization側の承認を必要とする。
- 招待はPerson側の承認を必要とする。
- Membership履歴は削除しない。
- Membershipは所属というFactを表現する。
- 詳細なAuthorizationはRole / DelegateRoleで管理する。
- Production単位の権限はProductionDelegateで管理する。
- Membershipの内部情報を公開しない。
- Blueprintを唯一の設計基準とする。
