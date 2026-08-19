# StageArt Blueprint

# Production Public Visibility Policy

Version : 1.0

---

# Purpose

Organization Public SiteおよびProduction Public Siteにおける、複数Productionの同時公開・表示ルールを定義する。

---

# Multiple Productions

Organizationは、複数のProductionを同時に公開・管理できる。

StageArtは「1 Organization = 1 公演」を前提としない。

同一Organizationにおいて、複数のProductionが並行して制作・告知・公演されることを許可する。

ProjectはInternal Domainであり、利用者向けUIでは必ずしも意識させない。Projectは複数Productionを束ねる内部単位として利用できるが、公開サイト上の公演表示単位はProductionとする。

---

# Public Site: Current Public Productions

Organization Public SiteのHOMEでは、単一の「最新公演」だけを表示するのではなく、**公開中公演（Current Public Productions）**を複数表示できる構造とする。

ここでいう「公開中」は、Productionが情報公開され、かつPublic Performance上で終了扱いとなっていないProductionを基本とする。

Production Public Siteの公開状態と、Public Performance上の終了判定は別概念として扱う。

千秋楽の翌日以降は対象ProductionをOrganization Public Siteの公開中公演一覧から除外する。ただしProduction Public Site自体は削除せず、過去公演として参照可能な状態を維持する。

---

# HOME Layout

複数の公開中公演が存在する場合、HOMEでは公演情報をカード単位で表示し、左右スライド可能なカルーセル形式を基本とする。

```text
┌─────────────────────────────┐
│       公演ビジュアル          │
│                             │
│       公演肩書               │
│       公演タイトル            │
│       公演日時               │
│       場所                   │
│                             │
│       [公演ページへ]          │
└─────────────────────────────┘

        ＜  ● ○ ○  ＞
```

公演が1件しか存在しない場合は、カルーセル操作を表示せず単一カードとして表示できる構造とする。

公演が存在しない場合はComing Soon等で成立させる。

各カードは対応するProduction Public Siteへリンクする。

公演肩書が設定されている場合は、公演タイトルの上部に表示する。未設定の場合は表示しない。

---

# Ordering

複数の公開中公演を表示する場合の並び順は、公開日等の明示的なBusiness Ruleに基づいて決定する。

単純にProjectの作成順やProductionId順を利用者向け表示順としてはならない。

具体的な並び順はPublic Site Generation設計で確定する。

---

# Management UI

Management ClientのDashboardも、複数Productionを前提とする。

Organizationに複数のProductionが存在する場合、単一の「現在の公演」を前提とせず、ユーザーがアクセス可能なProductionを一覧またはカードとして表示できる構造とする。

DashboardからProductionを選択し、Production Scopeの管理画面へ遷移する。

ProjectをManagement UIの必須ナビゲーション単位として表示しない。

---

# Production Scope

Productionは、StageArtにおける具体的な公演・活動の管理単位である。

Productionごとに、必要に応じて以下を管理する。

- 公演情報
- 公演肩書
- 公演タイトル
- 日程
- 出演者・スタッフ
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Accounting
- Public Site

Productionの詳細なDomain責務はProduction Domainおよび各関連Domainで定義する。

---

# Public Site Update Rules

新しいProductionを公開した場合、Organization Public Siteの公開中公演一覧へ追加する。

既存の公開中Productionの公開情報を変更し、その変更がOrganization Public Siteに表示される情報へ影響する場合、Organization Public Siteを更新対象とする。

あるProductionのPublic Siteだけが変更され、Organization Public Siteに表示する情報へ影響しない場合、Organization Public Siteを更新しない。

Productionの千秋楽翌日への到達だけを理由としてOrganization Public Site全体を再生成する必要はない。公開中公演の表示は、生成時に保持した終了日時等を利用して公開ページ側で時間判定できる構造を基本とする。

---

# Past Productions

終了したProductionは、Organization Public Siteの「公開中公演」からは除外されるが、PAST PRODUCTIONSには引き続き表示対象となる。

過去公演は新しい順に表示する。

各公演はProduction Public Siteへリンクする。

---

# Business Rules

- 一つのOrganizationで複数Productionを同時に公開・管理できる。
- StageArtは1 Organization = 1 公演を前提としない。
- ProjectはInternal Domainであり、利用者向けUIの必須単位としない。
- 公開サイト上の公演表示単位はProductionとする。
- Organization Public Site HOMEでは複数の公開中Productionを表示できる。
- 複数件の場合はカード型カルーセルを基本とする。
- 1件の場合は単一カードとして表示できる。
- 0件の場合はComing Soon等で成立させる。
- 各公演カードは対応するProduction Public Siteへリンクする。
- 公演肩書は設定されている場合のみタイトル上部へ表示する。
- 千秋楽翌日以降はPublic Performance上の終了扱いとし、公開中公演から除外する。
- 終了したProduction Public Siteは削除しない。
- 終了したProductionはPAST PRODUCTIONSで参照できる。
- Management Dashboardも複数Productionを前提とする。
- ProjectをManagement UIの必須ナビゲーション単位として表示しない。
