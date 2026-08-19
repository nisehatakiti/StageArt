# StageArt Blueprint

# Public Site Lifecycle Supplement

Version : 1.0

---

# Purpose

Production Public SiteとOrganization Public Site（劇団ページ）の更新関係、およびProduction終了時のOrganization Public Site更新を明確化する。

---

# Business Rules

- Production Public Siteの初回生成時には、対象Organization Public Siteも更新対象とする。
- Production Public Siteの公開情報更新時には、対象Organization Public Siteも更新対象とする。
- Productionが終了してCOMPLETEDとなったタイミングでは、Organization Public Siteを更新する。
- Production Public SiteはCOMPLETED後も公演記録として公開可能とする。
- Organization Public SiteはProductionの現在・終了状態を反映し、必要に応じて現在公演から過去公演へ掲載状態を変更する。
- Production Public SiteとOrganization Public Siteの生成は、同一Generation Batchとして扱える構造を許容する。
- 一方の公開成果物の生成失敗によって、既存の正常な公開成果物を破壊してはならない。
- 公開サイトはStageArt CoreをSource of Truthとし、Organization / ProductionのBusiness Dataを公開サイト側で二重管理しない。

---

# Lifecycle

```text
Production作成
   ↓
PRIVATE
   ↓ 情報公開
PUBLIC
   ├─ Production Public Site生成
   └─ Organization Public Site更新
   ↓ 公開情報変更 + 更新確認 YES
   ├─ Production Public Site更新
   └─ Organization Public Site更新
   ↓ 公演終了
COMPLETED
   └─ Organization Public Site更新
       ├─ 現在公演から除外
       └─ 過去公演等へ反映
```

公演ページは「作品の記録」、劇団ページは「劇団の現在」を担うため、Productionのライフサイクルに応じて両者を連動させる。

---

# Generation Batch

Productionに関する公開変更がOrganization Public Siteへ影響する場合、Production Public Site用RequestとOrganization Public Site用Requestを同一のライフサイクルイベントから生成できる構造とする。

```text
Generation Batch
      │
      ├─ Production Public Site
      └─ Organization Public Site
```

Production終了時はOrganization Public Siteの更新を必須のライフサイクルイベントとして扱う。
