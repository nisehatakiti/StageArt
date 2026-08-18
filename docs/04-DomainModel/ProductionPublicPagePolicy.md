# StageArt Blueprint

# Production Public Page Policy

Version : 1.2

---

# Purpose

Production Public Pageの情報構成、Navigation、観客向け情報の優先順位、およびResponsive Layoutを定義する。

Production Public Pageは、Productionを観客に紹介し、観劇に必要な情報を確認してチケット予約へ進むためのPublic Pageとして扱う。

Organization Public Pageが「劇団そのもの」を見せる公式ページであるのに対し、Production Public Pageは「この公演を観に行くためのページ」として情報を絞る。

---

# Public Visibility

ProductionがStageArt内部に存在することと、Production Public Pageを一般公開することは別の概念として扱う。

Productionには、管理者がそのProductionの過去公演Public Pageを公開するかどうかを制御できるPublic Visibility設定を持たせる。

Public VisibilityがOFFの場合、Productionの内部データおよびLifecycle履歴は保持したまま、一般観客向けのPublic Pageからは表示しない。

Public Visibilityの判断は、Productionの内容、集客状況、活動方針その他の運用上の理由を問わず、Production管理者が行えるものとする。

Public VisibilityはProduction Lifecycleとは独立した設定として扱う。

ProductionがCOMPLETEDまたはARCHIVEDになったことだけを理由として、Public Visibilityを自動的に変更してはならない。

Public VisibilityがOFFのProductionは、Organization Public PageのLatest Past ProductionおよびPRODUCTIONS一覧等の公開Production一覧にも含めない。

Public VisibilityがOFFのProductionについて、Public URLを直接指定された場合も一般公開ページとして表示してはならない。公開対象外のProductionの存在を、Public Page上から推測できる情報も表示しない。

Public VisibilityをONに戻した場合は、Productionの既存Business FactからPublic Pageを再び生成・表示できる構造とする。

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

---

# Performance Information

公演日時はProductionに登録されたPerformance等の正規Business Factから自動生成する。

Public Page専用の日時入力を別途要求してはならない。

複数Performanceが存在する場合は、日付・時刻順に整理して表示する。

Organization Timezoneを基準としてBusiness DateTimeを解釈する。

---

# Ticket / Reservation

Ticket / Reservationを利用するProductionでは、公演トップにチケット料金と予約導線を表示する。

予約機能を利用していないProductionでは、予約ボタン等の存在しない機能への導線を表示してはならない。

Ticket / ReservationはCore機能として扱うが、Productionごとに利用するかどうかを設定できる。

「チケットを予約する」等の主要予約CTAは、公演トップ上で視認性の高い位置に配置する。

スマートフォン向けには、必要に応じて画面下部に固定CTAを表示できる構造とする。

---

# Venue / Access

公演トップには会場名と所在地を表示する。

必要に応じて外部地図サービス等へのアクセス導線を表示できる。

Venue情報はProductionに登録された正規Business FactをSource of Truthとし、Public Page専用の会場情報を二重管理しない。

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

Production終了後もProduction Public Page自体は原則として保持し、過去公演の公開アーカイブとして閲覧可能な構造とする。

ただし、Production Public Pageの公開可否は、管理者が設定するPublic Visibilityによって制御する。

過去公演ページは、現在公演の予約促進だけではなく、その公演がどのような作品であったかを記録し、観客の反応を残すためのArchive Pageとして扱う。

過去公演ページでは、現在公演時の基本情報に加えて、公開対象として承認された観客アンケートの抜粋を掲載することを必須要件とする。

アンケートの抜粋は、Productionに紐づくアンケート回答から管理者が公開対象として選定・承認したものだけを表示する。

アンケート回答を回答者の意思確認なく自動的に公開してはならない。

アンケートの入力方式、質問構成、回答収集方法等の詳細は、Survey / Questionnaire Domainの設計で定義する。本Policyでは、過去公演ページへの公開抜粋が必須であることのみを定める。

Production終了後は、予約CTA等の現在公演向け導線は表示しない。

Organization Public Pageでは、Public VisibilityがONである終了済みProductionのみをLatest Past Productionとして扱う。

---

# Survey Excerpt Display

過去公演ページには、観客アンケートから選定・承認されたコメント抜粋を表示する領域を設ける。

表示は「アンケート結果の統計値」だけを目的とせず、観客が実際に記した感想を公演アーカイブの一部として伝えることを基本とする。

公開コメントについて、回答者の氏名その他の個人情報を公開するかどうかは、別途定義される公開・同意ルールに従う。

StageArtは、公開ページ表示のために承認済みの公開情報を参照するが、Public Page専用に同じアンケート本文を二重管理してはならない。

---

# Production Public Page Lifecycle

Productionが次回公演として扱われている間は、Organization Public PageからProduction Public Pageへの主要導線を提供する。

Production終了後もProduction Public Page自体は原則として保持し、過去公演の公開アーカイブとして閲覧可能な構造を基本とする。

ただし、終了後のPublic Page表示はProductionのPublic VisibilityがONであることを条件とする。

過去公演ページでは、予約促進を目的とした現在公演向けCTAを表示せず、作品記録としての情報と公開承認済みアンケート抜粋を中心に構成する。

Public VisibilityがOFFの場合、Productionは内部データとして保持するが、Public Pageからは表示しない。

---

# Responsive Layout

Production Public PageはDesktopとMobileの両方を前提とする。

Desktop等の広い画面では、Performance / Ticket / Venueを横並びの3カラムとして表示する。

Mobile等の狭い画面では、同じ3情報を縦積みに変更し、情報の意味・順序を維持する。

Layout変更によってBusiness Factの内容を変えてはならない。

---

# Public Page Generation

Production Public PageはProductionおよび関連する公開対象Business Factから自動生成される。

Public Page専用のBusiness Factを元Domainと二重管理しない。

管理者がHTML等を直接編集するCMS機能はProduction Public Pageの基本要件としない。

Production設定を変更すると、同じSource of Truthから生成されるPublic Pageへ反映される。

Public Visibilityの変更はPublic Pageの公開・非公開状態に反映するが、ProductionのBusiness Data自体を変更・削除しない。

過去公演ページのアンケート抜粋も、承認済みのSurvey情報をSource of Truthとして表示し、Public Page専用のアンケート本文を二重管理しない。

---

# Business Rules

- Production Public PageはCore機能として自動生成する。
- Production Public Pageは観客が公演を理解し、予約・来場に必要な情報を確認するためのページとする。
- Productionの内部存在とProduction Public Pageの公開状態は別概念として扱う。
- Productionには管理者がPublic Pageの公開・非公開を制御できるPublic Visibility設定を持たせる。
- Public VisibilityはProduction Lifecycleとは独立して管理する。
- Public VisibilityがOFFのProductionはPublic Pageから表示しない。
- Public VisibilityがOFFでもProductionの内部データ、履歴、Lifecycle状態等は削除・変更しない。
- Public VisibilityがOFFのProductionはOrganization Public PageのLatest Past ProductionおよびPRODUCTIONS一覧等の公開Production一覧に含めない。
- Public VisibilityがOFFのProductionを直接URLで指定された場合も、一般公開ページとして表示しない。
- Public VisibilityをONに戻した場合は、既存のProduction Business FactからPublic Pageを再表示できる構造とする。
- Production Public Pageの主役はFlyer / Main Visualとする。
- Production Titleと作・演出は公演トップに基本表示する。
- 公演概要および出演・スタッフの詳細情報は専用ページから表示する。
- 公演日時、チケット、会場は基本3カラム情報ブロックとして表示する。
- 広い画面ではPerformance / Ticket / Venueを横並びにする。
- 狭い画面ではPerformance / Ticket / Venueを縦積みにするResponsive Layoutとする。
- 公演日時はProductionの正規Business Factから自動生成し、Public Page専用の日時情報を二重管理しない。
- Organization Timezoneを基準としてBusiness DateTimeを解釈する。
- Ticket / Reservationを利用しているProductionでは公演トップから予約導線を提供する。
- Ticket / Reservationを利用していないProductionでは存在しない予約機能への導線を表示しない。
- Venue情報はProductionの正規Business Factから表示し、Public Page専用の会場情報を二重管理しない。
- Production終了後もProduction Public Pageは原則として公開アーカイブとして保持する。
- 過去公演ページには、管理者が公開対象として選定・承認した観客アンケートの抜粋を掲載することを必須とする。
- アンケート回答を自動公開してはならない。
- 過去公演ページでは現在公演向けの予約CTAを表示しない。
- アンケートの入力・収集・質問構成等はSurvey / Questionnaire Domainで別途定義する。
- Public Pageには内部管理情報を公開しない。
