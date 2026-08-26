# StageArt Blueprint

# Historical Production Migration Policy

Version : 1.0

---

# Purpose

StageArt利用開始以前に実施された過去公演についても、StageArt上でProductionとして管理し、過去公演ArchiveおよびPublic Pageへ活用できるようにするための基本方針を定義する。

StageArt導入前の過去公演を、StageArt外部の単なる参照資料として扱うのではなく、可能な範囲でStageArtのProduction履歴として移行できる構造を基本とする。

---

# Historical Production as Production

StageArt利用開始以前に実施された公演も、原則としてProductionとして登録する。

StageArt導入前だからといって、別のHistoricalProduction Entityを作成してProductionと分離してはならない。

基本構造：

Organization
    ↓
Project
    ↓
Production

StageArt導入前のProductionも、StageArt導入後に作成されたProductionと同じProduction Domainで管理する。

これにより、過去公演と現在公演を同じProduction一覧、Archive、Public Pageの仕組みで扱えるようにする。

---

# Production Origin

Productionには、そのProductionがどのようにStageArtへ登録されたかを識別できるOrigin情報を持たせることを許容する。

基本的な区分：

- CREATED_IN_STAGEART
- MIGRATED

MIGRATEDは、StageArt利用開始以前に存在していたProduction、またはStageArt外部で管理されていた過去ProductionをStageArtへ移行したことを表す。

Production OriginはProductionのBusiness Identityではない。

OriginがMIGRATEDであっても、ProductionIdは通常のProductionと同じくStageArt内部で一意に管理する。

---

# Historical Production Lifecycle

過去公演を移行する場合、現在進行中のProduction Lifecycleを最初から再現する必要はない。

移行時点で既に公演および必要な精算が完了している場合は、ProductionをCOMPLETEDまたはARCHIVEDとして登録できるものとする。

基本的な考え方：

StageArt導入前
    ↓
過去公演
    ↓
Historical Migration
    ↓
COMPLETED / ARCHIVED

移行のためだけに、過去の日付に合わせてDRAFT → PLANNING → ACTIVEを実際に遷移させる必要はない。

ただし、Migration処理によってLifecycleの意味を破壊してはならず、最終的なStatusはProduction Lifecycle Ruleに従って管理する。

---

# Historical Data Completeness

StageArt導入前の過去公演は、現在のProductionと同じ情報量を保持しているとは限らない。

そのため、過去資料に存在しない情報を必須入力として要求し、移行を妨げてはならない。

例えば、以下のような部分的な移行を許可する。

- 公演タイトルのみ
- 公演タイトル + 公演年
- 公演タイトル + 公演期間
- 公演タイトル + 会場
- 公演タイトル + 出演者
- 公演タイトル + Flyer
- 公演タイトル + あらすじ
- 公演タイトル + 観客アンケート抜粋

存在しない情報を推測・生成してBusiness Factとして登録してはならない。

過去資料に記録されている情報と、現在のStageArtで新たに登録した情報は区別できる構造を妨げない。

---

# Historical Date Precision

過去公演の資料によっては、正確なPerformance Dateが存在しない場合がある。

その場合、存在しない日付を推測して登録してはならない。

年、年月、期間等の精度しか確認できない場合は、その情報精度を保持したまま移行できる構造とする。

Performance等の既存Domainが完全な日時を要求する場合は、Historical Migration用の入力・保持方法を別途詳細設計で定義し、架空の日時をBusiness Factとして生成しない。

---

# Historical Participants

過去公演の出演者・スタッフも、可能な範囲で通常のParticipantとして移行する。

過去公演に参加していたPersonが現在OrganizationのMembershipを持っていない場合でも、過去ProductionのParticipantとして履歴を保持できるものとする。

基本構造：

Production
    ↓
Participant
    ↓
Person

過去ProductionのParticipantであることによって、現在のOrganization Membershipや現在のProductionへの権限を自動的に付与してはならない。

Personとして本人を特定できない場合は、資料に存在する表記を無理に既存Personへ統合してはならない。Identity Resolutionのルールは別途定義する。

---

# Historical Public Page

MIGRATED Productionは、Public VisibilityをONにすることで、通常の過去公演Public Pageとして公開できる構造とする。

StageArt導入前の公演であることだけを理由として、Public Pageの対象外としてはならない。

基本構造：

Historical Production
    ↓
Public Visibility = ON
    ↓
Production Public Page
    ↓
Archive

過去公演ページでは、現在公演向けの予約導線ではなく、作品記録としての情報を中心に表示する。

Flyer、作品紹介、出演・スタッフ、会場、公開可能な公演情報、公開承認済みの観客アンケート抜粋等、移行できた情報を活用する。

---

# Historical Survey / Audience Feedback

StageArt導入前に紙、ファイル、既存システム等で収集されていた観客アンケートや感想についても、権利・同意・個人情報等の公開条件を満たす場合は、過去公演の公開情報として移行できる構造とする。

過去アンケートを移行した場合も、回答者の意思確認なく自動的にPublic Pageへ公開してはならない。

公開対象として選定・承認されたコメントのみをPublic Pageへ表示する。

過去アンケートの原本をすべてStageArt Domainへ取り込むことを必須とはしない。

---

# Historical Flyer / Document

StageArt導入前のFlyer、画像、台本、資料等については、Productionに関連するHistorical Documentとして移行できる構造を妨げない。

ただし、Documentを移行したことによって、その内容すべてがPublic Pageへ公開されることを意味しない。

Public Pageへ表示する情報はPublic Visibilityおよび各情報の公開可否に従う。

---

# Migration Source

過去公演を移行する場合、可能であれば移行元を識別できる情報を保持する。

例：

- 既存Excel
- CSV
- Word
- PDF
- 旧Webサイト
- 旧管理システム
- 紙資料

Migration Sourceは、ProductionのBusiness Factそのものとは別のMigration Metadataとして扱う。

移行元資料の存在を理由に、元資料の内容をStageArtのBusiness Factへ無条件にコピーしてはならない。

---

# Migration and Source of Truth

移行完了後、StageArt上で正規化された情報については、Productionおよび関連DomainがSource of Truthとなる。

Public Page専用に過去公演情報を二重管理してはならない。

例えば、過去公演のタイトル、会場、出演者等をPublic Page専用Entityへ複製して管理する設計を基本としない。

---

# Migration Correction

過去公演を移行した後でも、管理者は移行内容を修正できるものとする。

ただし、修正によって元資料に存在しなかった事実を創作してはならない。

移行後にStageArt上で追加された情報は、Historical Migration時点の情報とは区別できる構造を妨げない。

---

# Migration and Public Visibility

Historical MigrationとPublic Visibilityは独立した概念として扱う。

過去公演をStageArtへ移行しただけで、自動的に一般公開してはならない。

基本Flow：

Historical Migration
    ↓
Productionとして登録
    ↓
内部履歴として保持
    ↓
管理者が公開内容を確認
    ↓
Public Visibility = ON
    ↓
Public Archive

公開したくない過去公演は、MIGRATED Productionとして内部に保持したままPublic VisibilityをOFFにできる。

---

# Business Rules

- StageArt利用開始以前の過去公演をProductionとして移行できる。
- Historical専用の別Production Entityを基本としない。
- 移行した過去公演も通常のProduction Domainで管理する。
- Production OriginとしてCREATED_IN_STAGEART / MIGRATED等を識別できる構造を許容する。
- MIGRATEDであることはProductionのBusiness Identityではない。
- 過去公演を移行するために、過去のLifecycleを最初から再演する必要はない。
- 既に完了している過去公演は、移行時点でCOMPLETED / ARCHIVEDとして扱える。
- 過去資料に存在しない情報を推測してBusiness Factとして登録してはならない。
- 過去公演は部分的な情報でも移行できる。
- 不正確な日時しか分からない場合、架空の正確な日時を生成してはならない。
- 過去公演の出演者・スタッフは、可能な範囲で通常のParticipantとして移行する。
- 過去ProductionのParticipantであることから現在のMembershipやPermissionを自動付与してはならない。
- StageArt導入前のProductionもPublic VisibilityをONにすることでPublic Archiveとして公開できる。
- Historical MigrationだけでPublic Visibilityを自動的にONにしてはならない。
- 過去アンケート・感想は、公開条件を満たし管理者が公開対象として承認したものだけをPublic Pageへ表示する。
- 過去Flyer、画像、台本、資料等をHistorical Documentとして移行できる構造を妨げない。
- Migration SourceはMigration Metadataとして扱い、Business Factと混同しない。
- 移行後の正規化されたBusiness FactのSource of Truthは各Domainとする。
- Public Page専用の過去公演情報を二重管理しない。
- 移行後も管理者は移行内容を修正できる。
