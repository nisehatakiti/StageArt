# StageArt Blueprint
# Value Object : Identifier

Version : 1.0

---

# Purpose

IdentifierはStageArt全体で利用する識別子(Value Object)を定義する。

すべてのEntityは固有のIdentifierによって識別される。

IdentifierはEntityのライフサイクルを通して不変である。

---

# Concept

IdentifierはEntityそのものではない。

IdentifierはEntityを識別するためのValue Objectである。

同じIdentifierは同じEntityを表す。

Identifierは表示名やメールアドレスのように変更される情報ではない。

---

# Design Rules

Identifierは以下のルールに従う。

- Immutableである。
- 一意である。
- 変更できない。
- 表示用途に利用しない。
- Business Ruleを持たない。
- Entityを識別することだけを責務とする。

---

# Identifier List

StageArtでは以下のIdentifierを定義する。

## AccountId

Accountを識別する。

---

## PersonId

Personを識別する。

---

## OrganizationId

Organizationを識別する。

---

## MembershipId

Membershipを識別する。

---

## ProjectId

Projectを識別する。

---

## ProductionId

Productionを識別する。

---

## PerformanceId

Performanceを識別する。

---

## ParticipantId

Participantを識別する。

---

## ReservationId

Reservationを識別する。

---

## ReservationSeatId

ReservationSeatを識別する。

---

## CompanionId

Companionを識別する。

---

## CategoryId

Categoryを識別する。

---

## GenreId

Genreを識別する。

---

## TagId

Tagを識別する。

---

# Equality

Identifierは値によって等価比較を行う。

例

PersonId("P001")

=

PersonId("P001")

異なる型同士は等価としない。

例

PersonId("P001")

≠

OrganizationId("P001")

---

# Persistence

IdentifierはデータベースではUUIDとして保存する。

利用者へUUIDを表示することはない。

表示用には予約番号や公開URLなど別の値を利用する。

---

# Future

将来的にIdentifierの生成方式を変更しても、

BlueprintおよびDomain Modelには影響しない。

Identifierは実装方式から独立した概念とする。

---

# Design Principles

- IdentifierはValue Objectである。
- IdentifierはImmutableである。
- IdentifierはBusiness Ruleを持たない。
- IdentifierはEntityを一意に識別する。
- Identifierは利用者へ表示しない。
