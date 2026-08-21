# StageArt Blueprint

# 03 - Business Flow UX Clarifications

Version : 1.0

---

## Purpose

本書は、`03-BusinessFlow.md` に定義されたBusiness Flowを、初回登録後のモバイルUIおよび一般利用者向けの導線へ落とし込む際の補足方針を定義する。

既存のDomain ModelおよびBusiness Flowを変更するものではなく、利用者に見せる入口と権限による表示の考え方を明確にする。

---

# 1. 登録直後の基本状態

StageArtでは、Googleまたはメールアドレスによるアカウント登録が完了しただけでは、利用者を特定のOrganizationまたはProductionへ自動所属させない。

登録直後の基本状態は、次のとおりとする。

```text
UserAccount
    ↓
Person
    ├─ Organization Membership：なし
    ├─ Production Participant：なし
    └─ History：0件以上
```

Personは、Organizationに所属していない状態でもStageArt利用者として成立する。

Organizationへの所属、Productionへの参加、観劇履歴は、それぞれ独立した状態として扱う。

---

# 2. 初回ホームの基本方針

初回登録直後の利用者に対して、ProjectやProductionの管理機能を一律に表示しない。

利用者がまだOrganizationに所属していない場合でも、StageArtの利用を開始できるホームを表示する。

基本的な入口は以下とする。

- 団体を探す
- 公演を探す
- 観劇履歴
- プロフィール
- 団体を作る
- 団体に参加する

「あとで設定する」ことも可能とする。

---

# 3. Production作成権限

Production（公演・活動）の作成は、一般利用者が自由に行う機能ではない。

Organizationに所属していること、およびProduction作成に必要な管理権限を持っていることを前提とする。

したがって、登録直後の利用者や一般のOrganizationメンバーには、Production作成機能を表示しない。

必要なRole / Permissionを取得した利用者にのみ、「公演を作る」等の利用者向け名称でProduction作成機能を表示する。

ProjectはInternal Domainであり、一般利用者にProject作成を直接要求するUIを設けない。

---

# 4. Projectと「公演を作る」の関係

ProjectはOrganizationが行う活動・制作をまとめるInternal Domainである。

利用者は通常Projectを意識しない。

利用者が「公演を作る」操作を行った場合、StageArtは内部で、

- 既存ProjectへProductionを所属させる
- 新しいProjectを作成してProductionを所属させる

のいずれかを行う。

基本的な利用者向けFlowは次のとおりとする。

```text
公演を作る
    ↓
所属Projectを内部で決定
    ├─ 既存Project
    └─ 新規Project
    ↓
Production生成
```

Projectそのものを一般観客向けの検索結果や初回ホームの主要対象として扱わない。

---

# 5. 「団体を探す」と「公演を探す」は別の入口

「団体を探す」と「公演を探す」は、単なる同一検索機能の別名ではない。

両者は検索の起点と利用目的が異なるため、モバイルUIでは別入口として提供する。

## 5.1 団体を探す

「団体を探す」は、Organizationを起点としてStageArt上の情報を辿るための入口である。

用途を観客または舞台人のどちらかに限定しない。

例えば、観客は、

- 気になる団体を探す
- 団体の現在の情報を見る
- 今後の公演を見る
- 過去の公演を見る
- 団体の活動履歴を辿る

ために利用できる。

舞台人は、

- 過去に参加した団体を探す
- 過去公演を探す
- 自分が出演した公演を確認する
- 自分が出演者として登録されているがPersonと未紐付けのProduction Participantを探す

ためにも利用できる。

したがって、団体を探すことは、団体情報・現在の公演・今後の公演・過去公演・活動履歴などを辿れる汎用的な探索入口とする。

---

# 6. 「公演を探す」

「公演を探す」は、Productionを起点として公演を発見するための入口である。

特に一般観客が、

- いつ公演があるか
- どこで公演があるか
- どのような公演が予定されているか
- どの団体が公演するか

などを一覧・検索・絞り込みによって確認できることを目的とする。

主として今後開催される公演を発見するための入口とするが、過去公演を排除するものではない。

「団体を探す」がOrganizationを起点とするのに対し、「公演を探す」はProductionを起点とする。

```text
団体を探す
    ↓
Organization
    ↓
現在・今後・過去のProduction

公演を探す
    ↓
Production
    ↓
開催情報・公演情報・Organization
```

---

# 7. 観劇履歴

OrganizationにもProductionにも所属していない利用者であっても、観客としてStageArtを利用できる。

観劇履歴はPersonに紐付くHistoryとして扱い、観劇した事実はAUDIENCE Historyとして記録する。

したがって、登録直後の利用者には観劇履歴が存在しない場合があるが、将来的に観劇することで履歴が蓄積される。

観劇履歴は、舞台活動への所属状態とは独立したPersonの情報として表示する。

---

# 8. Production参加者の本人確認

Productionへの参加（Participant）について、本人とProduction側の情報を紐付けるため、以下の2つの入口を想定する。

## 8.1 公演管理者がメールアドレス付きで参加者を登録する場合

公演を管理する権限を持つ利用者が、ProductionのParticipantとしてPerson候補をメールアドレス付きで登録する。

StageArtに該当メールアドレスのPersonが存在する場合、そのPersonへ参加確認を通知する。

利用者が承認すると、Production ParticipantとPersonの対応関係を確定する。

未登録者の場合は、招待メールからStageArtへの登録を行った後、同じ参加確認へ進める。

基本Flow：

```text
公演管理者
    ↓
Participant登録
    ↓
メールアドレス照合
    ↓
本人へ参加確認
    ↓
承認
    ↓
Production ParticipantとPersonを紐付け
```

登録しただけで本人の同意なしにPersonへ自動紐付けするのではなく、本人確認・承認を経て確定する。

---

# 9. Production参加者がメールアドレス未登録の場合

公演管理者が出演者・スタッフを登録した時点でメールアドレス等のPerson識別情報が存在しない場合でも、Production側にはParticipantを登録できる。

その後、StageArt利用者が団体・過去公演等を辿り、自分が登録されているParticipantを発見した場合、

「これは私です」

という本人確認申請を行えるようにする。

基本Flow：

```text
公演管理者
    ↓
Participant登録
（氏名等のみ）
    ↓
StageArt利用者が公演を発見
    ↓
「これは私です」
    ↓
公演管理者へ確認申請
    ↓
承認
    ↓
Production ParticipantとPersonを紐付け
```

このFlowにより、過去公演に出演していたが当時メールアドレス等が登録されていなかった舞台人も、後から自分の出演履歴をStageArt上のPersonへ紐付けられるようにする。

---

# 10. 初期ホームにおける探索入口

初期ホームでは、少なくとも以下の2つの探索入口を別々に提供する。

```text
[ 団体を探す ]
団体から現在・今後・過去の活動を探す

[ 公演を探す ]
公演から開催予定・公演情報を探す
```

両方とも全利用者が利用できる共通機能とする。

団体を探すを舞台人専用、公演を探すを観客専用とはしない。

利用者がどの立場でStageArtを利用しているかに関係なく、両方の入口を利用できる。

---

# 11. 初期ホームと権限による機能表示

初期ホームは、Personの現在の状態と権限に応じて内容を追加表示する。

例：

```text
登録直後
  → 団体を探す
  → 公演を探す
  → 観劇履歴
  → プロフィール
  → 団体を作る / 団体に参加する

Organization管理権限取得後
  → 上記に加えて「公演を作る」等の管理機能

Production Participant
  → 上記に加えて参加中の公演への入口
```

「管理機能があるか」と「StageArt上で公演・団体を探せるか」は独立して扱う。

---

# 12. 用語方針

利用者向けUIでは、Projectを主要な業務用語として表示しない。

利用者には原則として、

- 団体
- 公演
- 参加
- 観劇
- 団体を探す
- 公演を探す
- 公演を作る

など、Business Flowに沿った言葉を使用する。

Projectは、複数Productionをまとめ、会計・予実・資料等を含む制作全体を管理するInternal DomainとしてBackendおよび管理機能で利用する。
