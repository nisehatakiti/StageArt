# StageArt Blueprint

# Domain Model : Organization SNS

Version : 1.0
Status : Confirmed

---

# Purpose

OrganizationSNSは、Organizationの公式外部SNSアカウントを管理するDomainである。

Organization Public Profileにおいて、団体の公式X・Instagram等を公開し、利用者が団体の外部発信へアクセスできるようにする。

---

# Core Structure

```text
Organization
  ↓
OrganizationSNS[]
```

OrganizationSNSはOrganizationの公開情報に属する。

Organization内部のCredentialや外部サービス認証情報とは責務を分離する。

---

# Initial Supported Services

初期UIでは、少なくとも以下を優先表示対象とする。

- X
- Instagram

将来的には、YouTube等の追加サービスを許容する。

---

# Information

OrganizationSNSは少なくとも以下の情報を扱える。

- SNS Service
- Account Identifier
- Profile URL
- Display Name
- Display Order
- Visibility
- Created At
- Updated At

Organization Public Profileでは、公開対象のSNSのみを表示する。

---

# Public Page

Organization Public Pageでは、団体情報の一部として公式SNSを表示できる。

例：

```text
公式SNS

X            @stageart_theatre
Instagram    stageart_theatre
```

タップまたはクリックにより、対応する外部SNSプロフィールへ遷移できる。

Organization Topに配置するSNS情報は、既存のOrganization Public Informationの一部として扱う。

---

# Administration

OrganizationSNSの作成・変更・削除は、Organizationの公開情報を管理できるPermissionを持つPersonに限定する。

MembershipだけではOrganizationSNSの編集権限を自動付与しない。

具体的なPermissionはAuthorization Domainで定義する。

---

# Relationship with External Connection

OrganizationSNSとExternal Connectionは別Conceptである。

OrganizationSNS：

```text
公開プロフィール上のSNS参照情報
```

External Connection：

```text
外部サービスとのシステム連携
```

SNSへの自動投稿等を将来実装する場合は、External Connection / Credentialの安全な認証管理を利用する。

公開プロフィールのOrganizationSNSへCredentialを混在させてはならない。

---

# Security

OrganizationSNSには以下を保存してはならない。

- パスワード
- Access Token
- Refresh Token
- Client Secret
- OAuth Secret
- その他認証秘密情報

Credentialを必要とする将来機能は、Credential DomainおよびExternal Connection Domainで扱う。

---

# Initial Implementation Scope

初期実装では以下を確定する。

1. Organizationは複数の公式SNSを登録できる。
2. XとInstagramを初期UIの優先サービスとする。
3. Organization Public Pageに公開SNSを表示できる。
4. 外部SNSプロフィールへ遷移できる。
5. OrganizationSNSはCredentialを保持しない。
6. 将来のSNS自動投稿機能とは責務を分離する。
