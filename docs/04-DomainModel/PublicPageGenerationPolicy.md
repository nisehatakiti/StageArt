# StageArt Blueprint

# Public Page Generation Policy

Version : 1.0

---

# Purpose

Production Public PageおよびOrganization Public Pageについて、ページ生成、情報公開、情報更新、公開終了の責務を分離し、CRONに依存しない観客向けページ公開方式を定義する。

本Policyは、既存のProduction Public Page Policy / Public Page URL Policyを補完する。

---

# Generation and Publication Separation

Public Pageの「ページ生成」と「ページ内情報の公開」は別の概念として扱う。

Production作成後、公開開始前であっても、将来公開するためのProduction Public Pageを生成できるものとする。

Production Public Pageは、最低限Production Titleが存在すればページとして成立できる。

その他の情報が未入力・未公開の場合でもページ生成を妨げない。

未入力情報は、観客向け表示時にComing Soon / 近日公開等として扱える。

---

# PHP Static Generation Model

V1では、Production Public PageをPHPページとして生成し、生成時点のBusiness Dataと各情報の公開日時をページ側へ埋め込む方式を基本とする。

ページ生成後、情報公開のためにCRONでページ自体を再生成する必要はない。

PHPはサーバーの現在日時を参照し、各情報に設定された公開日時と比較して表示・非表示を判断する。

基本判定：

```text
現在日時 >= 情報公開日時
    ↓
公開情報として表示

現在日時 < 情報公開日時
    ↓
非表示 / Coming Soon等
```

サーバーの日時・タイムゾーンはOrganization Timezoneを基準とする。

---

# Information Publication DateTime

情報公開日時はProduction情報としてStageArt Core側に保持する。

ページ生成時には、公開対象となる各情報の値と公開日時をPHPへ反映する。

公開日時はページ生成を実行する日時とは独立して管理する。

例えば、ページを8月1日に生成し、Ticket情報の公開日時を8月10日10:00に設定した場合、8月10日10:00まではTicket情報を表示せず、時刻到達後にPHPが自動的に表示する。

CRONによる「公開時刻になったからページを再生成する」処理はV1の必須要件としない。

---

# Production Page Initial Generation

Production Public Pageは、公開開始時点で初めて生成するのではなく、Production管理者が必要な段階で事前生成できる。

ページが事前生成されていても、設定された公開日時までは対象情報を観客へ表示してはならない。

Production Titleのみ登録済みの場合でもページ生成可能とし、その他の未入力情報はComing Soon等で表示できる。

---

# Public Page Update

Production情報に変更が生じた場合、管理者へ「公演ページを更新しますか？」と確認できるものとする。

管理者がYESを選択した場合、最新のProduction Business Data、公開日時、画像等を元にPublic Pageを再生成する。

NOの場合はPublic Pageを更新せず、StageArt Core側のBusiness Dataのみを変更できる。

Public Pageの再生成は情報公開日時の到来を待たず実行できる。

---

# Public Page Generation Permission

Production Public Pageの生成・更新・公開に関する管理Actionは、以下の権限を持つ者が実行できる。

- Organization Manager / 団体管理者
- Production Manager / 公演管理者
- Production Delegate / 公演に対する代理人

一般MemberはPublic Page生成・更新・公開Actionを実行できない。

---

# Production URL

Production Public PageのURLは以下の構造で固定する。

```text
/[Organization Slug]/[Production Slug]/
```

Organization SlugとProduction Slugを組み合わせてProduction Public Pageを識別する。

Slug変更時の既存URLの扱いはPublic Page URL Policyに従う。

---

# Production Visuals

Production Public Pageで使用する公演ビジュアルはV1では2枚固定とする。

- Flyer Front / 表
- Flyer Back / 裏

追加の公演ビジュアルをV1で自由に増やす仕様は設けない。

未登録の場合でもPublic Page生成を妨げない。

---

# Production Page End Date

Production Public Pageの公演終了表示はStageArt Production Lifecycleとは独立して扱う。

公演終了日は、原則として千秋楽の翌日とする。

公演終了日時をProduction情報として保持し、Public Page生成時にPHPへ埋め込む。

PHPはサーバーの現在日時と公演終了日時を比較する。

```text
現在日時 < 公演終了日時
    ↓
通常の公演ページ表示

現在日時 >= 公演終了日時
    ↓
終了メッセージを表示
```

終了時にPublic Pageそのものを再生成する必要はない。

終了後は、観客向けページ上に少なくとも以下の趣旨のメッセージを自動表示できるものとする。

```text
公演無事終了しました。
ご来場いただいた皆様ありがとうございました。
```

---

# No CRON Requirement for Visibility

V1では、情報公開日時の到来および公演終了日時の到来を契機としてCRONでPublic Pageを再生成する設計を採用しない。

公開・終了の表示切替は、生成済みPHPページがサーバー現在日時を判定することで実現する。

CRONは将来的なキャッシュ更新、保守処理等に利用することを妨げないが、Public Pageの時刻公開・終了表示の成立条件にはしない。

---

# Organization Public Page Relationship

Organization Public Pageは、Production Public Pageとは独立したページとして生成・管理する。

Organization Public PageのHOMEでは、複数の最新Productionをカード形式で表示できる。

Production追加、最新Production情報変更等、Organization Public Pageの表示内容に影響するActionが発生した場合に、Organization Public Pageを更新する。

Organization Public PageをProduction Public Pageの情報公開時刻到来だけを理由としてCRONで再生成する必要はない。

Production終了後にOrganization Public Pageの最新Production表示等へ影響がある場合は、該当Actionに応じてOrganization Public Pageを更新する。

---

# V1 Principle

V1のPublic Pageは、以下の分離を基本原則とする。

```text
StageArt Core Business Data
        ↓
Public Page生成
        ↓
PHPへ情報 + 公開日時 + 終了日時を埋め込む
        ↓
サーバー現在日時を判定
        ↓
観客向け表示を自動切替
```

この方式により、公開時刻ごとのCRON実行や、千秋楽翌日のページ再生成を必須としない。

Public Pageの内容を変更する必要がある場合のみ、管理者の明示的な更新Actionによって再生成する。
