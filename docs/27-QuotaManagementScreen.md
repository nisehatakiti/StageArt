# StageArt Blueprint
# Chapter 27 : Quota Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the Production-level **ノルマ管理** screen and the business rules for configuring ticket quotas for Production members.

ノルマ is managed in the context of a Production. The configured quota is used for each applicable Production member's ticket quota progress displayed on Home.

The ticket quantity used for quota progress is the number of reservations whose **扱い** is that member.

---

## 2. Production Management Menu

The Production management screen adds the following menu:

- ノルマ管理

The menu is available to users who have the corresponding Production management authority, according to the existing Production management authorization rules.

Conceptually:

```text
公演管理
├─ メンバー管理
├─ 稽古管理
├─ 公演回管理
├─ チケット管理
├─ ノルマ管理
└─ 公演HP / 情報管理
```

---

## 3. Quota Management Screen

The screen provides the following settings and operations.

```text
ノルマ管理

ノルマあり　□

（ノルマあり ON の場合）
基本ノルマ枚数　[　　　] 枚

メンバー別ノルマ

メンバー          ノルマ枚数
山田 太郎         [ 10 ]
佐藤 花子         [ 10 ]
鈴木 一郎         [ 10 ]

[ 保存 ]
```

### 3.1 ノルマあり

Display a checkbox labeled **「ノルマあり」**.

- OFF: The Production does not have a ticket quota.
- ON: The Production has a ticket quota and the basic quota quantity can be entered.

When the checkbox is ON, the **基本ノルマ枚数** input becomes available.

---

## 4. Basic Quota Quantity

When **ノルマあり** is ON, the administrator can enter the Production's **基本ノルマ枚数**.

The basic quota is the default quota quantity applied to the Production's registered members.

### 4.1 Updating the Basic Quota

When the basic quota quantity is changed and saved, the new basic quota quantity is applied **uniformly to all registered Production members**.

This is a confirmed business rule.

For example:

```text
変更前
基本ノルマ：10枚

山田：10枚
佐藤：20枚
鈴木：5枚

↓ 基本ノルマを15枚に変更して保存

変更後
基本ノルマ：15枚

山田：15枚
佐藤：15枚
鈴木：15枚
```

Any previously configured individual quota differences are therefore replaced by the newly saved basic quota.

The system must not preserve previous individual quota values when the basic quota is updated.

---

## 5. Member-specific Quota

When the basic quota is registered or updated, the screen displays the registered Production members together with their quota quantity.

Each member's quota quantity can then be edited individually.

Example:

```text
基本ノルマ：15枚

メンバー          ノルマ枚数
山田 太郎         [ 15 ]
佐藤 花子         [ 20 ]
鈴木 一郎         [ 15 ]
```

In this example, 佐藤花子 has an individual quota of 20枚 while the other members remain at the basic quota of 15枚.

Individual quota changes are saved using the **「保存」** operation.

An individual quota change does not change the Production's basic quota or the quota of other members.

---

## 6. Relationship to Production Members

The quota is maintained for the registered members/participants of the Production.

The member list used by this screen is the current Production member list defined by Production Member Management.

Production Member Management and Quota Management are therefore separate management operations, but Quota Management uses the Production member records as its target members.

A Person's general StageArt profile is not modified by changing a Production quota.

---

## 7. Relationship to Home

When a Production has a quota, the user's Home can display quota progress for the applicable Production member.

The progress is expressed as:

```text
予約枚数 / ノルマ枚数
```

The **予約枚数** is the number of tickets reserved with that member as **扱い**.

Example:

```text
チケットノルマ　12 / 20枚
```

The quota denominator is the member-specific quota configured by this screen.

If no quota is configured for the Production, quota progress is not displayed as a quota target for that Production.

---

## 8. Relationship to Ticket Back

Quota Management is separate from the Production-wide ticket-back settings.

- ノルマ: configured per Production member through this screen.
- チケットバック: configured at the Production level through Ticket Management.

For both purposes, the member's ticket count is based on reservations whose **扱い** is that member, according to the existing ticket sales rules.

The quota configuration must not introduce a member-specific ticket-back rate or override the Production-wide ticket-back calculation method/conditions.

---

## 9. Save Behavior

Pressing **「保存」** saves:

- ノルマあり／なし
- 基本ノルマ枚数 when applicable
- Each registered member's quota quantity

When the basic quota is changed, all registered Production members receive the new basic quota quantity as part of the saved update.

When only an individual member quota is changed, only that member's quota is changed.

---

## 10. Confirmed UX and Business Rules

- **ノルマ管理** is a Production-level management menu.
- The screen provides a **ノルマあり** checkbox.
- When **ノルマあり** is ON, **基本ノルマ枚数** can be entered.
- The registered Production members are displayed with their quota quantities.
- Each member's quota can be changed individually.
- Pressing **保存** saves the quota settings.
- When the basic quota is updated and saved, the new basic quota is applied uniformly to **all registered Production members**.
- Previous individual quota differences are overwritten when the basic quota is updated.
- Afterward, individual member quotas can be changed independently.
- Quota progress on Home is represented as **予約枚数 / ノルマ枚数**.
- The reservation count is based on reservations whose **扱い** is the relevant Production member.
- Quota Management does not configure or override Production-wide ticket-back settings.

---

## 11. Scope

This chapter defines the confirmed business behavior and primary UX for Production Quota Management.

Detailed API paths, database field names, validation messages, authorization implementation, exact input constraints, and visual styling are implementation details unless separately confirmed.
