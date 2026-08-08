# StageArt Blueprint
# Domain Model : Account

Version : 1.0

---

# Purpose

AccountはStageArtへログインするための認証情報を管理するドメインである。

Accountは利用者を一意に識別するために存在する。

AccountはPersonでもOrganizationでもない。

認証を担当することのみを責務とする。

---

# Concept

AccountはStageArt利用者の入口である。

利用者はGoogleアカウントまたはメールアドレスによってAccountを作成する。

ログイン後、StageArtはAccountに紐付くPersonおよび所属情報をもとに利用可能な機能を提供する。

---

# Identity

AccountはAccountIDによって一意に識別される。

メールアドレスは識別子ではない。

メールアドレスは変更できる。

Googleアカウントの情報が変更されてもAccountIDは変更されない。

---

# Authentication

Version 1.0では以下を提供する。

- Googleアカウント
- メールアドレス

将来的には以下へ対応する。

- Apple
- LINE
- Passkey
- Multi Factor Authentication

---

# Authorization

Accountは権限を保持しない。

Accountは「誰であるか」を識別するだけである。

利用可能な機能は、

Organizationへの所属、

Membership、

Role

によって決定される。

Account自身は管理者でも観客でも舞台人でもない。

---

# Registration

初回登録時、

StageArtは以下を自動生成する。

- Account
- Person

初期状態ではOrganizationへ所属していない。

利用者はログイン後、

- 劇団を立ち上げる
- 劇団へ参加する
- プロフィールを作る
- 公演を探す

などの目的を自由に選択できる。

---

# Relationship

AccountはPersonと関連する。

```
Account
    │
    └── Person
```

Accountは複数のOrganizationへ所属できる。

その所属関係はMembershipによって管理される。

```
Account
    │
    └── Person
            │
            └── Membership
                    │
                    └── Organization
```

---

# Lifecycle

Accountは以下の状態を持つ。

- Active
- Email Verification Pending
- Locked
- Deleted

DeletedとなったAccountも履歴管理のため論理削除とする。

---

# Design Decisions

Accountは認証情報のみを管理する。

以下の情報は保持しない。

- 氏名
- 芸名
- プロフィール
- 出演履歴
- 劇団情報
- 権限
- 利用者区分

これらはそれぞれ専用ドメインが責任を持つ。

---

# Future

将来的に以下へ対応する。

- 複数ログイン方法の紐付け
- 二段階認証
- パスキー
- API Token
- 外部認証プロバイダ追加

これらの追加によって既存AccountIDは変更されない。

---

# Design Principles

- Accountは認証のみを責務とする。
- Personとは責務を分離する。
- Organizationとは責務を分離する。
- Accountは権限を持たない。
- Accountは利用者区分を持たない。
- AccountはStageArt利用者を一意に識別する。
- 認証方式が増えてもDomain Modelは変更しない。
