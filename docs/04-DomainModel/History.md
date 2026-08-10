# StageArt Blueprint
# Domain Model : History

Version : 3.0

---

# Purpose

HistoryはStageArtにおける活動履歴を表す独立したDomainである。

HistoryはPersonやOrganizationに属する子Entityではなく、
Subjectを中心として活動履歴を管理する。

Historyは利用者が直接作成・編集するDomainではない。

ParticipantやReservationなどのDomainで発生したBusiness Eventを契機として、
History Domainが必要な履歴を生成・更新する。

---

# Concept

HistoryはSubjectがStageArt上で行った活動を記録する。

```text
Subject
   │
   ▼
History
   │
   ├── Production
   └── Performance