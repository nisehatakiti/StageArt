# StageArt Blueprint

# Domain Model : Follow

Version : 1.0
Status : Confirmed

---

# Purpose

Followは、PersonがOrganizationまたは公開対象のProductionに対して持つ「関心」を表すDomainである。

FollowはMembershipやParticipantとは異なる。

Followによって、OrganizationまたはProductionへの所属、参加、管理権限は一切発生しない。

StageArtでは、舞台芸術の実務関係者だけでなく、一般の観客も利用者として想定する。

そのため、実際に所属していないOrganizationに対しても、観客が継続的な関心を持ち、新しい公演情報を受け取れる構造を持つ。

---

# Core Principle

所属とフォローは明確に分離する。

```text
実際の活動関係
Person
  ↓
Membership / Participant / ProductionDelegate
  ↓
Organization / Production
```

```text
関心関係
Person
  ↓
Follow
  ↓
Organization / Production
```

FollowはAuthorizationを与えない。

FollowはTenant内部の情報へのアクセス権を与えない。

Followは公開情報およびFollow対象の更新情報を受け取るための関係である。

---

# Organization Follow

PersonはOrganizationをFollowできる。

```text
Person
  ↓
OrganizationFollow
  ↓
Organization
```

OrganizationFollowは、少なくとも以下のConceptを持つ。

- Person
- Organization
- FollowedAt
- Status

一人のPersonは複数OrganizationをFollowできる。

一つのOrganizationは複数PersonからFollowされ得る。

同一Personが同一Organizationを重複してFollowすることはできない。

Unfollow後は、Organizationからの新しい情報をHome FeedのFollow対象として扱わない。

---

# Production Follow

Production Followは将来拡張可能なDomainとして位置付ける。

```text
Person
  ↓
ProductionFollow
  ↓
Production
```

Production Followは、特定公演に関心を持つPersonが、その更新情報を追跡するために使用できる。

初期実装の優先対象はOrganization Followとする。

Production Followは、Organization Followと同じ意味に混同しない。

Organization Followは「その団体の今後の活動への関心」を表す。

Production Followは「特定Productionへの関心」を表す。

---

# Favorite

FavoriteはFollowとは別Conceptとして扱う。

Favoriteは、Personが自分のために保存する対象を表す。

Followが「今後の更新を受け取る関係」であるのに対し、Favoriteは「後で見返すための保存」である。

したがって、Favorite登録だけでHomeへの継続的な新着配信を発生させない。

初期設計ではFavoriteの対象を以下へ拡張可能とする。

- Organization
- Production
- Performance

Favoriteの詳細な対象拡張は、各公開Domainの成熟に合わせて定義する。

---

# Relationship with Membership

PersonがOrganizationのMembershipを持っている場合でも、そのOrganizationをFollowする必要はない。

Membershipは実際の所属関係を表す。

Followは公開情報への関心関係を表す。

両者を自動的に同期してはならない。

例えば、団体の内部メンバーが一般観客向けの新作情報をHomeで受け取る必要があるかは、Membership情報から一律に決定しない。

Home表示は、実際の活動予定とFollow関係を別々の情報源として扱う。

---

# Public Information Boundary

Followによって取得・表示できる情報は、公開対象として定義された情報に限定する。

FollowしているPersonに対して、以下の内部情報を公開してはならない。

- Membership内部Status
- Role
- Permission
- Rehearsalの非公開情報
- Accounting
- Internal Document
- Credential
- その他Organization内部情報

FollowはPublic Informationの配信・発見のための関係である。

---

# Home Feed

OrganizationをFollowしているPersonのHomeには、そのOrganizationの公開情報を基にした新着を表示できる。

初期実装で最も重要なTriggerは以下とする。

```text
Organization
  ↓
新しいProduction / 公演情報を公開
  ↓
Organization Followを持つPersonを解決
  ↓
Person HomeのFollow新着として表示
```

Home表示の例：

```text
フォロー中の新着

劇団○○
新しい公演が公開されました

『作品名』
```

これはPersonが自分で団体を検索しに行かなくても、関心のある団体の新作を発見できることを目的とする。

---

# Home Information Priority

Person Homeは「所属していない」という状態を情報として表示しない。

存在する事実に応じて情報を表示する。

優先概念は以下とする。

1. 自分が実際に参加している活動の予定
2. FollowしているOrganizationの新着公開情報
3. 自分の観劇履歴
4. まだ活動情報が存在しない場合の次の行動への導線

一般観客で、OrganizationにもProductionにも参加していないPersonに対して、以下のような空情報を表示してはならない。

- 所属団体なし
- 参加公演なし
- 次回稽古なし

代わりに、Followしている団体の新着や、存在する観劇履歴を表示する。

何も情報が存在しない場合のみ、例えば以下の導線を表示する。

- 団体を探す
- 公演を探す
- 観劇履歴を記録する

---

# Notification

Followに基づくHome Feedと、Notificationは同一Conceptにしない。

初期実装では、まずPerson Home上の「Follow中の新着」を実現する。

将来的には同じ公開Eventを起点として、Notificationを追加できる。

```text
公開Event
  ├─ Home Feed Item
  └─ Notification
       └─ 将来的に Push Notification / Email等へ拡張可能
```

Push NotificationはFollow Domainの初期実装必須要件とはしない。

---

# Initial Implementation Scope

初期実装では以下を確定する。

1. PersonはOrganizationをFollowできる。
2. PersonはOrganizationをUnfollowできる。
3. Organization公開ページにFollow導線を設ける。
4. Person Homeに「フォロー中の新着」を表示できる。
5. FollowしているOrganizationが新しいProduction / 公演情報を公開した場合、Follow新着としてHomeへ反映する。
6. FollowによってOrganization MembershipやPermissionを付与しない。
7. OrganizationやProductionに参加していない一般観客でもFollowを利用できる。
8. 所属していない状態をHomeの空情報として表示しない。

Production Follow、Favorite、Notification Center、Push Notificationは、この構造を拡張する将来機能として位置付ける。
