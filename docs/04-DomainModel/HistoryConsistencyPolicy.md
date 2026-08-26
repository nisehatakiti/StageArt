# StageArt Blueprint

# Domain Consistency Policy : History / HistoricalActivity

Version : 1.0

---

# Purpose

HistoryおよびHistoricalActivityについて、Person / Participant / Production / CheckInと現在の確定仕様との整合性を定義する。

---

# Canonical Boundary

HistoryとHistoricalActivityは別の責務を持つ。

```text
StageArt上で確認されたFact
    ↓
Participant / CheckIn
    ↓
Domain Event
    ↓
History

本人申告による過去活動
    ↓
Profile
    ↓
HistoricalActivity
```

HistoryはStageArt上のFact、HistoricalActivityは本人申告情報である。

---

# Person Production History

個人ページの出演・スタッフ参加履歴は、StageArt上のProduction Participantを基礎として表示する。

対象には少なくとも以下を含む。

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- その他ParticipantTypeに定義された参加区分

出演だけでなくスタッフ参加も履歴対象とする。

---

# Existing Production Claim

StageArt上にすでに存在するProductionについて、Person本人が自分の参加履歴として追加したい場合は、本人申告だけで履歴を確定しない。

本人は、既存ProductionのどのParticipantが自分に該当するかを申告する。

Production管理者が申告内容を承認することで、Personと既存Participantの対応関係を確定する。

```text
Person
  ↓ 申告
Existing Participant
  ↓ 管理者承認
Person ↔ Participant
  ↓
Personal Participation History
```

本人が既存Participantを自由に自己認定してはならない。

---

# Historical Activity

StageArt利用開始以前など、StageArt上に対応するProduction / Participantが存在しない活動はHistoricalActivityとして本人が登録できる。

HistoricalActivityは、本人が編集・削除できるプロフィール情報であり、StageArt上の正式な活動Factへ自動変換しない。

---

# Public History

Personページでは、承認済みのStageArt上の参加履歴と、本人が公開したHistoricalActivityを区別して表示できる構造とする。

StageArt上のFactと本人申告情報を、同じ信頼レベルの情報として自動的に混同しない。

---

# Production Snapshot

Production Member表示では、参加時点の所属表記をSnapshotとして扱う。

所属がある場合：

```text
名前（所属）
```

所属がない場合：

```text
名前
```

Personの現在のMembership / Profileを参照して過去Productionの表示を再構成しない。

退団後も、そのProduction時点の表記を維持する。

---

# Production Visibility

過去Productionそのものには、管理者が公開／非公開を設定できる。

非公開のProductionは一般公開の過去公演一覧・個人履歴等へ自動表示しない。

ただし、内部管理上のParticipantやHistory等のFactを削除することとは別問題として扱う。

---

# History Generation

StageArt上のParticipant FactからParticipation Historyを生成できる。

ParticipantAdded / ParticipantUpdated / ParticipantRemoved等のDomain Eventを契機とする既存History設計を維持する。

ParticipantRemovedによって、過去の活動履歴を自動削除しない。

---

# Audience History

Audience Historyは、CheckInCompletedを契機として生成する。

```text
Reservation
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Audience History
```

ReservationCreated、ReservationUpdated、ReservationCancelledだけではAudience Historyを生成しない。

---

# Accounting Boundary

CheckInCompletedはHistoryとAccountingの双方の契機となり得る。

```text
CheckInCompleted
    ├─→ Audience History
    └─→ Ticket Revenue Recognition
          └─→ Journal Entry
```

HistoryはJournal Entryを生成・管理しない。

AccountingはHistoryを会計Factとして扱わない。

---

# Profile Boundary

Profileは現在のPerson情報を管理する。

HistoricalActivityはProfileに属する本人申告の過去活動である。

HistoryはProfileの子Entityではなく、StageArt上の活動Factから生成される独立Domainである。

---

# Ordering

PersonページのStageArt上の参加履歴は、原則として活動日時等の履歴情報に基づいて表示する。

必要な表示順制御はPresentation上の情報として扱い、History Factそのものの意味を変更しない。

Production Memberページの並び順はProduction管理者が変更できる。

---

# Deletion Boundary

以下を混同しない。

- PersonがHistoricalActivityを削除する
- Production管理者がParticipantを変更・削除する
- Productionを非公開にする
- History Factを保持する

本人がHistoricalActivityを削除してもStageArt上のParticipant / Historyには影響しない。

Participantの削除によって、既に成立したHistory Factを物理削除しない。

Productionの非公開はPublic Visibilityの変更であり、内部Factの物理削除ではない。

---

# Canonical Summary

```text
Person
 ├─ Profile
 │    └─ HistoricalActivity[]   ← 本人申告
 │
 ├─ Membership[]               ← 団体承認による所属
 │
 └─ Participant / History      ← StageArt上の活動Fact

Production
 └─ Participant
       ↓
   History

Reservation
 └─ CheckInCompleted
       ├─→ Audience History
       └─→ Ticket Revenue / Journal Entry
```

---

# Design Principle

StageArt上で確認された活動と、本人が申告した過去活動を明確に分離する。

既存Productionへの履歴追加は、本人申告だけで確定せず、Production管理者によるParticipant対応承認を必要とする。

これにより、個人の経歴ページを充実させながら、StageArt上の正式な公演参加Factの信頼性を維持する。
