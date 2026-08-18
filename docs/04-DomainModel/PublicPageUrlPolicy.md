# StageArt Blueprint

# Public Page / URL Policy

Version : 1.0

---

# Purpose

OrganizationおよびProductionのPublic Pageと、その公開URLを構成するPublic Slugの基本設計を定義する。

Public PageはStageArtのCore機能として自動生成され、StageArtに登録された公開対象のBusiness Factから構成される。

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

# Public Page Generation

Public Pageは、OrganizationまたはProductionの登録情報から自動生成される。

Public Page専用のBusiness Factを、元のOrganization / Production情報と二重管理しない。

Public PageはRead Model / Public Artifactとして構成できるが、公開対象のBusiness FactのSource of Truthは各Domain側に保持する。

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
- Public Pageには内部管理情報を公開しない。
- Public Slug変更時の詳細なRedirect Policyは別途定義する。
