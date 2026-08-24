# StageArt Blueprint

# 03 - Public Page URL, Publication Schedule and Membership Onboarding

Version : 1.2
Status : Confirmed

---

## 目的

StageArt上で作成・管理されるOrganizationおよびProductionを、そのまま一般公開可能な団体ページ・公演ページとして利用できるようにする。

また、初回Onboardingにおいて、利用者が団体を作成する場合だけでなく、既存Organizationや既存Productionへの所属・参加を申請できるFlowを確定する。

---

# 01 公開URL

## Organization

```text
https://stageart.top/{organization-slug}
```

## Production

```text
https://stageart.top/{organization-slug}/{production-slug}
```

ProductionはOrganizationの配下で公開する。

---

# 02 Slug

- Organization.slug はStageArt全体で一意。
- Production.slug は同一Organization内で一意。
- 名称入力からSlugを自動生成し、必要に応じて編集可能。

---

# 03 団体管理者の初回Onboarding

団体管理者を選択したPersonは、初回Onboarding中にOrganizationを作成する。

## F01-ORG-02 団体基本情報

同一画面で設定する。

```text
団体名
Organization Slug
説明
Logo
```

作成者はOrganizationの管理者として登録する。

## 直近Production確認

```text
直近に予定している公演はありますか？

[ はい ]
[ いいえ ]
```

「いいえ」はHomeへ進む。

「はい」は以下を設定する。

### F01-PROD-01 公演基本情報

```text
公演名
Production Slug
```

### F01-PROD-02 本番期間・公演回

```text
本番開始日
本番終了日
日程未定設定
本番期間情報の公開設定 / 情報公開日

公演回（複数）
・公演日
・開場時刻
・開演時刻
・終演予定時刻
＋ 公演回を追加
```

公演回は既存の `Production → Performance` 構造を利用する。

### F01-PROD-03 会場・公開設定

```text
会場
会場未定設定
会場情報の公開設定 / 情報公開日
```

### F01-PROD-04 出演予定者・公開設定

```text
出演予定者
出演予定者情報の公開設定 / 情報公開日
```

### F01-PROD-05 チケット情報・公開設定

```text
チケット情報
チケット情報の公開設定 / 情報公開日
```

未決定情報は後から追加・変更可能。

稽古日程は初回Onboardingでは入力せず、Home到達後に設定する。

---

# 04 団体・公演所属者の初回Onboarding

団体管理者ではないPerson、または初回目的選択で団体・公演所属者を選択したPersonは、既存OrganizationおよびProductionへの所属・参加を設定できる。

この設定は必須ではなく、それぞれ個別にスキップできる。

## 全体Flow

```text
団体に所属しますか？
│
├─ スキップ
│
└─ 所属する
    ↓
    Key入力 / QR読取 / 団体検索
    ↓
    対象団体確認
    ↓
    所属申請
    ↓
    団体側承認
    ↓
    正式所属

↓

公演に所属しますか？
│
├─ スキップ
│
└─ 参加する
    ↓
    Key入力 / QR読取 / 公演検索
    ↓
    対象公演確認
    ↓
    参加申請
    ↓
    公演側または団体側承認
    ↓
    正式参加

↓
Home
```

団体と公演は独立してスキップ可能とする。

両方をスキップした場合も正常なOnboarding完了とし、Homeで「どこにも所属していない」といった状態を強調表示しない。

---

## F02-ORG-01 団体所属確認

```text
団体に所属しますか？

[ 団体に所属する ]
[ 今はスキップする ]
```

「団体に所属する」を選択した場合、以下の参加方法を提示する。

```text
所属Keyを入力
QRコードを読み取る
団体を検索する
```

### 所属Key

Organization管理側が発行した参加用Keyを入力する。

### QRコード

Organization管理側が発行した参加用QRコードを読み取る。

### 団体検索

検索画面を別画面またはモーダルで開き、検索結果から対象Organizationを選択する。

検索後は元のOnboarding画面に戻り、選択した対象団体を確認できる。

```text
選択中の団体
劇団○○

[変更]
[所属申請]
```

Key、QR、検索のいずれの方法でも、対象Organizationを確認してから所属申請を行う。

---

## F02-ORG-02 団体所属申請

利用者は対象Organizationに対して所属申請を行う。

```text
劇団○○に所属申請しますか？

[キャンセル]
[所属申請]
```

申請直後は正式所属ではない。

状態は少なくとも以下を持つ。

```text
pending
active
rejected
```

Organization管理側が承認した時点で `active` となり、正式所属とする。

検索から申請した場合は、必ずOrganization側の承認後に所属確定する。

---

## F02-PROD-01 公演参加確認

団体設定の後、または団体設定をスキップした後に表示する。

```text
出演・スタッフとして参加する公演はありますか？

[ 公演に参加する ]
[ 今はスキップする ]
```

「公演に参加する」を選択した場合、以下の参加方法を提示する。

```text
参加Keyを入力
QRコードを読み取る
公演を検索する
```

### 参加Key

ProductionまたはOrganization管理側が発行した公演参加用Keyを入力する。

### QRコード

Production側が発行した公演参加用QRコードを読み取る。

### 公演検索

検索画面を別画面またはモーダルで開き、検索結果から対象Productionを選択する。

検索後は元のOnboarding画面に戻り、選択した対象公演を確認できる。

```text
選択中の公演
○○○○
所属団体：劇団○○

[変更]
[参加申請]
```

Key、QR、検索のいずれでも対象Productionを確認してから参加申請を行う。

---

## F02-PROD-02 公演参加申請

利用者は対象Productionへの参加申請を行う。

```text
○○○○への参加を申請しますか？

[キャンセル]
[参加申請]
```

申請直後は正式参加ではない。

状態は少なくとも以下を持つ。

```text
pending
active
rejected
```

Production側または権限を持つOrganization側が承認した時点で `active` とする。

検索から申請した場合は、必ず承認後に正式参加となる。

---

# 05 Key / QRによる参加

OrganizationおよびProductionには参加用KeyとQRコードを発行できる。

QRコードは参加Keyまたは参加用トークンを表現し、読み取り後に対象Entityを直接特定する。

ただし、KeyやQRを入力・読み取った場合でも、対象Entityを確認する画面を経由する。

```text
Key / QR
↓
対象Organization / Productionを取得
↓
対象確認
↓
参加申請または参加処理
```

参加用KeyやQRは、Entityの内部IDを直接露出する仕組みではなく、参加用トークンとして管理する。

---

# 06 Membership状態

Organizationへの所属とProductionへの参加は、単純な所属有無ではなく状態を持つ。

少なくとも以下を扱う。

```text
pending  : 承認待ち
active   : 正式所属・正式参加
rejected : 拒否
```

将来的には退団・参加終了等の状態を追加可能とする。

概念構造は以下とする。

```text
Person
├─ Organization Membership / Join Request
│   ├─ pending
│   ├─ active
│   └─ rejected
│
└─ Production Membership / Join Request
    ├─ pending
    ├─ active
    └─ rejected
```

既存Domain ModelのMembership系Entityがある場合は、同じ概念を重複するEntityとして追加せず、既存構造に状態管理と参加申請Flowを整合させる。

---

# 07 所属なしユーザー

OrganizationにもProductionにも所属していないPersonは正常な利用者状態である。

例えば観劇客は、所属なしのまま以下を利用できる。

- Organizationをフォロー
- Productionをお気に入り
- 公演情報を閲覧
- チケット情報を確認
- 観劇記録を管理

Homeに「どこにも所属していない」という状態を不要に強調表示しない。

後からマイページ等からOrganizationまたはProductionへの所属・参加申請を開始できる。

---

# 08 初回Onboarding Flow全体

```text
アカウント登録 / Google認証
↓
姓名入力・確認
↓
初回目的選択
│
├─ 団体管理者
│   ↓
│   Organization作成
│   ↓
│   直近Production設定（任意）
│   ↓
│   Home
│
├─ 団体・公演所属者
│   ↓
│   団体に所属しますか？（任意）
│   ↓
│   Key / QR / 検索
│   ↓
│   所属申請（承認待ち）またはスキップ
│   ↓
│   公演に参加しますか？（任意）
│   ↓
│   Key / QR / 検索
│   ↓
│   参加申請（承認待ち）またはスキップ
│   ↓
│   Home
│
└─ 観客
    ↓
    必要な初期設定のみ
    ↓
    Home
```

---

# 09 Blueprint確定事項

1. 団体所属と公演参加は初回Onboardingでそれぞれ個別に設定できる。
2. 団体と公演はそれぞれスキップ可能。
3. 団体への参加方法はKey入力、QRコード読み取り、団体検索の3方式。
4. 公演への参加方法はKey入力、QRコード読み取り、公演検索の3方式。
5. 検索画面は別画面またはモーダルで開き、選択後に元のOnboarding画面で対象を確認する。
6. 検索からの所属申請はOrganization側の承認後に正式所属となる。
7. 検索からの公演参加申請はProduction側または権限を持つOrganization側の承認後に正式参加となる。
8. 申請状態として少なくとも pending / active / rejected を持つ。
9. KeyおよびQRによる参加でも対象確認画面を経由する。
10. KeyやQRは内部Entity IDを直接公開するのではなく、参加用トークンとして管理する。
11. OrganizationにもProductionにも所属していないPersonは正常な利用者状態とする。
12. 所属なし状態をHomeで不要に強調表示しない。
13. 後からいつでも所属・参加申請を開始できる。

---

End of Blueprint
