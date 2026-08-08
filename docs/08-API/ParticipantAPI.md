# StageArt Blueprint
# API : Participant

Version : 1.0

---

# Purpose

Participant APIはParticipantドメインを操作するためのREST APIを定義する。

ParticipantはProductionへ参加する人物または団体を表すBusiness Resourceである。

出演者だけでなく、

- スタッフ
- 主催
- 協賛
- 後援

など、公演へ参加するすべての主体を管理する。

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
- Person
- Organization
- Role
- ParticipantType

ParticipantはProductionの構成要素として公開する。

---

# Create Participant

## Request

```
POST /api/v1/productions/{productionId}/participants
```

### Request Body

```json
{
  "personId": "...",
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
- ParticipantAddedを発行する。
- 出演実績はHistoryへ自動反映する。

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

ParticipantIdは変更できない。

PersonおよびOrganizationの変更はできない。

---

# Remove Participant

## Request

```
DELETE /api/v1/participants/{participantId}
```

### Business Rules

- ParticipantをProductionから削除する。
- ParticipantRemovedを発行する。
- HistoryはDomain Eventによって更新する。

---

# List Participants

## Request

```
GET /api/v1/productions/{productionId}/participants
```

---

# Search

検索対象

- Person
- Organization
- Role
- ParticipantType

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

HistoryはDomain Eventによって自動更新される。

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

---

# Design Principles

- ParticipantはProductionへの参加を表すBusiness Resourceである。
- PersonまたはOrganizationを参照する。
- 出演実績はHistoryへ自動反映する。
- Historyは直接更新しない。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
