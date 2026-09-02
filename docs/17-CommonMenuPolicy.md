# StageArt Blueprint Addendum
# Common Menu Policy

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the common account-level menu items that must be available regardless of the current StageArt screen/context.

---

## 2. Common Menu Items

The following two menu items are common to all authenticated StageArt screens:

- アカウント管理
- ログアウト

These items are not restricted to Home and must remain accessible from any screen while the user is authenticated.

### 2.1 アカウント管理

- Available to all authenticated users.
- Manages the StageArt account itself.
- This is separate from Person / プロフィール information.

### 2.2 ログアウト

- Available to all authenticated users.
- Logs the user out of the current StageArt session.
- The user should not need to return to Home in order to log out.

---

## 3. Relationship with Context-specific Menus

Context-specific menus such as:

- 団体を探す
- 公演を探す
- プロフィール
- 経費精算
- 団体情報
- 公演情報
- その他の Organization / Production / Performance operations

are displayed according to their respective availability, relationship, scope, and management authority.

The common account-level items defined in this document are different: **アカウント管理 and ログアウト are always available on every authenticated screen.**

---

## 4. Web / Mobile

The common-menu rule applies to both Web and Mobile.

The visual placement may differ according to the platform layout, but the two functions must remain explicitly accessible from every authenticated screen.

This does not require the user to rely on swipe-back navigation or to return to Home before accessing these functions.
