# StageArt Blueprint

# 03 - Initial Onboarding and Join Key

Version : 1.0

---

## Purpose

本書は、StageArtへ初めて登録したPersonが、StageArtで何ができるのかを理解し、自身の利用目的に応じた最初の行動を選択したうえでPerson Homeへ到達するまでの初回Onboarding、およびOrganization / Productionへ参加するためのJoin Key（参加キー）方式を定義する。

本仕様は `03-BusinessFlow.md` および `03-BusinessFlowUXClarifications.md` を補足するUX・Business Flow仕様である。

重要な原則は次のとおりとする。

- 初回登録直後に、利用者へ「何をすればよいかわからない」状態を残さない。
- 最初の回答によってPersonの役割を固定しない。
- Organization Membership、Production Participant、観客としての利用は独立した状態として扱う。
- 検索は参加方法の一つとし、現場で配布できるJoin Keyによる直接参加を主要導線として追加する。
- 初回Onboardingを途中でスキップまたは後回しにしても、後から同じ機能へ到達できる。

---

# 01 初回登録からPerson Homeまでの基本Flow

Email登録の場合の基本Flowを以下とする。

```text
アカウント登録
    ↓
Email確認
    ↓
姓名確認・入力
    ↓
StageArtについて
    ↓
初回利用目的の選択
    ↓
必要に応じた初期設定
    ↓
Person Home
```

Google認証の場合も、Email確認が不要である点を除き、同じOnboarding Flowへ入る。

```text
Google認証
    ↓
姓名確認・入力
    ↓
StageArtについて
    ↓
初回利用目的の選択
    ↓
必要に応じた初期設定
    ↓
Person Home
```

Googleから取得できる姓名情報が存在する場合は、姓名入力画面のDefault値として利用できる。

ただし、Google由来の情報を利用者の明示確認なしに最終確定するものではない。利用者は入力画面で確認・修正できるものとする。

Email登録の場合は姓名欄を空欄から入力する。

---

# 02 姓名とPerson表示

StageArtは初回Onboardingの中でPersonの姓・名を確認する。

Person Homeでは、少なくとも姓を利用して利用者を歓迎できるものとする。

例：

```text
おはようございます、秦さん
```

時間帯に応じて「おはようございます」「こんにちは」「こんばんは」等を切り替えることは可能とする。

姓名の保存方式、Display Nameとの関係、およびBackend APIはDomain / API仕様に従って別途定義する。

本書では初回Onboarding上のUXとして、以下を確定する。

- Email登録者は姓名を入力する。
- Google認証者は取得可能な姓名をDefault値として表示する。
- 利用者は確認・修正して保存する。
- 姓名未設定のまま通常のPerson Homeへ進ませないことを基本とする。ただし将来の既存アカウント移行等では個別Migration Policyを別途定義できる。

---

# 03 StageArt紹介画面

姓名確認の後、利用者へStageArtの目的を説明する画面を表示する。

基本文言は以下を起点とする。

> StageArtは、舞台芸術に関わる人たちのためのプラットフォームです。

その上で、利用者が次のような活動を行えることを説明する。

- 団体を管理する。
- 団体に所属する。
- 公演・活動に参加する。
- 公演・活動を探す。
- 団体を探す。
- 観劇履歴を記録する。

この画面の目的は機能一覧を単純に羅列することではなく、次の選択画面へ進む前に「自分は何のためにStageArtを使うのか」を利用者が理解できるようにすることである。

---

# 04 初回利用目的の選択

StageArt紹介の後、利用者へ現在の主な利用目的を尋ねる。

初期UIでは、少なくとも次の3つを主要な選択肢として提示する。

```text
あなたはどのようにStageArtを利用しますか？

[ 団体を管理している ]
劇団・企画団体・劇場などの運営や管理をしている

[ 団体や公演・活動に参加している ]
出演者・スタッフなどとして活動に参加している

[ 観客として利用する ]
公演・活動を探したり、観劇履歴を記録したい
```

この選択はPersonの固定Roleではない。

例えば、一人のPersonが同時に以下の状態になることを許容する。

- 自身のOrganizationを管理する。
- 別OrganizationにMemberとして所属する。
- 別ProductionにParticipantとして参加する。
- 観客として観劇履歴を記録する。

したがって、Onboardingの回答は「現在の利用目的・最初に行いたいこと」を把握するためのUX上の選択であり、Domain上の恒久的な属性や権限制御に使用しない。

---

# 05 団体を管理している場合

「団体を管理している」を選択した利用者には、StageArtでOrganizationを管理できることを説明する。

例：

```text
あなたの団体をStageArtで管理できます。

メンバー管理
稽古管理
公演・活動管理
チケット管理など
```

その後、次の選択肢を提示する。

```text
[ 団体を作成する ]
[ あとで設定する ]
```

「あとで設定する」を選択してもOnboarding全体を完了できる。

Organization作成はPersonの唯一の利用目的を意味しないため、後からOrganizationへの参加やProductionへの参加を行うことを妨げない。

---

# 06 団体や公演・活動に参加している場合

「団体や公演・活動に参加している」を選択した利用者には、所属・参加方法を提示する。

主要導線は以下とする。

```text
[ 参加コードを持っている ]
8文字程度のJoin Keyを入力して直接参加する

[ 団体を探す ]
団体名などからOrganizationを探す

[ 公演・活動を探す ]
参加しているProductionを探す

[ あとで設定する ]
```

Join Keyを持っている場合、利用者に検索を強制しない。

舞台芸術の現場では、稽古場、打ち合わせ、出演者連絡等で管理者から直接コードを配布できることを前提とする。

例：

> StageArtに登録したら、この参加コードを入力してください。

この利用方法を、検索と同等以上に自然な参加導線として扱う。

---

# 07 観客として利用する場合

「観客として利用する」を選択した利用者は、OrganizationやProductionへの所属を必須としない。

Onboardingでは、必要に応じて以下の入口を提示できる。

- 公演・活動を探す。
- 団体を探す。
- 観劇履歴を記録する。
- あとで設定する。

観客として利用することを選択したPersonも、後からOrganization MembershipやProduction Participantになることができる。

---

# 08 Onboardingの完了とPerson Home

利用者が必要な初期設定を終えた場合、または「あとで設定する」を選択した場合、Onboardingを完了してPerson Homeへ遷移する。

Person Homeでは、利用者を識別できる歓迎表示を行う。

例：

```text
おはようございます、[姓]さん
```

その下には、既存のPerson中心の共通導線を配置する。

- 団体を探す
- 公演・活動を探す
- 参加している公演・活動
- 観劇履歴
- プロフィール

Organization管理機能は、実際にMembership / Role / Permissionを持つ場合のみ表示する。

初回Onboardingで「団体を管理している」を選択しただけでは、Organization管理権限を与えない。

---

# 09 Join Key（参加キー）

## 09.1 目的

Join Keyは、PersonをOrganizationまたはProductionへ直接案内するための参加用キーである。

Join Keyの目的は、検索を不要にし、現場での口頭・紙・メッセージ・QRコード等による参加案内を容易にすることである。

初期仕様では、利用者が入力しやすい8文字程度の英数字コードを基本とする。

例：

```text
AB7K29XZ
```

ハイフンを表示上の読みやすさのために利用することは可能だが、入力時はハイフンの有無に影響されないよう正規化できるものとする。

例：

```text
AB7K-29XZ
AB7K29XZ
```

を同じJoin Keyとして扱えるようにする。

---

## 09.2 利用者向け入力

利用者には対象種別を先に選ばせず、基本的には単一の入口を提供する。

```text
参加コードを入力

[ A B 7 K 2 9 X Z ]

[ 続ける ]
```

StageArtがJoin Keyを解決し、そのコードが何を指すかを判定する。

例：

```text
これは団体への参加コードです

劇団○○

この団体に参加しますか？

[ キャンセル ] [ 続ける ]
```

または、

```text
これは公演・活動への参加コードです

公演「○○○○」

この公演・活動に参加しますか？

[ キャンセル ] [ 続ける ]
```

Join Key入力後、確認なしに即時参加を確定してはならない。

対象名称等を表示し、利用者が正しい対象であることを確認したうえで次へ進む。

---

# 10 Join Keyの対象

Join Keyは少なくとも以下の対象を扱える設計とする。

```text
Organization Join Key
Production Join Key
```

利用者UI上では、これらを別々の入力欄にする必要はない。

```text
参加コードを入力
```

という共通入口から対象を判定する。

内部では対象種別を明確に区別する。

```text
JoinKey
    ├─ targetType: Organization / Production
    └─ targetId
```

Organizationへの参加とProductionへの参加は、Domain上では別の関係として扱う既存原則を維持する。

- Organization Join Key → Membership等のOrganization参加Flowへ接続する。
- Production Join Key → Participant等のProduction参加Flowへ接続する。

Join Keyを入力したこと自体を、無条件の権限付与と同義にしてはならない。

最終的なMembership / Participantの成立は、それぞれのBusiness Ruleに従う。

---

# 11 Join Keyの確認と権限

Join Keyによって直接対象を解決できても、次の情報を確認できるものとする。

- 対象種別
- Organization名またはProduction名
- 必要に応じて所属Organization
- 参加時に選択可能なRoleまたは区分

Productionの場合、出演者・スタッフ等の参加区分を選択する必要がある場合は、確認画面または次画面で選択できる。

ただし、Join Keyを知っているだけでProduction管理権限を取得してはならない。

Production Participantと管理権限は独立した概念とする。

---

# 12 Join Keyの管理

Join Keyは、単純なOrganization IDやProduction IDを8文字化したものではなく、独立した管理対象として扱うことを基本方針とする。

将来的に少なくとも以下の属性を追加できる構造とする。

- key
- targetType
- targetId
- enabled / disabled
- expiresAt
- maximumUses
- currentUseCount
- allowedRole / participationType
- createdAt
- revokedAt

初期実装で全属性を必須とするものではない。

最小構成としては、少なくとも以下を扱えることを目標とする。

```text
Join Key
対象OrganizationまたはProduction
有効 / 無効
```

将来的な有効期限・回数制限・用途別キーへの拡張を妨げない。

---

# 13 QRコードとの関係

Join Keyは将来的にQRコードとしても配布できるものとする。

例：

```text
StageArtに参加してください

[ QRコード ]
```

利用者はQRコードを読み取り、Join Key入力と同じ確認Flowへ入る。

```text
QRコード読み取り
    ↓
Join Key解決
    ↓
Organization / Production確認
    ↓
利用者確認
    ↓
参加Flow
```

QRコードはJoin Key方式の別UIであり、別のDomain参加モデルを作らない。

---

# 14 検索との関係

Join Keyは検索を置き換えるものではない。

参加方法として以下を併存させる。

```text
参加したい
    ├─ Join Keyを持っている
    │      ↓
    │   直接対象を解決
    │
    └─ Join Keyを持っていない
           ↓
        団体を探す
        公演・活動を探す
```

Join Keyは主として、管理者から既に参加対象を案内されている利用者のための導線である。

検索は、参加対象そのものを探したい利用者、または観客として情報を発見したい利用者のための導線として維持する。

---

# 15 初回Onboardingの回答は後から変更可能

初回Onboardingで行った選択は固定設定ではない。

例えば、最初に「観客として利用する」を選択したPersonが後日、次の操作を行うことを許容する。

```text
観客
    ↓
団体に誘われる
    ↓
Join Key入力
    ↓
Organization Member
```

同様に、Organization管理者が別ProductionへParticipantとして参加することも可能とする。

初回Onboardingの回答は、将来の利用者状態を制限するものではない。

---

# 16 Blueprint上の確定事項

本仕様により、以下を確定する。

1. StageArt登録後は、姓名確認・StageArt紹介・初回利用目的選択を経てPerson Homeへ到達する。
2. 初回利用目的は、権限や恒久的Roleを固定しないUX上の選択とする。
3. 初回の主要選択肢は「団体を管理している」「団体や公演・活動に参加している」「観客として利用する」とする。
4. Organization / Productionへの参加には検索導線に加えてJoin Keyによる直接参加導線を追加する。
5. Join Keyは利用者に8文字程度の英数字コードとして提供し、単一の「参加コードを入力」入口から対象を解決する。
6. Join KeyはOrganization / Productionの両方を対象にできる設計とする。
7. Join Key入力後は、対象確認なしに即時参加を確定しない。
8. Join Keyは将来的に有効期限、回数制限、Role制限、無効化等へ拡張できる独立した管理対象とする。
9. QRコードはJoin Keyを配布するための別UIとして扱い、別の参加Domainを作らない。
10. Onboardingの回答や最初の参加状態は、後から追加・変更できる。

---

# 17 既存仕様との整合

本書は以下の既存方針を維持する。

- PersonはOrganizationに所属していなくてもStageArt利用者として成立する。
- Organization MembershipとProduction Participantは独立した関係である。
- 観劇履歴はPersonに紐付く独立したHistoryである。
- Projectは利用者向け主要概念とせず、内部Domainとして扱う。
- Production管理権限とParticipantであることは同義ではない。
- Organization管理機能は実際のMembership / Role / Permissionを取得した場合のみ表示する。

本仕様は上記のDomain原則を変更せず、初回利用者体験と参加導線を具体化するものである。
