# StageArt Blueprint

# Domain Model : Join Key

Version : 1.0

---

# Purpose

Join Keyは、PersonをOrganizationまたはProductionへ直接案内し、参加・所属の開始を容易にするためのDomainである。

本仕様は、`03-InitialOnboardingAndJoinKey.md` で定義された初回Onboardingおよび参加コード導線を、Domain Modelとして正式に補完する。

Join Keyは単なる検索用の短縮IDではない。

管理者が発行し、利用者へ配布し、利用者が入力またはQRコード等から読み取ることで、対象OrganizationまたはProductionへの参加Flowを開始するための招待・参加用キーとして扱う。

---

# Relationship to Organization and Production

Join KeyはOrganizationまたはProductionを対象とする。

基本構造：

```text
JoinKey
    ├── targetType
    │       ├── ORGANIZATION
    │       └── PRODUCTION
    │
    └── targetId
            ├── OrganizationId
            └── ProductionId
```

したがって、Join KeyはOrganizationIdやProductionIdそのものを外部へ露出する代替識別子ではない。

Organization / ProductionのIdentityは従来どおりOrganizationId / ProductionIdとし、Join Keyは参加導線専用の独立概念とする。

---

# Organization Join Key

Organizationには、Organizationへの所属を案内するためのOrganization Join Keyを発行できる。

利用者がOrganization Join Keyを入力した場合、StageArtは対象Organizationを解決し、Membershipの参加Flowへ接続する。

```text
Person
    ↓
Organization Join Key
    ↓
Organization
    ↓
Membership Flow
```

Join Keyを入力しただけでMembershipを無条件に確定してはならない。

対象Organizationを表示し、利用者による確認を経たうえで、Membership DomainのBusiness Ruleに従って所属を成立させる。

---

# Production Join Key

Productionには、Productionへの参加を案内するためのProduction Join Keyを発行できる。

利用者がProduction Join Keyを入力した場合、StageArtは対象Productionを解決し、Participantの参加Flowへ接続する。

```text
Person
    ↓
Production Join Key
    ↓
Production
    ↓
Participant Flow
```

Production Join Keyを知っていることだけで、Productionの管理権限を取得してはならない。

ParticipantとProductionDelegateは従来どおり独立した概念とする。

---

# Issuer

Join Keyの発行は対象Scopeの管理権限を持つPersonのみが行える。

Organization Join Keyの発行には、Organization Scopeにおける適切なPermissionを必要とする。

Production Join Keyの発行には、Production Scopeにおける適切なPermissionを必要とする。

具体的なPermission名はAuthorization / Role仕様で定義する。

Join Keyの発行権限は、Join Key自体を知っているPersonへ自動的に付与されない。

---

# Issuance UI

OrganizationおよびProductionの管理画面には、それぞれ独立した参加コード発行導線を設ける。

## Organization管理画面

Organizationの管理メニューに、少なくとも以下の導線を設ける。

```text
団体への参加
    ├── 参加コードを発行する
    ├── 発行済みコードを確認する
    ├── コードを無効にする
    └── QRコードを表示する（将来拡張）
```

管理者は発行されたOrganization Join Keyを、出演者、スタッフ、団体メンバー等へ配布できる。

## Production管理画面

Productionの管理メニューに、少なくとも以下の導線を設ける。

```text
公演・活動への参加
    ├── 参加コードを発行する
    ├── 発行済みコードを確認する
    ├── コードを無効にする
    └── QRコードを表示する（将来拡張）
```

Production Join Keyは、出演者・スタッフ等のParticipant参加導線として利用する。

---

# Multiple Keys

一つのOrganizationまたはProductionに対して、複数のJoin Keyを発行できる設計とする。

理由：

- 一時的な参加コードを発行できる。
- 古いコードを無効化して新しいコードへ切り替えられる。
- 用途ごとに別のコードを発行できる。
- 将来的に参加区分やRole候補をコード単位で制限できる。

ただし、UI上で同時に複数コードを見せることを必須とはしない。

初期UIでは「現在有効な参加コード」を中心に扱い、詳細管理画面で複数コードを扱える構成を許容する。

---

# Lifecycle

Join Keyは少なくとも次の状態を持てるものとする。

- ACTIVE
- DISABLED
- EXPIRED
- EXHAUSTED

基本Lifecycle：

```text
発行
 ↓
ACTIVE
 ├── DISABLED
 ├── EXPIRED
 └── EXHAUSTED
```

DISABLED、EXPIRED、EXHAUSTEDとなったJoin Keyは、新規参加に使用してはならない。

過去にそのJoin Keyを利用して成立したMembershipまたはParticipantを遡及的に削除してはならない。

---

# Attributes

Join Keyは少なくとも以下の概念を持つ。

- JoinKeyId
- Code
- TargetType
- TargetId
- Status
- IssuedAt
- IssuedByPersonId
- ExpiresAt（任意）
- MaxUses（任意）
- UseCount
- DisabledAt（任意）

将来的な拡張として以下を許容する。

- Allowed Participant Type
- Allowed Role Candidate
- Note
- Purpose
- LastUsedAt

ただし、Join Key自体をRoleやParticipant Typeと同一視してはならない。

---

# Code Format

初期仕様では、人間が口頭・紙・メッセージで扱いやすい8文字程度の英数字コードを基本とする。

例：

```text
AB7K29XZ
```

表示上の読みやすさのためにハイフンを利用できる。

```text
AB7K-29XZ
```

入力時には大文字小文字およびハイフンを正規化できるものとする。

コードは対象Resourceの内部IDを直接エンコードしたものではなく、十分な衝突回避と推測困難性を持つランダムな値として生成する。

---

# Resolve and Confirm

利用者はOrganization Join KeyかProduction Join Keyかを事前に選択する必要はない。

基本入口は共通とする。

```text
参加コードを入力
```

入力されたコードをStageArtが解決し、対象を表示する。

```text
これは団体への参加コードです

劇団○○

この団体に参加しますか？
```

または、

```text
これは公演・活動への参加コードです

公演「○○○○」

この公演・活動に参加しますか？
```

確認なしに即時参加を確定してはならない。

---

# Domain Separation

Join Keyは以下のDomainを置き換えない。

- Organization Identity
- Production Identity
- Membership
- Participant
- ProductionDelegate
- Role
- Permission

Join Keyは、それらのDomainへ到達するための参加開始トリガーである。

最終的な所属・参加・権限は、既存のMembership / Participant / Role / PermissionのBusiness Ruleに従う。

---

# QR Code

QRコードはJoin Keyの将来の入力補助手段として扱う。

QRコード自体を新しいIdentityとして作成しない。

QRコードにはJoin KeyまたはJoin Keyを解決できるURL等を格納できる。

したがって、将来のQRコード導入後もDomainの中心はJoin Keyであり続ける。

---

# Required Additions to Existing Domain Models

本書により、既存Domain Modelへの以下の追記を正式仕様とする。

## Organization.md

Organizationには、OrganizationへのMembership参加を案内するOrganization Join Keyを発行・管理できる概念を追加する。

Organization管理UIには「団体への参加」メニューを設け、その配下に参加コードの発行・確認・無効化を配置する。

## Production.md

Productionには、ProductionへのParticipant参加を案内するProduction Join Keyを発行・管理できる概念を追加する。

Production管理UIには「公演・活動への参加」メニューを設け、その配下に参加コードの発行・確認・無効化を配置する。

---

# Consistency Rules

- Join KeyはOrganizationId / ProductionIdの代替Identityではない。
- Join Keyを知っているだけで管理権限を取得してはならない。
- Organization Join KeyとProduction Join KeyはTargetTypeによって明確に区別する。
- Organization Join KeyはMembership Flowへ接続する。
- Production Join KeyはParticipant Flowへ接続する。
- Join Keyによる参加成立後も、Role / Permissionは既存Domainのルールに従う。
- Join Keyは無効化できる。
- Join Keyの無効化は既存のMembership / Participantを遡及的に削除しない。
- OrganizationおよびProductionの管理画面には、それぞれ参加コード発行メニューを設ける。
