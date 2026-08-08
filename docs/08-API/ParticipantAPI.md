# StageArt Blueprint
# API : Participant

Version : 2.0

---

# Purpose

Participant APIはParticipantドメインを操作するためのREST APIを定義する。

ParticipantはProductionへ参加する活動主体を表すBusiness Resourceである。

活動主体はSubjectによって表現する。

SubjectはPersonまたはOrganizationを参照する共通Referenceであり、
ParticipantはSubjectを通じて活動主体を管理する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ParticipantはProduction配下のResourceとして公開する。

```
/api/v1/productions/{productionId}/participants
```

Participant固有の操作はParticipant Resourceとして公開する。

```
/api/v1/participants/{participantId}
```

---

# Public Resource

Participant APIが公開する情報

- Participant
- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

SubjectはPersonまたはOrganizationを表す。

---

# Create Participant

## Request

```
POST /api/v1/productions/{productionId}/participants
```

### Request Body

```json
{
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "role": "主演",
  "creditOrder": 1
}
```

### Success

```
201 Created
```

### Business Rules

- Participantを作成する。
- Productionへ紐付ける。
- Subjectを登録する。
- ParticipantAddedを発行する。
- HistoryはDomain Eventによって自動更新する。

---

# Get Participant

## Request

```
GET /api/v1/participants/{participantId}
```

---

# Update Participant

## Request

```
PUT /api/v1/participants/{participantId}
```

更新可能項目

- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

Subjectは変更できない。

活動主体を変更する場合は、
Participantを作り直す。

---

# Remove Participant

## Request

```
DELETE /api/v1/participants/{participantId}
```

### Business Rules

- ParticipantをProductionから削除する。
- ParticipantRemovedを発行する。
- HistoryはDomain Eventによって自動更新する。

---

# List Participants

## Request

```
GET /api/v1/productions/{productionId}/participants
```

---

# Search

検索対象

- Subject
- ParticipantType
- Role
- Status

---

# Authorization

Participantの追加・更新・削除は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

---

# Domain Events

Participant APIは以下のDomain Eventを利用する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

HistoryはこれらのDomain Eventによって自動更新される。

---

# Error Response

代表例

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

500 Internal Server Error

---

# Future

将来的に以下へ対応する。

- 日替わり出演
- 出演期間
- 出演回指定
- グループ出演
- ゲスト出演
- SNSリンク

Subjectの種類が追加されても、
Participant APIは変更しない。

---

# Design Principles

- ParticipantはProductionへの参加を表すBusiness Resourceである。
- ParticipantはSubjectを通じて活動主体を参照する。
- SubjectはPersonまたはOrganizationを表す。
- ParticipantはPersonおよびOrganizationへ直接依存しない。
- ParticipantTypeはシステムが管理する参加区分である。
- Roleは表示用の役割である。
- Subjectは変更できない。
- HistoryはDomain Eventによって自動更新する。
- Historyは直接更新しない。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
