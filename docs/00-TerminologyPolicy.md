# StageArt Blueprint

# Terminology Policy

Version : 1.0

---

# Purpose

StageArtの設計書・画面仕様・ユーザー向け文言では、Performanceを表す日本語用語を統一する。

---

# Production / Performance

StageArtでは、以下の対応関係を基本とする。

- Production = 公演
- Performance = 公演スケジュール

Productionは、公演そのものを表す。

Performanceは、その公演に含まれる個々の上演日時・上演単位を表す。

例：

公演
  ├─ 公演スケジュール①
  ├─ 公演スケジュール②
  └─ 公演スケジュール③

---

# User-facing Terminology

これまで「公演回」と表現していた箇所は、原則として「公演スケジュール」に統一する。

例：

- 公演回管理 → 公演スケジュール管理
- 公演回を作成する → 公演スケジュールを作成する
- 公演回一覧 → 公演スケジュール一覧
- 公演回情報 → 公演スケジュール情報

「公演回」という表現は、新規の画面・仕様・ドキュメントでは使用しない。

---

# Internal Names

この用語変更は、ユーザー向け日本語表現の統一を目的とする。

既存の内部コード・ドメイン・API・DB等の名称は変更しない。

したがって、以下のような内部名称はそのまま維持する。

- Performance
- PerformanceId
- Performance Domain
- Performance API

内部名称を説明する際には、必要に応じて「Performance（公演スケジュール）」のように併記する。

---

# Rule

今後StageArtのBlueprint、仕様書、画面設計、ユーザー向けUI文言を更新する際は、本Terminology Policyを基準として用語を統一する。
