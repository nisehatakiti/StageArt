# StageArt Blueprint

# Production Public Page Policy

Version : 1.4

---

# Purpose

Production Public Pageの情報構成、Navigation、観客向け情報の優先順位、Responsive Layout、およびProduction作成時の公開導線を定義する。

Production Public Pageは、Productionを観客に紹介し、観劇に必要な情報を確認してチケット予約へ進むためのPublic Pageとして扱う。

Organization Public Pageが「劇団そのもの」を見せる公式ページであるのに対し、Production Public Pageは「この公演を観に行くためのページ」として情報を絞る。

---

# Production Setup Wizard

Productionの新規登録は、現在・これからの公演と過去公演で基本的に同一のSetup Wizardを利用する。

StageArt導入前の過去公演についても、同じ基本フローでProductionを登録できる。過去公演専用の別データ構造を作らず、Productionとして登録した上で必要な情報を入力する。

過去公演登録の入口が別である場合でも、Production Setup Wizardの入力構造・基本的な順序は共通とする。

基本的なSetup Wizardは以下とする。

1. Production基本情報
2. 情報公開設定
3. 会計・予算設定（Organizationの会計がONの場合のみ）
4. Member登録
5. Venue登録
6. Ticket設定
7. Performance登録
8. 完了

各Stepで入力した情報は、Production登録完了後も通常のProduction管理画面から追加・変更できる。

---

# Production Basic Information Setup

Production基本情報として少なくとも以下を登録できる。

- Production Title
- Production Overview
- Production Slug
- Flyer Front
- Flyer Back
- Production Manager

Flyer Front / Backは登録時点でアップロードでき、未登録のまま後から追加することもできる。

Production ManagerはStageArtアカウントを所持するPersonのみ指定可能とする。

Production ManagerはProductionの管理責任者であり、後から別のStageArtアカウント所持者へ移譲できる。

Production Managerの移譲によってProductionのBusiness Data、Lifecycle、Publicity、Participant等を変更・削除してはならない。

Production Managerとは別に、既存のProduction Delegate Policyに基づく代理人・委任権限を設定できる。

---

# Information Publication at Production Creation

Production作成時に、一般への情報公開を開始するかどうかを選択できる。

基本的な選択肢は以下とする。

- 情報公開しない
- 情報公開する

「情報公開しない」を選択した場合、ProductionのPublicity StatusはPRIVATEとする。

「情報公開する」を選択した場合、Production Managerによる明示的な情報公開GOとして扱い、Publicity StatusをPUBLICへ変更できる。

Production作成時に公開しないことを選択しても、後からProduction Managerが情報公開を開始できる。

Production Slugが登録済みであることだけを理由として、Production Public Pageを一般公開してはならない。

---

# Accounting / Budget Setup

Organizationの会計管理がONの場合、Production Setup Wizardで予算を設定するかどうかを確認する。

選択肢は以下とする。

- 予算を設定する
- 今は設定しない

「予算を設定する」を選択した場合、Budget入力画面へ進む。

Budget入力画面の詳細な項目・Account構成等はBudget Domainの設計で定義し、本PolicyではSetup WizardからBudget設定へ遷移できることのみを定める。

予算を設定しない場合でもProduction登録を完了でき、BudgetはProduction登録後に設定・変更できる。

Organizationの会計管理がOFFの場合、このBudget設定Stepを表示しない。

---

# Member Setup

Production登録時にProduction Participant / Memberを登録できる。

ProductionごとのParticipantはOrganization Membershipとは独立して管理する。

Production Setup Wizardでは、出演者・スタッフ等のProduction参加者を登録し、必要に応じてProduction Managerから委任された代理人等を設定できる。

Production登録時点で全Memberが確定していることを要求してはならない。

MemberはProduction登録完了後も追加・変更できることを画面上で明示する。

---

# Venue Setup

Production登録時にVenueを登録できる。

Venue情報はProductionの正規Business Factとして管理し、Public Page専用のVenue情報を二重管理しない。

Venueが未確定の場合でも、Production登録そのものを妨げない。

---

# Ticket Setup

Production登録時にTicket情報およびReservationに必要な設定を登録できる。

Ticket / ReservationはCore機能として扱うが、Production登録時点で料金・予約情報等が確定していない場合でもProduction登録を妨げない。

未確定のTicket情報はProduction登録後に追加・変更できる。

---

# Performance Setup

Production登録時にPerformance（公演回）を登録できる。

PerformanceはOrganization Timezoneを基準としてBusiness DateTimeを管理する。

公演回が未確定の場合でもProduction登録を妨げない。

PerformanceはProduction登録後に追加・変更できる。

---

# Publicity and Lifecycle Separation

Productionの業務Lifecycleと、一般への情報公開状態は別の概念として扱う。

Production Lifecycleは、企画・制作・稽古・公演・精算・決算・Archive等の業務上の進行を表す。

Publicityは、Productionの情報を一般公開するタイミングと公開状態を表す。

Productionには、Lifecycle Statusとは独立したPublicity Statusを持たせる。

Publicity Statusは以下を基本とする。

| Publicity Status | 意味 |
|---|---|
| PRIVATE | 一般公開前。Productionは内部で管理されるが、Public Pageは一般公開しない |
| PUBLIC | 一般公開中。Production Public Pageを一般から閲覧できる |
| CLOSED | 一般公開を終了した状態。過去公演として残すかどうかはPublic Visibilityと組み合わせて管理する |

Publicity StatusはProduction Lifecycle Statusの代替ではなく、両者を一つのStatusに統合してはならない。

例えばPLANNING + PRIVATEは「公演準備中で、まだ一般発表していない」状態を表す。

PLANNING + PUBLICは「公演準備中だが、一般への情報公開を開始している」状態を表す。

Production作成時のPublicity StatusはPRIVATEを基本とする。ただしSetup Wizardで「情報公開する」を明示的に選択した場合はPUBLICへ遷移できる。

ProductionのLifecycleがACTIVEになったことだけを理由としてPublicityをPUBLICへ自動変更してはならない。

PublicityがPUBLICになったことだけを理由としてProduction LifecycleをACTIVEへ自動変更してはならない。

---

# Publicity Transition

Publicityの変更は、Production管理者による明示的なGOを基本とする。

基本Flow：

Production情報を準備
    ↓
管理者が一般公開可能と判断
    ↓
「情報公開」Action
    ↓
Publicity = PUBLIC
    ↓
Production Public Pageを生成・公開

情報公開前は、Public Pageを一般公開してはならない。

Slugが登録済みであっても、それだけを理由としてPublic Pageを一般公開してはならない。

情報公開後にProduction情報を更新した場合は、同じSource of Truthから公開ページへ反映する。

一般公開を終了する場合は、管理者の明示的なActionによってPublicityをCLOSEDへ変更できる。

Publicityの変更はProduction Business DataやLifecycleを削除・変更するものではない。

---

# Coming Soon Public Page

Publicity StatusがPUBLICであれば、Venue、Performance、Ticket等の詳細情報が未確定であってもProduction Public Pageを一般公開できる。

一般公開済みであることと、観客向け情報がすべて確定していることは別の概念として扱う。

Public Pageに必要な情報が未登録・未確定の場合は、ページ自体を非公開にするのではなく、該当情報を「Coming Soon」または「近日公開」として表示できる構造とする。

例えば、以下のような状態を許容する。

- Venue未登録 → 会場情報をComing Soon / 近日公開
- Performance未登録 → 公演日時をComing Soon / 近日公開
- Ticket未登録 → チケット情報をComing Soon / 近日公開

一部の情報が登録済みであれば、その情報は通常どおり表示し、未登録の情報だけをComing Soonとして表示する。

例えばProductionがPUBLICで、Flyer、Title、作・演出は登録済みだがVenue、Performance、Ticketが未確定の場合でも、Flyerと基本情報を表示した上で、日時・チケット・会場の各領域をComing Soonとして公開できる。

Coming SoonはPublicity StatusやPublic Visibilityを変更する状態ではなく、公開済みProductionにおける情報充足度の表示方法である。

「Coming Soon」であることを理由としてPublic Pageを自動的にPRIVATEへ戻してはならない。

---

# Public Visibility

ProductionがStageArt内部に存在することと、Production Public Pageを一般公開することは別の概念として扱う。

Productionには、管理者がそのProductionのPublic Pageを公開するかどうかを最終的に制御できるPublic Visibility設定を持たせる。

Publicity StatusがPRIVATEの場合、Public VisibilityがONであっても一般公開してはならない。

Publicity StatusがPUBLICで、かつPublic VisibilityがONの場合に、Production Public Pageを一般公開対象とする。

Publicity StatusがCLOSEDの場合は、一般公開を終了した状態として扱い、過去公演として残す場合の表示可否はPublic Visibilityで制御する。

Public VisibilityがOFFの場合、Productionの内部データおよびLifecycle履歴は保持したまま、一般観客向けのPublic Pageからは表示しない。

Public Visibilityの判断は、Productionの内容、集客状況、活動方針その他の運用上の理由を問わず、Production管理者が行えるものとする。

Public VisibilityはProduction LifecycleおよびPublicity Statusとは独立した設定として扱う。

ProductionがCOMPLETEDまたはARCHIVEDになったことだけを理由として、Public Visibilityを自動的に変更してはならない。

Public VisibilityがOFFのProductionは、Organization Public PageのLatest Past ProductionおよびPRODUCTIONS一覧等の公開Production一覧にも含めない。

Public VisibilityがOFFのProductionについて、Public URLを直接指定された場合も一般公開ページとして表示してはならない。公開対象外のProductionの存在を、Public Page上から推測できる情報も表示しない。

Public VisibilityをONに戻した場合は、Publicity StatusがPUBLICまたは過去公演として公開可能なCLOSEDであれば、既存Business FactからPublic Pageを再表示できる構造とする。

Public VisibilityはPublic Pageの公開制御であり、Productionそのものの削除、Archive、Lifecycle変更を意味しない。

---

# Public URL

Production Public Pageの基本URLは以下とする。

https://hatakiti.com/StageArt/[Organization Slug]/[Production Slug]/

Organization SlugおよびProduction Slugの詳細な一意性・Availability RuleはPublic Page / URL Policyで定義する。

---

# Production Public Page Top

Production Public Pageのトップページでは、以下の情報を優先する。

1. Flyer / Main Visual
2. Production Title
3. 作・演出
4. 公演日時
5. チケット情報・予約導線
6. 会場・アクセス

公演概要、出演・スタッフ等の詳細情報はトップページに全面展開せず、上部Navigationから専用ページへ遷移する。

---

# Header / Navigation

Production Public Pageには、少なくとも以下の公開導線を設ける。

- 公演トップ
- 公演概要
- 出演・スタッフ
- 劇団ページ

必要に応じてTicket、Contact等の導線を追加できるが、基本的な予約導線は公演トップから直接到達できる構造とする。

Production Public Pageから所属OrganizationのOrganization Public Pageへ戻れる構造とする。

---

# Flyer / Main Visual

Production Public Pageの主役はProductionのFlyer / Main Visualとする。

Flyer画像が登録されている場合は、ページ上部のHero領域に大きく表示する。

Flyer画像が登録されていない場合でも、Production Title、Organization、日時等のテキスト情報によってページが成立するレイアウトとする。

Production TitleはFlyer / Main Visualに続く主要情報として表示する。

作・演出はProductionトップに表示する基本情報とする。

---

# Performance / Ticket / Venue Three-Column Layout

Production Public Pageでは、公演日時、チケット、会場を基本3カラム情報ブロックとして表示する。

PC等の広い画面では、以下を横並びにする。

Performance
    公演日時

Ticket
    料金
    予約導線

Venue
    会場
    所在地
    地図・アクセス導線

この3カラムは、観客が「いつ」「いくら」「どこで」を一度に確認できることを目的とする。

スマートフォン等の狭い画面では、3カラムを自然な縦積みに変更するResponsive Layoutとする。

未登録の情報についてはComing Soon表示を利用できる。

---

# Performance Information

公演日時はProductionに登録されたPerformance等の正規Business Factから自動生成する。

Public Page専用の日時入力を別途要求してはならない。

複数Performanceが存在する場合は、日付・時刻順に整理して表示する。

Organization Timezoneを基準としてBusiness DateTimeを解釈する。

Performanceが未登録の場合、公開ページではComing Soon / 近日公開等の状態表示を行う。

---

# Ticket / Reservation

Ticket / Reservationを利用するProductionでは、公演トップにチケット料金と予約導線を表示する。

予約機能を利用していないProductionでは、予約ボタン等の存在しない機能への導線を表示してはならない。

Ticket / ReservationはCore機能として扱うが、Productionごとに必要なTicket情報を設定する。

「チケットを予約する」等の主要予約CTAは、公演トップ上で視認性の高い位置に配置する。

スマートフォン向けには、必要に応じて画面下部に固定CTAを表示できる構造とする。

Ticket情報が未登録の場合、公開ページではComing Soon / 近日公開等の状態表示を行う。

---

# Venue / Access

公演トップには会場名と所在地を表示する。

必要に応じて外部地図サービス等へのアクセス導線を表示できる。

Venue情報はProductionに登録された正規Business FactをSource of Truthとし、Public Page専用の会場情報を二重管理しない。

Venue情報が未登録の場合、公開ページではComing Soon / 近日公開等の状態表示を行う。

---

# Production Detail Pages

Production Public Pageの詳細情報は、少なくとも以下を別ページとして構成する。

- 公演概要
- 出演・スタッフ

公演概要ページには、作品紹介、あらすじ、見どころ、注意事項等の公開対象情報を表示できる。

出演・スタッフページには、公開対象となっているParticipant / Person情報を表示できる。

内部のMembership、Authorization、Permission等を公開してはならない。

---

# Past Production Archive

Production終了後も、公開状態にあるProduction Public Pageは過去公演の公開アーカイブとして閲覧可能な構造とする。

ただし、過去公演として公開するかどうかはPublic Visibilityによって管理者が制御できる。

過去公演ページでは、現在公演時の基本情報に加えて、公開対象として承認された観客アンケートの抜粋を掲載することを必須要件とする。

アンケートの抜粋は、Productionに紐づくアンケート回答から管理者が公開対象として選定・承認したものだけを表示する。

アンケート回答を回答者の意思確認なく自動的に公開してはならない。

Production終了後は、予約CTA等の現在公演向け導線は表示しない。

Organization Public Pageでは、Publicity StatusがCLOSEDまたはProduction Lifecycleが完了状態となったProductionのうち、Public VisibilityがONであるものをLatest Past Productionとして扱う。

---

# Survey Excerpt Display

過去公演ページには、観客アンケートから選定・承認されたコメント抜粋を表示する領域を設ける。

表示は「アンケート結果の統計値」だけを目的とせず、観客が実際に記した感想を公演アーカイブの一部として伝えることを基本とする。

公開コメントについて、回答者の氏名その他の個人情報を公開するかどうかは、別途定義される公開・同意ルールに従う。

StageArtは、公開ページ表示のために承認済みの公開情報を参照するが、Public Page専用に同じアンケート本文を二重管理してはならない。

---

# Production Public Page Lifecycle

Productionが次回公演として扱われている間は、Publicity StatusがPUBLICかつPublic VisibilityがONの場合にOrganization Public PageからProduction Public Pageへの主要導線を提供する。

Production終了後も、Publicity StatusおよびPublic Visibilityが公開条件を満たすProduction Public Pageは過去公演の公開アーカイブとして閲覧可能な構造を基本とする。

過去公演ページでは、予約促進を目的とした現在公演向けCTAを表示せず、作品記録としての情報と公開承認済みアンケート抜粋を中心に構成する。

PRIVATEのProductionはPublic Pageから表示しない。

---

# Responsive Layout

Production Public PageはDesktopとMobileの両方を前提とする。

Desktop等の広い画面では、Performance / Ticket / Venueを横並びの3カラムとして表示する。

Mobile等の狭い画面では、同じ3情報を縦積みに変更し、情報の意味・順序を維持する。

Layout変更によってBusiness Factの内容を変えてはならない。

---

# Public Page Generation

Production Public Pageは、Publicity StatusがPUBLICで、かつPublic VisibilityがONとなったProductionについて生成・公開する。

PRIVATEのProductionについて、一般公開用のProduction Public Pageを生成・公開してはならない。

Public PageはProductionおよび関連する公開対象Business Factから自動生成される。

Public Page専用のBusiness Factを元Domainと二重管理しない。

管理者がHTML等を直接編集するCMS機能はProduction Public Pageの基本要件としない。

Production設定を変更すると、同じSource of Truthから生成されるPublic Pageへ反映される。

PublicityまたはPublic Visibilityの変更はPublic Pageの公開・非公開状態に反映するが、ProductionのBusiness Data自体を変更・削除しない。

Coming Soon表示はPublic Pageの生成・公開を妨げず、未登録・未確定の情報領域に対する表示状態として扱う。

過去公演ページのアンケート抜粋も、承認済みのSurvey情報をSource of Truthとして表示し、Public Page専用のアンケート本文を二重管理しない。

---

# Business Rules

- Production Public PageはCore機能として自動生成する。
- Production Public Pageは観客が公演を理解し、予約・来場に必要な情報を確認するためのページとする。
- Productionの内部存在とProduction Public Pageの公開状態は別概念として扱う。
- Production LifecycleとPublicity Statusは別概念として管理する。
- Publicity StatusはPRIVATE / PUBLIC / CLOSEDを基本とする。
- Production作成時のPublicity StatusはPRIVATEを基本とし、Setup Wizardで明示的に公開を選択した場合はPUBLICへ遷移できる。
- Publicity Statusの変更はProduction管理者による明示的なGOを基本とする。
- PRIVATEのProductionは一般公開用Production Public Pageを公開してはならない。
- Slugが登録済みであっても、PRIVATEのProductionを一般公開してはならない。
- Publicity StatusがPUBLICで、かつPublic VisibilityがONの場合にProduction Public Pageを一般公開する。
- PUBLICであればVenue、Performance、Ticket等の一部情報が未確定でもPublic Pageを公開できる。
- 未登録・未確定の観客向け情報はComing Soon / 近日公開として表示できる。
- 一部の情報が登録済みであれば、その情報は通常表示し、未登録の情報だけをComing Soonとして表示する。
- Coming SoonはPublicity StatusやPublic Visibilityを変更する状態ではない。
- Publicity StatusがCLOSEDの場合は一般公開終了として扱い、過去公演として残す場合はPublic Visibilityで制御する。
- Public VisibilityはPublicity StatusおよびProduction Lifecycleとは独立して管理する。
- Public VisibilityがOFFのProductionはPublic Pageおよび公開Production一覧に含めない。
- Public VisibilityがOFFでもProductionの内部データやLifecycle履歴は削除しない。
- Public Pageは公開対象Business Factから自動生成し、Public Page専用のBusiness Factを二重管理しない。
- Production Lifecycleの変更だけでPublicityを自動変更してはならない。
- Publicityの変更だけでProduction Lifecycleを自動変更してはならない。
- Organization Timezoneを基準としてBusiness DateTimeを解釈する。
- Production Public Pageの主役はFlyer / Main Visualとする。
- Production Titleと作・演出は公演トップに基本表示する。
- 公演概要および出演・スタッフの詳細情報は専用ページから表示する。
- 公演日時、チケット、会場は基本3カラム情報ブロックとして表示する。
- 広い画面ではPerformance / Ticket / Venueを横並びにする。
- 狭い画面ではPerformance / Ticket / Venueを縦積みにするResponsive Layoutとする。
- Production Setup Wizardは現在公演と過去公演で基本的に共通化する。
- Production ManagerはStageArtアカウント所持者のみ指定でき、後から移譲できる。
- Production登録時にMember、Venue、Ticket、Performanceを登録でき、それぞれ登録後も追加・変更できる。
- Organizationの会計管理がONの場合、Production登録時にBudgetを設定するか選択できる。
- 過去公演ページには公開承認済みアンケート抜粋を掲載することを必須要件とする。
