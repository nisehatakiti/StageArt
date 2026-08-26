# StageArt Blueprint

# Public Site Lifecycle Supplement

Version : 1.1

---

# Purpose

Production Public SiteとOrganization Public Site（劇団ページ）の更新関係、およびProduction終了時のPublic Site上の扱いを明確化する。

Production Public SiteとOrganization Public Siteは、それぞれ異なる役割を持つ公開成果物として扱う。

- Production Public Site = その公演の記録・観客向け情報
- Organization Public Site = 劇団の最新情報・最新公演を伝えるサイト

---

# Public Performance End Rule

公演ページ上の「公演終了」はStageArtのProduction Lifecycleとは切り離して扱う。

千秋楽の翌日から、Public Performance上では公演終了と判定する。

ProductionがStageArt上でCOMPLETEDになったか、会計・決算処理が完了したか等は、Public Performanceの終了判定に使用しない。

公演終了後もProduction Public Siteは削除せず、公演記録として公開可能な状態を維持する。

公演ページの終了表示はページそのものを再生成することで実現する必要はない。Production Public Site生成時に公演終了日時を公開成果物へ保持し、公開ページ側のPHPが現在日時と比較して終了表示を動的に行える構造を基本とする。

終了後の公演ページでは、例えば以下の定型文を自動表示する。

> 公演は無事終了いたしました。ご来場いただいた皆様、ありがとうございました。

---

# Organization Public Site: Latest Production

Organization Public Siteは「現在公演」を管理・表示するサイトではなく、基本的に**最新公演**を表示するサイトとして扱う。

したがって、あるProductionの千秋楽を迎えたことだけを理由としてOrganization Public Siteを再生成する必要はない。

例えば、Production Aが終了しても、新しいProduction Bが公開されていない限り、Organization Public Siteの「最新公演」はProduction Aのままとする。

```text
Production A 公開
    ↓
Organization Public Site生成
    ↓
最新公演 = A

A 千秋楽
    ↓
公演ページだけが自動的に「終了」表示
    ↓
Organization Public Siteは変更しない

Production B 公開
    ↓
Organization Public Site更新
    ↓
最新公演 = B
```

---

# Organization Public Site Update Trigger

Organization Public Siteの更新は、時間経過による自動更新ではなく、StageArt上で公開情報に影響する明示的なアクションを契機とする。

主な更新トリガー：

1. 新しいProductionを情報公開したとき
2. Organization Public Siteに掲載されている最新Productionの公開情報を変更したとき
3. Organization自身の公開情報を変更したとき
4. その他、Organization Public Siteの公開内容に影響する明示的なアクションを行ったとき

最新Productionの公開情報を変更した場合でも、Organization Public Siteに掲載している情報へ影響しない変更であれば、Organization Public Siteを再生成する必要はない。

「公演ページが更新されたら必ず劇団ページも更新する」のではなく、**劇団ページに影響する変更が発生した場合のみ劇団ページを更新対象とする**。

---

# Production Public Site Update

公開済みProductionの公開対象情報に変更が生じた場合、StageArtは管理者に「公演ページを更新しますか？」等の確認を提示できる。

YESの場合、Production Public SiteのGeneration Requestを登録する。

Organization Public Siteについては、その変更がOrganization Public Siteに掲載している最新Production情報へ影響する場合のみ更新対象とする。

NO / あとでの場合、現在公開中のProduction Public Siteを維持する。

---

# Initial Production Publication

新しいProductionを情報公開したタイミングでは、Production Public Siteを生成するとともに、Organization Public Siteに新しいProductionを最新公演として反映するため、Organization Public Siteも更新対象とする。

```text
Productionが情報公開
        ↓
Production Public Site Generation Request
        +
Organization Public Site Generation Request
        ↓
CRON Worker
        ↓
Generate / Validate
        ↓
Atomic Publish
```

---

# Generation Batch

Productionに関する公開変更がOrganization Public Siteへ影響する場合、Production Public Site用RequestとOrganization Public Site用Requestを同一のライフサイクルイベントから生成できる構造とする。

```text
Generation Batch
      │
      ├─ Production Public Site
      └─ Organization Public Site（影響がある場合のみ）
```

必ずしも2つの独立Jobを作る必要はなく、同一Generation Batchとして処理できる構造を許容する。

---

# Consistency Rules

- Production Public Siteの初回生成時は、Organization Public Siteも更新対象とする。
- Production Public Siteの公開情報変更時は、Organization Public Siteに影響する場合のみOrganization Public Siteを更新する。
- Productionの千秋楽翌日になったことだけを理由としてOrganization Public Siteを再生成しない。
- Production Public Siteは千秋楽翌日から自動的に「公演終了」表示へ切り替えられる。
- Production Public Siteは公演終了後も公演記録として公開可能とする。
- Organization Public Siteは「最新公演」を表示するものとし、Productionの終了によって自動的に最新公演を別のProductionへ変更しない。
- 新しいProductionが公開されたとき、そのProductionを最新公演としてOrganization Public Siteを更新する。
- Organization Public Siteの公開内容に影響しないProduction内部変更では、Organization Public Siteを更新しない。
- 公開成果物の生成に失敗しても、既存の正常な公開版を破壊してはならない。
- 公開サイトはStageArt CoreをSource of Truthとし、Organization / ProductionのBusiness Dataを公開サイト側で二重管理しない。

---

# Design Principle

> 公演ページは「その公演の記録」であり、千秋楽翌日から自動的に終了表示へ切り替わる。

> 劇団ページは「劇団の現在公演」ではなく「最新公演」を表示する。

> 劇団ページは時間経過ではなく、公開情報に影響するStageArt上のアクションを契機として更新する。

> 公演ページを更新した場合でも、劇団ページに影響がなければ劇団ページを更新しない。

> 新しい公演が公開されたときは、公演ページと劇団ページを連動して更新する。
