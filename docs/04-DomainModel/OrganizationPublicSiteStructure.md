# StageArt Blueprint

# Organization Public Site Structure

Version : 1.0

---

# Purpose

Organization Public Site（劇団ホームページ）の公開構成を定義する。

Organization Public SiteはStageArt Coreから生成される独立した公開成果物であり、劇団情報・所属メンバー・最新公演・過去公演・外部リンクへの観客向け導線を提供する。

---

# Global Navigation

上部メニューは以下を基本とする。

```text
HOME | ABOUT | メンバー | 過去公演 | その他リンク
```

「過去公演」は公開済みの過去公演が存在する場合のみ表示する。

---

# HOME

HOMEには以下を表示する。

- 劇団ロゴ
- 劇団名
- 最新公演ビジュアル
- 最新公演タイトル
- 過去公演への導線（存在する場合）
- CONTACT

最新公演ビジュアルおよび最新公演タイトルは、対応するProduction Public Siteへのリンクとする。

最新公演が存在しない場合でもOrganization Public Siteは公開可能とし、該当箇所はComing Soon等の表示で成立させる。

Organization Public Siteは「現在公演」を表示するサイトではなく、**最新公演**を表示するサイトとして扱う。

Productionの千秋楽を迎えたことだけを理由としてHOMEを再生成しない。新しいProductionが公開されるまでは、終了したProductionであっても最新公演として表示可能とする。

---

# ABOUT

ABOUTは劇団が自由に長文を記述できるフリースペースとする。

劇団のあらまし、活動内容、沿革、主宰者からのメッセージ等を自由に記述できる。

---

# MEMBERS

MEMBERSはOrganizationに所属しているメンバーをStageArt CoreのOrganization / Person情報から自動表示する。

劇団ホームページ側でメンバー情報を二重管理しない。

メンバー情報に変更があり、公開ページの表示内容へ影響する場合はOrganization Public Siteの更新対象とする。

---

# PAST PRODUCTIONS

過去公演はProductionの公開済み情報から自動生成する一覧ページとする。

公演は新しい順に縦方向へ一覧表示する。

各公演について以下を表示できる構造とする。

- 公演ビジュアル（表）
- 公演ビジュアル（裏）
- 公演タイトル
- 公演日時
- 場所

各公演は対応するProduction Public Siteへのリンクとする。

劇団側が過去公演一覧を個別編集する方式とはせず、StageArt CoreのProduction公開情報から生成する。

新しいProductionが公開された際に、過去公演一覧を含むOrganization Public Siteを必要に応じて再生成する。

---

# OTHER LINKS

その他リンクは劇団が自由に登録できる外部リンク一覧とする。

YouTubeリンクが登録された場合は、可能な範囲でYouTubeのサムネイルを取得し、サムネイル付きリンクとして表示する。

YouTube以外のリンクも自由に登録できるものとし、V1ではサービスを限定しない。

StageArtは動画ファイルそのものをストレージへ保存・管理しない。動画を扱う場合は外部サービスへのリンクを基本とする。

---

# CONTACT

HOMEにはCONTACTへの導線を設ける。

問い合わせ方式は別途Infrastructure / Security設計で確定する。

---

# Latest Production Update Rules

Organization Public Siteの最新公演情報は、StageArt CoreのProduction公開情報から生成する。

主な更新トリガー：

1. 新しいProductionを情報公開したとき
2. Organization Public Siteに掲載されている最新Productionの公開情報を変更したとき
3. Organization自身の公開情報を変更したとき
4. Organizationメンバー情報など、自動表示対象情報を変更したとき
5. その他、Organization Public Siteの公開内容に影響する明示的なアクションを行ったとき

Production Public Siteが更新された場合でも、Organization Public Siteに影響しない変更であればOrganization Public Siteを再生成しない。

「公演ページが更新されたら必ず劇団ページも更新する」のではなく、**劇団ページに影響する変更が発生した場合のみ劇団ページを更新対象とする**。

---

# Generation Relationship

新しいProductionが情報公開された場合、Production Public Siteの生成とOrganization Public Siteの更新を同一Generation Batchとして処理できる構造を許容する。

```text
Production公開
     │
     ├─ Production Public Site
     │
     └─ Organization Public Site
          └─ 最新公演を新Productionへ更新
```

一方、Productionの千秋楽だけではOrganization Public Siteの再生成を発生させない。

Production Public Siteの終了表示は、Production Public Site側で生成時に保持した終了日時をPHPが現在日時と比較して表示する方式とする。

---

# Business Rules

- Organization Public SiteはStageArt Coreから生成・公開する。
- HOME / ABOUT / MEMBERS / PAST PRODUCTIONS / OTHER LINKSを基本構成とする。
- 過去公演メニューは過去公演が存在する場合のみ表示する。
- HOMEは最新公演を表示し、「現在公演」の管理ページとはしない。
- 最新公演のビジュアルとタイトルはProduction Public Siteへのリンクとする。
- 最新公演が存在しない場合でもComing Soon等でページを成立させる。
- ABOUTは長文フリースペースとする。
- MEMBERSはOrganization / Person情報から自動表示し、公開サイト側で二重管理しない。
- PAST PRODUCTIONSはProduction情報から自動生成し、新しい順に表示する。
- PAST PRODUCTIONSにはビジュアル（表・裏）、タイトル、日時、場所を表示できる。
- PAST PRODUCTIONSの各公演は対応するProduction Public Siteへリンクする。
- OTHER LINKSは劇団が自由に登録できる。
- YouTubeリンクは可能な範囲でサムネイル表示する。
- StageArtは動画ファイルをストレージへ保存・管理しない。
- 公演終了だけを理由としてOrganization Public Siteを再生成しない。
- 新しいProductionの公開など、Organization Public Siteに影響する明示的なアクション時に更新する。
- Production Public Siteの変更がOrganization Public Siteに影響しない場合、Organization Public Siteを更新しない。
