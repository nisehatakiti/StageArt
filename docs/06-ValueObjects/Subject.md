# StageArt Blueprint
# Value Object : Subject

Version : 1.0

---

# Purpose

SubjectはStageArtにおける活動主体（Business Subject）を表すValue Objectである。

SubjectはBusiness Resourceを参照するための共通Referenceとして利用する。

Participant、Historyなど、
活動主体を扱うDomainはSubjectを利用して対象を参照する。

---

# Concept

StageArtでは活動主体をPersonまたはOrganizationとして管理する。

DomainはPersonやOrganizationを直接参照するのではなく、
Subjectを介して参照する。

```
Participant
        │
        ▼
     Subject
        │
        ├── Person
        └── Organization
```

SubjectはBusiness Ruleを持たない。

活動主体を識別するためのValue Objectである。

---

# Structure

Subjectは以下の情報で構成される。

- SubjectType
- SubjectId

例）

```
Subject

Type : PERSON

Id : person-001
```

```
Subject

Type : ORGANIZATION

Id : organization-001
```

---

# Subject Type

Version 1.0では以下をサポートする。

| Type | Description |
|-------|-------------|
| PERSON | 人物 |
| ORGANIZATION | 団体 |

将来的に必要となる場合は追加できる。

---

# Usage

Subjectは以下のDomainで利用する。

- Participant
- History

将来的に以下でも利用できる。

- Notification
- Favorite
- Follow
- Ownership
- Permission

---

# Equality

Subjectは以下が一致した場合に同一とみなす。

- SubjectType
- SubjectId

どちらか一方でも異なる場合は別Subjectとする。

---

# Immutability

SubjectはImmutableである。

生成後にSubjectTypeおよびSubjectIdを変更してはならない。

変更が必要な場合は新しいSubjectを生成する。

---

# Design Principles

- Subjectは活動主体を表すBusiness Conceptである。
- SubjectはBusiness Resourceを参照する共通Referenceである。
- SubjectはBusiness Ruleを持たない。
- SubjectはImmutableである。
- SubjectはPersonまたはOrganizationを参照する。
- DomainはPersonやOrganizationを直接参照せず、Subjectを利用する。
- SubjectはStageArt共通のユビキタス言語である。
