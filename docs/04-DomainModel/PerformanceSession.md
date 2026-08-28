# PerformanceSession（公演回）

Status: **Confirmed Blueprint**

---

## Purpose

PerformanceSessionは、Productionとして管理される公演・活動のうち、観客に対して実際に開催される一回ごとの公演回を表す。

Productionそのものと、公演回を区別する。

基本構造は以下とする。

```text
Production
↓
PerformanceSession（公演回）
↓
Ticket
```

---

## 1. Productionとの関係

Productionは、制作・公演活動全体を表す。

PerformanceSessionは、そのProductionに属する具体的な開催回を表す。

例：

```text
Production
「12人のうかれる人々」

├─ 2026/10/10 14:00 公演回
├─ 2026/10/10 19:00 公演回
├─ 2026/10/11 14:00 公演回
└─ 2026/10/11 19:00 公演回
```

一つのProductionは、複数のPerformanceSessionを持つことができる。

---

## 2. Ticketとの関係

Ticketは、原則としてPerformanceSessionに紐づく。

したがって、基本構造は以下とする。

```text
Production
↓
PerformanceSession
↓
Ticket
↓
Reservation
↓
IssuedTicket
↓
CheckIn
```

複数公演回共通のチケットなど、通常構造を超えるチケットモデルが必要になった場合は、将来の明示的なBusiness Ruleとして追加する。

現時点では、Ticketを直接Production全体に曖昧に紐づける構造を正本としない。

---

## 3. 管理する情報

PerformanceSessionは、必要に応じて以下の情報を管理する。

- Production
- 公演日
- 開場時間
- 開演時間
- 終演時間
- 会場参照
- 公演回状態
- 定員
- 公開状態
- チケット販売状態

詳細な会場情報、公開情報、チケット価格などは、それぞれのDomain Modelとの責務分離を維持する。

---

## 4. Module Ownership

PerformanceSessionは、Core DomainではなくPerformance & Ticket Moduleが所有する。

CoreはProduction Contextを提供する。

ModuleはProductionを複製しない。

関係は以下とする。

```text
StageArt Core
  └─ Production
        ↓ Production ID / Contract
Performance & Ticket Module
  └─ PerformanceSession
        ↓
     Ticket
```

ModuleはProductionContextContractなどを通じてProduction情報を参照する。

CoreのProduction EntityやRepositoryへ直接依存しない。

---

## 5. WordPress Plugin化

PerformanceSessionとTicketを同一Moduleとすることで、StageArt外でも以下の一般的な構造として利用できる。

```text
Event / Production
↓
Performance Session
↓
Ticket
↓
Reservation
↓
Check-in
```

そのため将来的なWordPress製品は、PerformanceSessionとTicketを別々のプラグインに分割せず、**Performance & Ticket Plugin**として提供する。

StageArt上ではCore Contract Adapterを使用し、単体WordPress利用時には別Host Adapterを差し替えられる構造を目指す。

---

## 6. Canonical Decision

StageArtにおける公演・公演回・チケットの正式なCanonical Modelを以下とする。

```text
Organization
↓
Project
↓
Production
↓
PerformanceSession（公演回）
↓
Ticket
```

Reservation、IssuedTicket、CheckIn、QRTicketは、Ticket lifecycleの下位Domainとして扱う。

この構造は、StageArt本体と将来のWordPress Pluginの両方に共通する基本構造とする。
