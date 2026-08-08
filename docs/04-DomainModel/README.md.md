# StageArt Blueprint
# 04 - Domain Model

---

# Purpose

Domain Modelは、StageArtが管理する業務上の概念（ドメイン）を定義する。

Business Flowが「利用者が何をするか」を定義するのに対し、Domain Modelは「StageArtが何を管理するか」を定義する。

Domain Modelは画面やデータベース構造ではない。

StageArtの業務ルールそのものであり、すべての設計・実装の中心となる。

---

# Design Policy

StageArtではDomainを中心として設計する。

画面はDomainを操作するためのUIであり、

データベースはDomainを保存するための仕組みである。

Business Flowで定義された利用者の操作は、Domainを通して実現される。

---

# Domain一覧

StageArtは以下のDomainで構成される。

## Core Domain

Account

Person

Organization

Membership

Production

Reservation

Ticket

CheckIn

---

## Future Domain

Rehearsal

Finance

Asset

Store

FanClub

Notification

---

# Domain Relationships

Account

↓

Person

↓

Membership

↓

Organization

↓

Production

↓

Reservation

↓

Ticket

↓

CheckIn

---

# Responsibility

各Domainは単一の責務を持つ。

一つのDomainが複数の責務を持ってはならない。

複数のDomainで同じ情報を保持してはならない。

---

# Lifecycle

StageArtではBusiness Flowに応じてDomainが生成される。

例）

Account登録

↓

Person生成

劇団登録

↓

Organization生成

公演登録

↓

Production生成

チケット予約

↓

Reservation生成

QRチケット生成

↓

Ticket生成

受付

↓

CheckIn生成

---

# Future Scope

Version 1.0ではCore Domainのみを実装する。

Future DomainはBlueprint上で定義のみを行い、必要なVersionで実装する。