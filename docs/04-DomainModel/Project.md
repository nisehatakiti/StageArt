# StageArt Blueprint
# Domain Model : Project

Version : 1.0

---

# Purpose

Projectは公演制作全体を管理するInternal Domainである。

利用者はProjectを直接操作しない。

StageArtは「公演を作る」という操作を受けると、自動的にProjectを生成する。

ProjectはProductionを中心として、公演制作に必要な情報を管理するためのコンテナとなる。

---

# Concept

Projectは公開される公演ではない。

Projectは制作活動そのものを表す。

Productionは観客へ公開される成果物であり、

ProjectはProductionを制作するための内部管理領域である。

---

# Identity

ProjectはProjectIDによって一意に識別される。

Project名は識別子ではない。

利用者はProjectIDを意識しない。

---

# Relationship

Projectは必ず一つのOrganizationへ所属する。

```
Organization
    │
    └── Project
            │
            └── Production
```

ProjectはProductionを一つ保持する。

将来的には以下の情報もProjectへ関連付く。

- Rehearsal
- Task
- Budget
- Finance
- Document
- Schedule

---

# Automatically Generated

利用者が

「公演を作る」

を実行すると、

StageArtは自動的に

- Project
- Production

を生成する。

将来的には以下も自動生成する。

- Rehearsal Workspace
- Document Workspace
- Budget Workspace
- Checklist

---

# Lifecycle

Projectは以下の状態を持つ。

- Draft
- Active
- Closed
- Archived

Projectは削除しない。

終了後も制作履歴として保持する。

---

# Visibility

Projectは利用者へ公開しない。

管理画面でもProjectという名称を表示しない。

利用者は

「公演」

だけを操作する。

---

# Design Decisions

ProjectはInternal Domainである。

ProjectはBusiness Logicを集約するための単位であり、

公開情報を保持しない。

Productionとは責務を分離する。

Projectは制作、

Productionは公開、

という役割を持つ。

---

# Future

将来的に以下を追加する。

- 稽古管理
- タスク管理
- ドキュメント管理
- 助成金管理
- 契約書管理
- 予算管理
- 収支管理
- 制作カレンダー

これらはすべてProjectへ関連付く。

---

# Design Principles

- ProjectはInternal Domainである。
- 利用者はProjectを意識しない。
- StageArtが自動生成する。
- Productionとは責務を分離する。
- Projectは制作全体を管理する。
- Projectは履歴として永続的に保持する。
