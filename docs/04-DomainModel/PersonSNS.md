# StageArt Blueprint

# Domain Model : Person SNS

Version : 1.0
Status : Confirmed

---

# Purpose

Person SNSは、StageArt上のPersonが自分自身の外部SNSアカウントをプロフィール情報として登録・公開するためのDomainである。

StageArtは舞台芸術の実務関係者だけでなく、一般の観客も利用する。

したがって、SNSは役者・スタッフ・団体管理者などの属性に限定せず、すべてのPersonが任意で登録できるプロフィール情報として扱う。

---

# Position

基本構造：

```text
Person
  ↓
Profile
  ↓
PersonSNS[]
```

PersonSNSはAuthenticationやCredentialとは別Conceptである。

PersonSNSは、外部SNSへの公開参照情報を扱う。

---

# Initial Supported Services

初期UIでは、少なくとも以下を優先表示対象とする。

- X
- Instagram

将来的には以下を含む他サービスへ拡張できる。

- Facebook
- YouTube
- TikTok
- その他SNS

Domain構造は、サービス追加のためにPerson DomainやProfile Domainの再設計を必要としない形とする。

---

# Information

PersonSNSは少なくとも以下の情報を扱える。

- SNS Service
- Account Identifier
- Profile URL
- Display Order
- Visibility
- Created At
- Updated At

Account Identifierの例：

```text
X
@stageart_user

Instagram
stageart_user
```

Profile URLは、公開プロフィールから外部SNSへ遷移するために利用できる。

ServiceとAccount IdentifierからURLを解決できる場合でも、URL解決規則を各UIに重複実装しない。

---

# Public Profile

Person Public Profileでは、Person本人が公開対象として設定したSNSのみを表示する。

XおよびInstagramは、初期UIで専用の表示枠またはアイコン付きリンクとして扱うことができる。

表示例：

```text
SNS

X            @stageart_user
Instagram    stageart_user
```

タップまたはクリックにより、対応する外部SNSプロフィールへ遷移できる。

---

# Privacy

PersonSNSは公開プロフィール情報であり、Credentialを保持してはならない。

以下をPersonSNSへ保存してはならない。

- パスワード
- Access Token
- Refresh Token
- Client Secret
- OAuth認証情報
- その他外部SNSの認証秘密情報

SNSへのログイン連携や投稿機能を将来追加する場合でも、公開プロフィール情報としてのPersonSNSと、認証・Credential情報を同一Entityとして扱わない。

---

# Relationship with Follow

PersonSNSを登録しても、Follow関係は発生しない。

Person Public Profileの閲覧者が、そのPersonをFollowできる機能を将来追加する場合でも、SNSリンクとStageArt内Followは別Conceptとする。

---

# Initial Implementation Scope

初期実装では以下を確定する。

1. すべてのPersonは任意でSNSを登録できる。
2. XとInstagramを初期UIの優先サービスとする。
3. Person Public Profileに公開SNSを表示できる。
4. Account IdentifierまたはProfile URLから外部SNSプロフィールへ遷移できる。
5. SNS登録は任意とする。
6. Credentialやパスワードを保存しない。
7. Service追加に対応できる拡張可能な構造とする。
