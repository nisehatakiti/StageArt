# StageArt Blueprint

# Social Domain Consistency Policy

Version : 1.0
Status : Confirmed

---

# Purpose

SNS、Follow、Favorite、Membership、External Connectionの責務混在を防止する。

---

# SNS and Credential

PersonSNSおよびOrganizationSNSは公開参照情報のみを扱う。

Credentialを保存してはならない。

---

# Follow and Membership

Followは所属ではない。

Followによって以下を付与してはならない。

- Membership
- Role
- Permission
- Tenant内部情報へのアクセス権

---

# Favorite and Follow

Favoriteは保存、Followは継続的関心である。

Favorite登録によってHome新着配信を発生させない。

Followによって対象を自動的にFavorite登録しない。

Favoriteによって対象を自動的にFollowしない。

---

# Public Information Boundary

Organization管理者がFollow情報を参照する場合でも、Personの非公開情報を開示してはならない。

少なくとも以下はFollow関係からOrganizationへ開示しない。

- Email
- UserAccount情報
- Credential
- 非公開プロフィール項目
- 内部Membership情報
- 非公開活動情報

---

# Future SNS Posting

SNS自動投稿は将来追加可能とする。

その場合も、以下を分離する。

```text
OrganizationSNS
  = 公開プロフィール情報

External Connection / Credential
  = 外部SNSへの認証・接続情報

Posting
  = 投稿実行Business Process
```

同一Entityへ統合してはならない。

---

# Consistency Rules

1. SNS情報とCredentialを混在させない。
2. FollowとMembershipを混在させない。
3. FollowとFavoriteを混在させない。
4. Organization Followによって内部情報へのアクセス権を与えない。
5. Person Public Profileの公開設定をOrganizationが上書きしない。
6. OrganizationSNSの編集権限はOrganization Permissionに従う。
7. PersonSNSの編集権限は本人を基本とする。
