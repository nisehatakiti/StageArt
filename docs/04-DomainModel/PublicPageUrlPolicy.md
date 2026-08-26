# StageArt Blueprint

# Public Page / URL Policy

Version : 1.2

---

# Purpose

OrganizationおよびProductionのPublic Pageと、その公開URLを構成するPublic Slug、およびOrganization Public Pageの基本的な情報構成を定義する。

Public PageはStageArtのCore機能として自動生成され、StageArtに登録された公開対象のBusiness Factから構成される。

Public Pageは、管理者がHTML等を直接編集するCMSではなく、StageArtのDomain情報を公開用に表示するRead Model / Public Artifactとして扱う。

---

# Public Page Structure

StageArtは、少なくとも以下の2種類のPublic Pageを持つ。

- Organization Public Page
- Production Public Page

Organization Public Pageは団体そのものを表す。

Production Public Pageは、Organizationに属する個別Productionを表す。

基本的な階層は以下とする。

Organization Public Page
    ↓
Production Public Page

---

# Public URL

基本URLは以下とする。

https://hatakiti.com/StageArt/[Organization Slug]/

https://hatakiti.com/StageArt/[Organization Slug]/[Production Slug]/

Organization Slugは公開URLの第1階層を構成する。

Production SlugはOrganization配下の公開URL第2階層を構成する。

Public URLには内部のOrganizationId / ProductionIdを使用しないことを基本とする。

---

# Public Slug and Internal ID

Public Slugと内部Entity IDは別の概念として管理する。

Organization：

- OrganizationId = 内部Business Identity
- Public Slug = 公開URL用識別子

Production：

- ProductionId = 内部Business Identity
- Public Slug = 公開URL用識別子

Public SlugをEntityの内部Identityそのものとして扱ってはならない。

---

# Organization Public Slug

Organization Public Slugは、Organization登録時に設定する。

利用者が自分のOrganizationに適した公開Slugを設定できる。

例えば、Organization名が「劇団クジラ」の場合、以下のようなSlugを設定できる。

kujira

この場合、Organization Public Pageは、

https://hatakiti.com/StageArt/kujira/

となる。

Organization Public SlugはOrganization Scopeで一意でなければならず、同じPublic Slugを複数Organizationで使用してはならない。

---

# Production Public Slug

Production Public Slugは、Production登録時に設定する。

例えば、Organization Slugがkujira、Production Slugがkappa-homerunの場合、Production Public Pageは、

https://hatakiti.com/StageArt/kujira/kappa-homerun/

となる。

Production Public SlugはOrganization内で一意でなければならない。

異なるOrganization間では、同じProduction Slugを使用できる。

例えば、以下は共存可能とする。

/StageArt/kujira/kappa-homerun/
/StageArt/example/kappa-homerun/

---

# Slug Availability

Public Slugは、登録時に利用可能性を確認する。

Client側では、入力中に利用可能性を確認するためのAvailability Checkを提供できる。

ただし、Client側のAvailability Checkは補助機能であり、最終的な一意性保証はServer Sideで行う。

同時登録などの競合が発生した場合でも、Server SideのBusiness RuleおよびPersistence Constraintによって重複登録を防止する。

---

# Setup Wizard Integration

Organization Setup Wizardでは、Organization基本情報の入力時にPublic Slugを設定する。

Production Setup Wizardでは、Production基本情報の入力時にProduction Public Slugを設定する。

Public Slugは公開ページを利用するための基本情報であり、登録時に未設定のまま自動生成だけに依存する設計を基本としない。

ただし、ユーザー入力を補助するため、Organization名やProduction名から候補Slugを自動生成して提示することは許容する。

---

# Organization Public Page Top Page

Organization Public Pageのトップページは、一般的な団体紹介ページではなく、そのOrganizationの現在の活動を見せる公式ページとして構成する。

トップページの最優先情報は次回公演とし、次回公演情報をHero / Main Visual領域として大きく表示する。

基本構成は以下とする。

1. Header / Navigation
2. Next Production（次回公演）
3. SNS / Social
4. Latest Past Production（最新の過去公演1件）
5. Footer / Contact

ABOUTおよびMEMBERSはトップページに詳細表示せず、上部Navigationから専用ページへ遷移する。

PRODUCTIONSは上部Navigationから公演一覧へ遷移できるものとする。

---

# Organization Public Page Navigation

上部Navigationには、少なくとも以下のような公開ページへの導線を設ける。

- ABOUT
- MEMBERS
- PRODUCTIONS

必要に応じてContact等の公開導線を追加できる。

トップページ内にすべての情報を展開せず、詳細情報はNavigationから辿れる構造とする。

---

# Next Production

Organization Public Pageでは、公開対象となっている次回Productionをトップページの中心的な情報として表示する。

次回公演は、Production Public Pageへの主要導線を持つ。

Ticket / Reservationを利用しているProductionでは、Production Public Pageから予約導線へ遷移できる構造とする。

次回公演が存在しない場合でも、Organization Public Page自体は成立するものとする。空のHero領域を前提とせず、適切な代替表示を行う。

---

# SNS / Social

Organization Public Pageの下部にはSNS / Social領域を設ける。

SNSはトップページの主要情報の一つとして扱うが、次回公演情報を主役とし、SNSだけをトップページの中心にしない。

SNS / Social領域は、Organizationが登録した外部SNS / 外部リンクを表示する。

外部サービスとのAPI連携を設定していない場合でも、単純なリンクカードとして表示できる構造を基本とする。

外部サービスとのAPI連携を設定した場合は、将来的に最新投稿等を表示できる構造を妨げない。

SNS API連携そのものはPublic Page Coreではなく、External Integration Optionの範囲として扱う。

---

# Latest Past Production

Organization Public Pageのトップページには、Public VisibilityがONである終了済みProductionのうち、最新のProductionを1件だけ表示する。

過去Productionをすべてトップページに並べてはならない。

Public VisibilityがOFFのProductionはLatest Past Productionの候補から除外する。

過去Productionの全履歴は、Public VisibilityがONのProductionのみを対象として、PRODUCTIONSページから一覧として閲覧できる構造とする。

これにより、トップページは現在の活動を中心とし、過去実績は最小限の表示に留める。

---

# Production Public Visibility

ProductionがStageArt内部に存在することと、Production Public Pageを一般公開することは別の概念として扱う。

Productionには、管理者がPublic Pageの公開・非公開を制御できるPublic Visibility設定を持たせる。

Public VisibilityがOFFの場合、Productionの内部データ、履歴、Lifecycle状態等は保持し、Productionそのものを削除・Archiveすることはしない。

Public VisibilityはProduction Lifecycleとは独立した設定として扱う。

ProductionがCOMPLETEDまたはARCHIVEDになったことだけを理由として、Public Visibilityを自動変更してはならない。

Public VisibilityがOFFのProductionは、Organization Public Page、PRODUCTIONS一覧、Latest Past Production等の一般公開対象から除外する。

Public VisibilityがOFFのProductionについてPublic URLを直接指定された場合も、一般公開ページとして表示してはならない。公開対象外のProductionの存在をPublic Page上から推測できる情報も表示しない。

Public VisibilityをONに戻した場合は、Productionの既存Business FactからPublic Pageを再び表示できる構造とする。

Public Visibilityの判断は、Productionの内容、集客状況、活動方針その他の運用上の理由を問わず、Production管理者が行えるものとする。

---

# ABOUT / MEMBERS

ABOUTおよびMEMBERSの詳細情報はOrganization Public Pageのトップページに全面展開せず、Navigationから専用ページへ遷移する。

公開対象として設定されたOrganization / Person情報のみを表示する。

Membership内部情報、Authorization情報、Role / Permissionの内部管理情報等を公開ページに表示してはならない。

---

# Public Contact

Organization Public Pageには、Organizationが希望する場合にPublic Contact導線を表示できる。

Public Contactは、StageArtが問い合わせ内容を管理する問い合わせ管理機能ではない。

基本方針は、Organization専用のStageArtメールアドレスを公開し、Organizationが指定した担当者の実メールアドレスへ転送する方式とする。

基本的な公開メールアドレス形式は以下とする。

[Organization Slug]@hatakiti.com

例えばOrganization Slugがkujiraの場合、

kujira@hatakiti.com

をPublic Contact Addressとして使用する。

問い合わせ受信後の返信は、転送先の担当者が通常使用しているメールアドレスから直接行うことを基本とする。

StageArtは問い合わせ本文、返信履歴、問い合わせスレッド等をDomainデータとして保存しない。

---

# Public Contact Enable / Disable

Public Contactは、スパム等の運用上の理由からOrganization管理者が停止できるものとする。

ContactをOFFにした場合、Organization Public PageからContact導線および公開メールアドレスを表示しない。

Public ContactのON/OFFは、Notificationのような一般的なFeature Toggleとは異なり、公開窓口の運用停止を目的とした例外的な設定として扱う。

Public Contactのデフォルト状態、およびConoHa側のメールアカウント自動生成・転送設定をStageArtからどこまで自動化するかは、インフラ詳細設計で確定する。

---

# Public Page Generation

Public Pageは、OrganizationまたはProductionの登録情報から自動生成される。

Public Page専用のBusiness Factを、元のOrganization / Production情報と二重管理しない。

Public PageはRead Model / Public Artifactとして構成できるが、公開対象のBusiness FactのSource of Truthは各Domain側に保持する。

管理者がHTML等を直接編集するCMS機能はPublic Pageの基本要件としない。

Organization設定やProduction設定を変更すると、同じSource of Truthから生成されるPublic Pageへ反映される。

Public Visibilityの変更はPublic Pageの公開・非公開状態に反映するが、ProductionのBusiness Data自体を変更・削除しない。

---

# Public Visibility

Public Pageが自動生成されることと、内部情報がすべて公開されることは同義ではない。

公開ページには、Public Informationとして明示された情報のみを表示する。

例えば以下の内部情報は公開してはならない。

- Permission
- Membership内部Status
- Accounting
- Budget
- Credential
- Internal Document
- Internal Notification
- その他Authorization Scope内の非公開情報

---

# Organization and Production Relationship

Organization Public Pageから、そのOrganizationに属するProduction Public Pageへ遷移できる構造とする。

ただし、Public VisibilityがOFFのProductionへの公開導線は生成しない。

Production Public Pageから、所属OrganizationのOrganization Public Pageへ戻れる構造とする。

Public PageのURL階層とDomainの所属関係は一致させる。

Organization
    ↓
Production

Organization Public Page
    ↓
Production Public Page

---

# URL Change

Public Slugは公開URLを構成するため、変更するとPublic URLも変更される。

Public Slugの変更可否、変更権限、変更時の旧URLの扱い、およびRedirect Policyは別途詳細設計で確定する。

少なくとも、Public Slug変更時に旧URLと新URLの関係が失われることを前提とせず、将来的なRedirect対応を妨げない設計とする。

---

# Business Rules

- Organization Public PageはCore機能として自動生成する。
- Production Public PageはCore機能として自動生成する。
- Organization Public PageのURLは`/StageArt/[Organization Slug]/`を基本とする。
- Production Public PageのURLは`/StageArt/[Organization Slug]/[Production Slug]/`を基本とする。
- Public Slugと内部Entity IDは別の概念として管理する。
- Organization Public SlugはOrganization登録時に設定する。
- Production Public SlugはProduction登録時に設定する。
- Organization Public SlugはOrganization間で一意とする。
- Production Public SlugはOrganization内で一意とする。
- Client側のAvailability Checkだけでは一意性を保証しない。
- Public Slugの最終的な一意性はServer Sideで保証する。
- Public Pageは公開対象として定義されたBusiness Factから生成する。
- Public Page専用のBusiness Factを元Domainと二重管理しない。
- Organization Public Pageのトップでは次回公演を最優先表示する。
- ABOUT / MEMBERSの詳細情報はトップページに全面展開せずNavigationから表示する。
- トップページに表示する過去Productionは、Public VisibilityがONである終了済みProductionから最新1件を基本とする。
- Public VisibilityがOFFのProductionは、PRODUCTIONS一覧、Latest Past Production、Production Public Page等の一般公開対象から除外する。
- Public VisibilityはProduction Lifecycleとは独立して管理する。
- Public VisibilityがOFFでもProductionの内部データ、履歴、Lifecycle状態等は削除・変更しない。
- Public VisibilityがOFFのProductionを直接URLで指定された場合も、一般公開ページとして表示しない。
- Public VisibilityをONに戻した場合は、既存のProduction Business FactからPublic Pageを再表示できる構造とする。
- SNS / Socialはトップページ下部の主要セクションとして表示する。
- SNS API連携はExternal Integration Optionとして扱い、連携なしでもリンク表示を可能とする。
- Public ContactはOrganizationが希望する場合に利用できる。
- Public Contactは`[Organization Slug]@hatakiti.com`を基本形式とする。
- Public Contactの問い合わせ本文・返信履歴をStageArtのDomainデータとして保存しない。
- Public Contactはスパム等の運用上の理由からOrganization管理者がOFFにできる。
- Public ContactをOFFにした場合は公開ページからContact導線を表示しない。
- Public Pageには内部管理情報を公開しない。
- Public Slug変更時の詳細なRedirect Policyは別途定義する。
