# StageArt Blueprint

# 03 - Social Profile and Follow Policy

Version : 1.0
Status : Confirmed

---

# Purpose

StageArtにおけるSNS、Follow、Favoriteの役割を明確に分離する。

StageArtは舞台芸術の活動管理だけでなく、人・団体・公演を発見し、継続的に関心を持つためのプラットフォームでもある。

---

# Three Different Relationships

## 1. SNS

外部SNSへの参照情報である。

```text
Person / Organization
  ↓
SNS Profile
  ↓
External Service
```

目的：

- 外部プロフィールを見る
- 外部発信を見る
- 外部SNSへ移動する

SNS登録自体はStageArt内の関係を発生させない。

---

## 2. Follow

StageArt内で継続的な関心を持つ関係である。

```text
Person
  ↓
Follow
  ↓
Organization / 将来Production等
```

目的：

- 新しい公開情報を受け取る
- Homeで新着を見る
- 継続的に活動を追う

初期実装の主対象はOrganization Followとする。

Organizationが新しいProductionや公演情報を公開した場合、FollowしているPersonのHomeへ表示できる。

---

## 3. Favorite

Person自身が後で見返すための保存である。

目的：

- 気になる対象を保存する
- 自分用一覧から見返す

Favoriteだけでは継続的なHome新着配信を発生させない。

---

# Comparison

| Concept | Meaning | Home新着 | 外部遷移 |
|---|---|---:|---:|
| SNS | 外部アカウント参照 | No | Yes |
| Follow | StageArt内の継続的関心 | Yes | Optional |
| Favorite | 自分用保存 | No | No |

---

# Organization Followers

Organization管理者は、団体のFollow状況を確認できる構造を持つ。

初期方針は以下とする。

- Follow数はOrganization側で確認できる。
- FollowしているPersonの情報を管理画面で確認できる構造を許容する。
- ただし、公開プロフィール情報のみを表示対象とする。
- Email、認証情報、非公開プロフィール情報、内部活動情報をOrganizationへ開示してはならない。
- 一般公開ページでフォロワー一覧を公開することは初期必須要件としない。

フォロワーの可視性とPrivacyの詳細は、Person Public Profileの公開設定およびFollow DomainのPrivacy Ruleに従う。

---

# Person SNS Experience

すべてのPersonは、任意でSNSをプロフィールに登録できる。

初期UIではXとInstagramを優先表示する。

SNS情報が未登録の場合、空欄や「未登録」を公開プロフィール上で強調表示する必要はない。

---

# Organization SNS Experience

Organizationは公式SNSを登録できる。

初期UIではXとInstagramを優先表示する。

Organization Public Pageでは、公開対象の公式SNSを表示できる。

---

# Security Principle

PersonSNSおよびOrganizationSNSは公開参照情報であり、Credentialではない。

パスワード、Access Token、Refresh Token等を保存してはならない。

将来のSNS投稿連携は、External ConnectionおよびCredential Domainで安全に扱う。

---

# Confirmed Decisions

1. Personは任意でSNSをプロフィールに登録できる。
2. Organizationは公式SNSを登録できる。
3. 初期UIではXとInstagramを優先する。
4. SNSは公開参照情報でありCredentialを保存しない。
5. Followは継続的な新着情報を受け取るStageArt内の関係である。
6. Favoriteは自分用保存でありFollowとは別Conceptである。
7. FollowしているOrganizationの新しい公開Production / 公演情報はHomeへ表示できる。
8. Organization管理者はFollow数を確認できる。
9. FollowしているPersonの詳細をOrganizationへ無制限に開示しない。
10. 一般公開のフォロワー一覧は初期必須要件としない。
