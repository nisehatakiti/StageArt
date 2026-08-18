# StageArt Blueprint

# Domain Model : Organization Consistency Policy

Version : 1.0

---

# Purpose

本書は、DomainMap Version 5.0で定義したCanonical Domain Structureと、Organization Domainに関する既存設計を整合させるための補足仕様である。

Organization.mdの既存記述と本書が矛盾する場合は、本書およびDomainMapの最新確定仕様を優先する。

---

# 1. Organization Role

OrganizationはStageArtにおけるTenantであり、団体全体のBusiness Dataを管理するScopeである。

Organizationは劇団に限定せず、舞台芸術活動を行う任意の団体を表現する。

Organization内の権限はPersonの属性ではなくMembershipを介してRoleを適用する。

UserAccountはAuthentication Identityであり、Organizationの権限主体ではない。

---

# 2. Organization Owner

Organization登録時の代表者・Ownerは、登録を実行したUserAccountに対応するPersonを初期Ownerとする。

Organization OwnerはOrganization Scopeの管理者であり、ProductionのPrimaryManagerとは別概念である。

Owner変更はOrganization Scopeの権限変更として扱う。

OrganizationにOwnerIdを直接保持することをCanonical Modelとしない。

---

# 3. Membership

MembershipはPersonとOrganizationの所属関係を表す。

一人のPersonは複数Organizationに所属できる。

Organization側から見たMember情報はMembershipを正本とする。

所属の承認・退団・所属状態の変更はMembershipのLifecycleとして扱う。

Productionにおける参加関係はMembershipではなくParticipantで管理する。

---

# 4. Organization and Production

ProductionはOrganizationに直接所属しない。

Canonical Structureは以下とする。

Organization
    ↓
Project
    ↓
Production

Production関連DomainもProductionを経由してOrganization Scopeを解決する。

OrganizationがProductionを直接保持する構造を新規に追加してはならない。

---

# 5. Project

ProjectはOrganizationに所属する活動・企画単位である。

一つのProjectは一つ以上のProductionを持つことができる。

通常の単独公演では、利用者がProjectを強く意識する必要はない。

一方、東京公演・大阪公演など複数のProductionを一つの企画として管理する場合は、同一Projectに複数Productionを所属させる。

Production作成時には、既存Projectを選択するか、新しいProjectを作成できるものとする。

---

# 6. Organization Setup

Organization登録はSetup Wizardで実行する。

基本的な流れは以下とする。

1. Google等の外部Authentication連携、Timezone、Notification等の初期設定
2. Organization Name入力
3. Organization Slug入力および一意性確認
4. Logo、Description等のOrganization情報入力
5. Accountingを有効化するか選択
6. Accountingを有効化した場合、現金と預金を分けた初期流動資産を入力
7. Organization登録完了

Organization登録を実行したPersonを初期代表者・Ownerとする。

会計設定やExternal Connection等は後から変更可能なものとする。

---

# 7. Organization Slug

Organizationは公開ページURLに使用するSlugを持つ。

基本URL：

/StageArt/[Organization Slug]/

SlugはOrganization単位で一意でなければならない。

Slug変更時は、既存公開URLへの影響を考慮した運用を別途定義する。

OrganizationIdは内部識別子として維持し、Slugを内部主キーとして使用しない。

---

# 8. Organization Public Page

Organization Public PageはOrganizationの公開情報を表示する。

基本構成は、次回公演とSNS情報を中心とする。

トップページでは、次回公演を主要コンテンツとして表示し、SNS関連情報を配置する。

ABOUTおよびMEMBERは上部メニューから参照できる構成とする。

過去公演はトップページには最新1件のみを表示する。

過去公演一覧は別途参照できるものとする。

Organization Public Pageには、内部のAccounting、Credential、Permission等を表示しない。

---

# 9. Organization Public Production Navigation

Organization Public PageからProduction Public Pageへ遷移できる。

Production Public PageのURLは、Canonical URL Structureに従う。

基本URL：

/StageArt/[Organization Slug]/[Production Slug]/

Organization SlugとProduction Slugは、それぞれのScopeで一意性を確認する。

---

# 10. SNS

SNS情報はOrganizationに関連するPublic Informationとして管理する。

SNS連携はOptionとして扱い、設定した場合のみ利用できる。

Organization Public Pageでは、設定されたSNS情報を表示できる。

SNSの認証情報等のCredentialはPublic Informationに含めない。

---

# 11. Contact

Organization Public PageにおけるContact機能はOptionalとする。

StageArt上の連絡用アドレスを利用して団体の実メールアドレスを公開しない運用を将来的に提供できるものとする。

Contactはスパム等の状況に応じてOrganization管理者がOFFにできる設計を想定する。

Contact機能はCore Domainとして必須にはしない。

---

# 12. Organization Images

Organizationが保持する主要画像は団体ロゴ1個とする。

画像アップロード時はStageArtの共通画像処理ルールに従い、長辺1600pxを基準として正規化し、表示用サムネイル600pxを生成する。

Public Pageでは必要に応じてサムネイルを利用する。

---

# 13. Organization Members

Organization Public PageのMember情報は、OrganizationのMembershipおよび関連するPerson情報から生成する。

Memberページでは、Personの公開可能なプロフィール情報のみを表示する。

退団者については、その時点の所属状態・表記を過去のProduction上のスナップショットとして保持する。

複数Organization所属はPerson / Membershipとして表現できるが、OrganizationのMember表示において複数所属を特別扱いする初期仕様は設けない。

---

# 14. Accounting Scope

Organization Accountingは団体全体の財務状況を表す。

Projectは企画全体の予実管理、Productionは個別公演の決算・収支確認を担当する。

基本構造：

Organization
    ↓
Project
    ↓
Production

Accountingの正本はJournal Entryとし、Organization / Project / Productionで同一Actualを二重管理しない。

Project BudgetはProject Scopeの計画値、Production BudgetはProduction Scopeの計画値として扱う。

---

# 15. External Connections

Organization Scopeでは外部サービスとのConnectionを管理できる。

Google Account等のExternal ConnectionはSetup Wizardで初期設定できる。

External ConnectionはOrganization単位で管理し、Connection固有のCredentialをPublic Informationに含めない。

External Connectionの利用可否は、対象機能のOption設定に従う。

---

# 16. Notification

NotificationはOrganization単位の設定対象とする。

通知機能はOptionとして扱い、Organization管理者がON/OFFを設定できる。

Notification以外のCore機能について、Organizationごとに不要なON/OFF設定を乱立させない。

---

# 17. Scope Rule

Organization ScopeのDataは、Organizationに属する。

Project ScopeのDataはProjectを通じてOrganization Scopeへ帰属する。

Production ScopeのDataはProduction → Project → Organizationの経路でScopeを解決する。

このScope解決ルールを、各Domainで重複定義してはならない。

---

# 18. Canonical Summary

Organizationは団体全体のTenant Scopeである。

Projectは企画・活動単位である。

Productionは具体的な公演単位である。

MembershipはOrganizationへの所属を表す。

ParticipantはProductionへの参加を表す。

ProductionDelegateはProduction Scopeの管理権限を表す。

Organization Public PageはOrganizationの公開情報から生成する。

Organization Accountingは団体全体の財務状況を表示する。

Project Accountingは企画全体の予実を表示する。

Production Accountingは個別公演の決算を表示する。
