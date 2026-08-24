# StageArt Blueprint

# 04 - Home Role-Based Menu and Management Entry Design

Version : 1.0
Status : Confirmed

---

# 01 基本方針

StageArtのHomeは「機能一覧」ではなく、一般ユーザーを基準に、Personが持つ所属・参加・管理権限に応じて必要な入口だけを追加表示する。

OrganizationにもProductionにも所属していない状態は正常な利用状態であり、所属なしをネガティブに表示しない。

Home上の情報は、次回稽古等の存在しない情報をプレースホルダーとして表示せず、実際に対象情報が存在する場合のみ表示する。

---

# 02 全ユーザー共通Home

すべてのユーザーのHomeには、少なくとも以下を表示する。

```text
おはようございます ○○さん

管理者からの通知

団体を探す

公演・活動を探す

お気に入り

設定
├ ユーザ名変更
└ 団体・公演に参加
   ├ 所属・参加Keyを入力
   ├ QRコードを読み取る
   └ 団体・公演を検索

ログアウト
```

「所属Keyを読み込む」だけではQR・検索による参加入口が分かりにくいため、設定上の入口名称は「団体・公演に参加」とし、その内部にKey・QR・検索をまとめる。

一般ユーザーの初回Onboardingは追加質問を行わず、アカウント登録またはGoogle認証、姓名設定後に直接Homeへ到達する。

---

# 03 通知と更新情報

通知は情報源に応じて区別する。

- StageArt管理者からの通知
- 所属Organizationからの通知
- 参加Productionからの通知

また、フォローしているOrganizationの新しいProduction公開等は、直接自分に送られた通知とは別の「フォロー中の団体の新着情報」として扱う。

```text
お知らせ・通知

フォロー中の団体の新着情報
```

通知と興味情報を混在させない。

---

# 04 Organization所属者への追加表示

Organizationに正式所属しているPersonには、共通Homeに加えて以下を追加する。

```text
所属Organizationからの通知

所属Organization
├ Organization名
└ Organization情報への入口
```

Organizationが複数ある場合は、すべて識別できる形で表示する。

---

# 05 Production参加者への追加表示

Productionに正式参加しているPersonには、共通Homeに加えて以下を追加する。

```text
Production管理者からの通知

参加中のProduction
├ Production情報
├ 次回稽古
├ 出欠確認（出欠確認が設定されている場合のみ）
└ 立替金申請（Productionの会計機能がONの場合のみ）
```

「次回稽古なし」等は表示しない。次回予定が存在する場合のみ、そのProduction名とともに表示する。

複数Productionに参加している場合、予定・通知・出欠確認は必ず対象Productionを識別可能な形で表示する。

---

# 06 Organization管理者への追加表示

Organization管理権限を持つPersonには、共通機能および所属Organization機能に加えて以下を表示する。

```text
Organization管理

├ 公演・活動を作る
├ Organization情報・設定
├ Organizationメンバー管理
├ Organizationへ招待
└ Organization参加申請
```

## Organization情報・設定

```text
Organization情報・設定
├ Organization基本情報
│  ├ 団体名
│  ├ Slug
│  ├ 説明
│  └ Logo
└ その他のOrganization設定
```

## Organizationメンバー管理

```text
Organizationメンバー管理
├ メンバー一覧
├ 権限管理
└ メンバー削除
```

## Organizationへ招待

```text
Organizationへ招待
├ QRコード発行
└ 招待Key発行
```

## Organization参加申請

検索等から所属申請が来ている場合のみ表示する。

```text
Organization参加申請（3件）
```

申請一覧から承認・拒否を行う。

---

# 07 Production管理者への追加表示

Production管理権限を持つPersonには、対象Productionごとに以下の管理入口を持つ。

```text
Production管理

├ Production情報
├ 一括通知
├ 稽古管理
├ 会計管理
├ Production・活動に招待
├ Production参加申請
└ Production・活動設定
```

## Production情報

参加者・管理者が対象Productionの情報を確認するための入口。

## 一括通知

対象Productionの参加者・対象者へ通知を送る。

## 稽古管理

```text
稽古管理
└ 稽古日程確認
   ├ 日程作成・調整
   ├ 出欠確認を設定
   ├ 出欠状況確認
   └ 稽古日程確定
```

稽古日程の作成、出欠確認、出欠状況の確認、日程確定は「稽古日程確認」を中心画面として扱う。

## 会計管理

Productionの会計機能がONの場合に利用する。

```text
会計管理
├ 会計入力
└ 立替金承認
```

## Production・活動に招待

```text
Production・活動に招待
├ QRコード発行
└ 招待Key発行
```

## Production参加申請

参加申請が存在する場合のみ表示する。

```text
Production参加申請（2件）
```

承認権限を持つ管理者が承認・拒否を行う。

## Production・活動設定

```text
Production・活動設定
├ 公演・活動設定
│  ├ タイトル
│  └ フライヤー等の基本情報
├ 日程管理
│  └ 本番日程・公演回等の調整
├ チケット管理
│  └ チケット金額等の調整
├ メンバー管理
│  ├ メンバー追加・削除
│  └ 役割管理
└ 会場管理
   └ 会場情報の調整
```

---

# 08 Productionメンバーの役割管理

Productionのメンバー管理では、単なる所属者一覧ではなく、Production内の役割を管理できる。

例：

- 出演者
- 演出
- 制作
- 舞台監督
- 照明
- 音響
- その他

出演者については、必要に応じて役名等のProduction固有情報を管理できる。

この情報はProductionの公開キャスト・スタッフ情報と連携可能な構造とする。

---

# 09 複数Organization・Productionへの所属

Personは複数Organizationに所属し、複数Productionに参加できる。

Homeでは以下を必ず満たす。

1. 通知・予定・出欠確認の対象OrganizationまたはProductionを識別できる。
2. 次回稽古等は対象Production名とセットで表示する。
3. Organization管理者が公演・活動を作成する場合、複数Organizationの管理権限を持つ場合は対象Organizationを選択できる。
4. Production管理機能は対象Productionごとに区別する。

---

# 10 Home表示優先順位

Homeは権限別メニューを機械的に大量表示するのではなく、現在対応が必要な情報を優先する。

例：

```text
おはようございます ○○さん

通知
・承認待ちの申請
・未読通知

次回の予定
・○○○○公演 9月10日 18:00〜

回答が必要
・○○○○公演 稽古出欠確認

管理
・Organization参加申請（存在する場合のみ）
・Production参加申請（存在する場合のみ）

探す
・団体を探す
・公演・活動を探す

お気に入り

設定

ログアウト
```

管理機能の詳細設定はHome上で全展開せず、OrganizationまたはProductionの管理入口から詳細画面へ進む。

---

# 11 Blueprint確定事項

1. Homeは一般ユーザーを基準とする。
2. 一般ユーザーは追加OnboardingなしでHomeへ到達する。
3. 全ユーザー共通で「団体を探す」「公演・活動を探す」「お気に入り」「設定」「ログアウト」を提供する。
4. 団体・公演への所属・参加は設定内の「団体・公演に参加」からも開始できる。
5. Key入力、QR読み取り、検索を同じ参加入口にまとめる。
6. Organization所属者にはOrganizationからの通知を追加する。
7. Production参加者にはProduction管理者からの通知、次回稽古、必要時のみ出欠確認、会計ON時のみ立替金申請を追加する。
8. 次回稽古等の存在しない情報は表示しない。
9. Organization管理者にはOrganization情報・設定、メンバー管理、招待、参加申請承認を提供する。
10. Production管理者には一括通知、稽古管理、会計管理、招待、参加申請承認、Production設定を提供する。
11. Organization・Productionの参加申請は申請が存在する場合のみHomeまたは管理入口で強調表示する。
12. Productionメンバー管理には役割管理を含める。
13. Personは複数Organization・複数Productionに所属・参加可能とする。
14. Home上の通知・予定・管理項目は必ず対象OrganizationまたはProductionを識別可能とする。
15. Homeは「全機能一覧」ではなく、現在必要な行動と情報への入口として設計する。

---

End of Blueprint
