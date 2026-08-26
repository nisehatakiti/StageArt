# StageArt Blueprint

# Domain Model : Venue Policy

Version : 1.0

---

# Purpose

Venueは、Productionにおける公演会場を表す。

StageArtの主な利用対象である小劇場の劇団では、一つの演目を一つの劇場で実施する運用を基本とするため、初期仕様ではVenueをProductionに直接紐づける。

---

# Production Relationship

VenueはProductionの基本情報として管理する。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Venue

一つのProductionには、基本的に一つのVenueを設定する。

PerformanceはProductionに属する公演回であり、初期仕様ではPerformanceごとにVenueを個別指定しない。

基本構造：

Production
    ↓
Venue

Production
    ↓
Performance
    ├── Performance A
    ├── Performance B
    └── Performance C

すべてのPerformanceは、原則としてProductionに設定されたVenueで実施する。

---

# Multiple Venues

初期仕様では、一つのProductionに複数Venueを設定する巡回公演・複数都市公演等を対象としない。

例えば、以下のような構成を標準機能として要求しない。

Production
    ├── 東京Venue
    └── 大阪Venue

将来、地方巡業等の需要が明確になった場合には、ProductionとVenueの多対多等への拡張を別途設計する。

初期仕様のデータモデルでは、複数Venue対応を前提とした複雑なUIやPerformance単位の会場選択を導入しない。

---

# Venue Information

Venueには、少なくとも以下の情報を保持できるものとする。

- Venue Name
- Postal Code
- Address
- Venue URL
- Map Information
- Notes

詳細な項目は、実装上の必要性に応じて追加できる。

---

# Public Page

Production Public Pageでは、Productionに設定されたVenueを利用して会場情報を表示する。

基本的に以下を表示できる。

- 会場名
- 住所
- 地図情報
- 会場URL

公開ページでは、観客が会場を確認しやすいことを優先する。

---

# Production Setup Wizard

Production Setup Wizardの「会場」StepでProductionのVenueを設定する。

公演登録時にVenueが未確定の場合でもProduction自体は登録可能とする。

ProductionがPUBLICでVenueが未設定の場合、Public Pageでは会場情報を「Coming Soon」等の準備中表示として扱う。

Venue設定後は、Production Public Pageへ会場情報を反映する。

---

# Business Rules

1. VenueはProductionに直接紐づく。
2. 一つのProductionには基本的に一つのVenueを設定する。
3. Performanceには初期仕様で個別のVenueを持たせない。
4. PerformanceはProductionに設定されたVenueで実施することを基本とする。
5. 東京公演・大阪公演等の複数会場を持つ巡回公演は初期仕様の対象外とする。
6. 複数Venue対応が必要になった場合は別途Domain拡張を行う。
7. Venueには会場名、住所、URL、地図情報、備考等を保持できる。
8. Production Setup Wizardの会場StepでVenueを設定する。
9. Venue未確定でもProduction登録を妨げない。
10. PUBLICなProductionでVenueが未設定の場合、Public PageではComing Soon等の準備中表示を行う。
