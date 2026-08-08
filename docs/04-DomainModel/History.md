# StageArt Blueprint
# Domain Model : History

Version : 2.0

---

# Purpose

HistoryはSubjectの活動履歴を表すDomainである。

HistoryはParticipantおよびReservationから自動生成される。

利用者が直接作成・更新するDomainではない。

HistoryはSubjectがStageArt上で行った活動を時系列に記録する。

---

# Concept

HistoryはSubjectとProductionまたはPerformanceとの関係を表す。

```
Participant
        │
        ├─────────────┐
        ▼             │
     Subject          │
                      │
Reservation           │
        │             │
        └─────────────┘
              │
              ▼
           History
```

HistoryはSubjectを中心として活動履歴を保持する。

---

# Responsibility

Historyは以下を管理する。

- Subject
- HistoryType
- Production
- Performance
- EventDateTime

Historyは活動履歴のみを保持する。

Business Ruleは保持しない。

---

# Identity

HistoryはHistoryIdによって識別する。

HistoryIdは変更できない。

---

# Subject

HistoryはSubjectを保持する。

Subjectは以下で構成される。

- SubjectType
- SubjectId

Subjectは以下を参照できる。

- Person
- Organization

---

# History Type

HistoryTypeは活動種別を表す。

Version 1.0では以下をサポートする。

- PARTICIPATION
- AUDIENCE

将来的に追加可能である。

例）

- STAFF
- SPONSOR
- AWARD

---

# Production

HistoryはProductionを参照できる。

Productionは活動の対象を表す。

---

# Performance

HistoryはPerformanceを参照できる。

Performanceを持たないHistoryも許可する。

例）

出演実績

↓

Productionのみ保持

観劇履歴

↓

Production

+

Performance

---

# Event Date Time

EventDateTimeは活動日時を表す。

出演実績は公開日または初回公演日を利用する。

観劇履歴はReservationCheckedIn日時を利用する。

---

# Generation

Historyは以下のDomain Eventによって生成・更新される。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

- ReservationCreated
- ReservationCheckedIn
- ReservationCancelled

利用者がHistoryを直接作成することはできない。

---

# Access

Historyは読み取り専用である。

公開APIは提供しない。

HistoryはPerson APIを通して取得する。

---

# Business Rules

HistoryはSubjectに属する。

HistoryはSubjectを変更できない。

Historyは利用者が編集できない。

HistoryはDomain Eventによってのみ更新する。

Historyは削除しない。

必要に応じてStatusで管理する。

---

# Design Decisions

Historyは独立したDomainである。

HistoryはSubjectを通じて活動主体を参照する。

HistoryはParticipantおよびReservationへ依存しない。

HistoryはBusiness Processの結果として生成される。

---

# Design Principles

- Historyは活動履歴を表すDomainである。
- HistoryはSubjectを保持する。
- SubjectはPersonまたはOrganizationを参照する。
- HistoryTypeは活動区分を表す。
- HistoryはDomain Eventによって自動生成する。
- Historyは読み取り専用である。
- Historyは公開APIを持たない。
- HistoryはPerson APIへ集約して公開する。
- Business Ruleは保持しない。
