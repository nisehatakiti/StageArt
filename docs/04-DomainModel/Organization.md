# StageArt Blueprint
# Domain Model : Organization

Version : 1.0

---

# Purpose

Organizationは、舞台芸術活動を行う団体を管理するドメインである。

StageArtにおけるすべての活動はOrganizationを起点として行われる。

Production、Member、Ticket、Budgetなどの情報は、必ずいずれかのOrganizationに所属する。

OrganizationはStageArtのマルチテナントを構成する最も重要なドメインである。

---

# Concept

Organizationは「劇団」を意味しない。

舞台芸術活動を行うあらゆる団体を表現する。

例）

- 劇団
- プロデュース団体
- ダンスカンパニー
- 学生劇団
- 演劇サークル
- 実行委員会

StageArtは団体種別によって機能を分けない。

---

# Identity

OrganizationはOrganizationIDによって一意に識別される。

団体名は識別子ではない。

団体名は変更できる。

同名団体が存在しても問題ない。

---

# Multi Tenant

OrganizationはStageArtにおけるTenantである。

すべてのBusiness DataはOrganizationへ所属する。

異なるOrganization同士でデータを共有してはならない。

Production

Reservation

Budget

Member

Document

すべてOrganization単位で管理される。

---

# Membership

Organizationには複数のPersonが所属できる。

Personは複数Organizationへ所属できる。

所属情報はMembershipによって管理する。

```
Person

↓

Membership

↓

Organization
```

Organization自身は所属情報を保持しない。

---

# Ownership

OrganizationにはOwnerが存在する。

OwnerとはOrganizationを管理できるPersonである。

Owner情報もMembershipのRoleによって管理する。

Organization自身はOwnerIDを保持しない。

---

# Lifecycle

Organizationは以下の状態を持つ。

- Active
- Archived
- Deleted

Deletedは論理削除とする。

過去のProductionやAccountingとの整合性を維持する。

---

# Automatically Generated

Organization作成時、

StageArtは以下を自動生成する。

- Membership（Owner）
- Default Role
- Default Settings
- Document Space

将来的には

- Homepage
- Public Profile

も生成する。

---

# Public Information

以下は公開できる。

- 団体名
- ロゴ
- 紹介文
- Webサイト
- SNS
- 活動地域

以下は公開しない。

- 内部設定
- メンバー権限
- 会計情報
- 管理情報

---

# Design Decisions

OrganizationはBusiness Dataを所有する。

OrganizationはMemberを保持しない。

OrganizationはRoleを保持しない。

OrganizationはProductionを保持しない。

それらは関連ドメインによって管理される。

Organizationは「団体」という事実だけを表現する。

---

# Future

将来的に追加する。

- Organization Logo
- Brand Color
- Public Homepage
- Fan Club
- Goods Store
- Donation
- Sponsor Management

---

# Design Principles

- OrganizationはTenantである。
- OrganizationはBusiness DataのOwnerである。
- Personとの関係はMembershipで表現する。
- 権限はRoleが管理する。
- Organization自身は権限を持たない。
- Organizationは舞台芸術活動を行う団体を表現する。
- 同名団体を許可する。
- Blueprintを唯一の設計基準とする。
