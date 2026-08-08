# StageArt Blueprint
# Domain Model : Membership

Version : 1.0

---

# Purpose

MembershipはPersonとOrganizationの所属関係を管理するドメインである。

StageArtではPersonとOrganizationを直接関連付けない。

所属という事実はすべてMembershipによって表現する。

---

# Concept

Membershipは

Person

↓

Organization

の関係を表す。

Personは複数Organizationへ所属できる。

Organizationは複数Personを持つことができる。

---

# Identity

MembershipはMembershipIDによって一意に識別する。

PersonIDとOrganizationIDは識別子ではない。

同一Personが同一Organizationへ複数回所属する場合もあるため、

Membership単位で履歴を管理する。

---

# Role

MembershipはRoleを保持する。

例）

- Owner
- Administrator
- Manager
- Member

Roleは権限を表す。

---

# Status

Membershipは以下の状態を持つ。

- Invited
- Active
- Suspended
- Left

退団した場合はLeftとなる。

削除は行わない。

---

# Period

Membershipは所属期間を保持する。

- JoinDate
- LeaveDate

LeaveDateが設定されることで、

過去の所属履歴を保持できる。

---

# Invitation

OrganizationはPersonを招待できる。

招待時、

MembershipはInvited状態となる。

本人が承認するとActiveとなる。

---

# Ownership

OrganizationのOwnerもMembershipとして管理する。

OwnerはOrganization固有の情報ではない。

Roleによって表現する。

---

# History

Membershipは所属履歴を保持する。

Personから見ると

- 所属劇団履歴

Organizationから見ると

- 所属メンバー履歴

として利用される。

---

# Design Decisions

MembershipはPerson情報を保持しない。

MembershipはOrganization情報を保持しない。

Membershipは所属という事実のみを表現する。

---

# Future

将来的に以下へ対応する。

- 招待メール
- 招待URL
- 権限変更履歴
- 一時休団
- 復団

---

# Design Principles

- PersonとOrganizationは直接関連付けない。
- 所属はMembershipで表現する。
- 権限はMembershipが保持する。
- 所属履歴は削除しない。
- Membershipは所属という事実のみを管理する。
