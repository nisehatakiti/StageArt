# StageArt Blueprint

# Domain Model : Production Participant Affiliation Policy

Version : 1.0

---

# Purpose

Production Participantにおける、PersonのOrganization所属表示と、Organization Membershipの承認関係を定義する。

StageArtでは、Productionへの参加関係とOrganizationへの所属関係を分離する。

そのため、Production Participantは、所属Organizationを持つPersonと、所属Organizationを持たないPersonの双方を扱える。

---

# 1. Production Participant and Organization Membership

Production ParticipantとOrganization Membershipは別のDomainである。

Membership：

PersonがOrganizationに所属しているというFact。

Participant：

PersonまたはOrganizationがProductionへ参加しているというFact。

基本構造：

Person
    ↓
Membership
    ↓
Organization

Person
    ↓
Participant
    ↓
Production

Productionへの参加を理由として、Organization Membershipを自動作成してはならない。

---

# 2. Organization Affiliation Approval

PersonのOrganization所属は、Organization側の承認によって成立する。

Personが自称する所属情報だけでは、StageArt上の正式なOrganization所属とはみなさない。

基本Flow：

Person
    ↓
Organizationへの所属申請
    ↓
Membership = REQUESTED
    ↓
Organization側の適切な権限を持つPersonが承認
    ↓
Membership = ACTIVE

MembershipのApprovalについては、Membership DomainのApproval Ruleを正本とする。

---

# 3. Production Public Credit Affiliation Display

Production Public Pageの出演者・スタッフ表示では、Production Participantを基準とする。

Person Participantについて、Production時点で有効なOrganization Membershipがある場合は、次の形式で表示できる。

    Person Name（Organization Name）

Organization Membershipがない場合は、次の形式とする。

    Person Name

例：

    山田太郎（劇団クジラ）
    佐藤花子（劇団イルカ）
    鈴木一郎

この表示は、Productionへの参加と、そのPersonの所属Organizationを同時に表現するための公開Creditである。

---

# 4. External / Freelance Participant

Organization Membershipを持たないPersonもProductionへ参加できる。

この場合、Production Public PageではOrganization名を付加せず、Person Nameのみを表示する。

客演、フリーランス、単発参加者、プロデュース公演等における外部参加者を特別なParticipant Entityで管理しない。

既存のParticipant構造で表現する。

---

# 5. Multiple Organization Affiliation

Personは複数のOrganizationへ所属できる。

Production Participantの公開所属表示に使用するOrganizationは、当該Productionに対して公開上の所属として扱うべきMembershipを基準とする。

複数Organization Membershipが存在するPersonについて、どの所属をProduction Creditへ表示するかは、Production参加時点の所属関係を基準に確定できる設計とする。

同一Personが複数Organizationに所属している場合に、Organization名を無条件に複数表示することを基本としない。

具体的な複数所属の選択・確定UIは、Production Participant Managementで定義する。

---

# 6. Historical Credit Preservation

Production Public Pageは過去公演のアーカイブとして利用されるため、現在のMembership状態の変更によって、過去公演のCredit表示を不意に書き換えてはならない。

例：

2026年
    山田太郎（劇団クジラ）

2028年
    山田太郎（劇団イルカ）

この場合、2026年のProduction Public Pageを2028年のMembership変更によって「劇団イルカ」に変更してはならない。

Production Participantの公開Creditは、Production参加時点の所属表示を履歴として保持できる設計とする。

Person Profileや現在のMembershipをSourceとして参照する場合でも、過去Productionの公開Creditを後から意図せず変更しない。

---

# 7. Production Participant Credit

Production Participantは、Productionごとの出演・スタッフ等のCreditを管理する。

Creditには少なくとも以下を扱える構造とする。

- Person Name
- Organization Affiliation Display
- Participant Type
- Credit Name
- Credit Order
- Visibility

Person Profileの基本情報をParticipantへ無条件に複製しない。

Production固有のCredit表示が必要な場合のみ、Participant側でProduction上の表示情報を保持する。

---

# 8. Public Visibility

Production Participantの公開可否はParticipant Visibilityで管理する。

Participantが非公開の場合、Production Public Pageの出演者・スタッフ表示に掲載しない。

Organization Membershipの公開可否とParticipant Visibilityは別の概念である。

Organizationに所属していること自体が、すべてのProductionでの公開を意味しない。

---

# 9. Organization Member Pageとの分離

Organization Public Member Pageは、現在のOrganization Membershipを基準として表示する。

Production Public Pageの出演者・スタッフ表示は、Production Participantを基準として表示する。

したがって、以下を明確に分離する。

Organization Member Page
    = 現在のOrganization Membership

Production Cast / Staff
    = 当該ProductionのParticipant

退団したPersonは現在のMember Pageから除外できるが、過去ProductionのParticipantとしての参加履歴は保持する。

---

# 10. Producer / Ensemble Change

プロデュース公演、合同公演、企画公演など、Productionごとに座組が大きく変わるケースを標準的に扱えること。

Production ParticipantはOrganization Membershipを必須条件としない。

そのため、あるProductionではOrganization A所属のPerson、別のProductionではOrganization B所属のPerson、または所属なしのPersonが同じProductionに参加できる。

ProductionごとのParticipant構成によって、当該公演の実際の座組を表現する。

---

# 11. Management Rule

Organization側は、自OrganizationのMembershipについて承認・変更・終了を管理する。

Production側では、Production Manager等の適切な権限を持つPersonがParticipantを登録・管理する。

Production Participantを登録したことだけでは、PersonのOrganization Membershipを変更してはならない。

Organization Membershipを変更する場合は、Membership Domainの承認・Lifecycle Ruleに従う。

---

# 12. Design Principle

StageArtでは、

「誰が劇団に所属しているか」

と

「誰がその公演に参加したか」

を別の事実として管理する。

これにより、通常の劇団公演だけでなく、客演、合同公演、プロデュース公演、外部スタッフ参加などを同じ構造で表現できる。
