# StageArt Blueprint

# 03 - Initial Onboarding Completion Flow

Version : 1.0
Status : Confirmed

---

## 目的

本書は `03-InitialOnboardingAndJoinKey.md` を補足し、初回Onboardingの最終到達状態を確定する。

初回Onboardingの目的は、単にStageArtの機能を説明することではない。

> PersonがPerson Homeへ初めて到達した時点で、そのPersonの現在の活動に必要な最初の居場所を、可能な範囲で既に完成させておくこと

を目的とする。

したがって、従来の

```text
登録
↓
Person Home
↓
何をすればよいか利用者が考える
```

ではなく、以下を基本Flowとする。

```text
登録
↓
姓名確認
↓
StageArt紹介
↓
あなたはどのように活動していますか？
↓
回答に応じた初期設定
↓
Organization作成 / Organization参加 / Production参加
↓
Person Home
```

---

# 01 初回Onboardingの基本原則

1. 初回Person Homeは「設定を始める場所」ではなく、活動を始める拠点とする。
2. Personの最初の回答は恒久的なRoleではない。
3. 一人のPersonが複数の活動形態を持つことを許容する。
4. 団体管理者を選択したPersonは、Onboarding中にOrganizationを作成できる。
5. 団体またはProductionへの参加者は、Onboarding中に参加先を確定できる。
6. Join KeyまたはQRコードを持つ場合、検索を強制しない。
7. 検索を使わないPersonも、Onboardingを完了できる。
8. Onboarding完了後も、Organization作成・参加・Production参加を追加できる。

---

# 02 確定Flow

## Email登録

```text
アカウント登録
↓
Email確認
↓
認証済みセッション確立
↓
姓名入力・確認
↓
StageArt紹介
↓
利用形態選択
↓
必要な初期設定
↓
Onboarding完了
↓
Person Home
```

## Google認証

```text
Google認証
↓
認証済みセッション確立
↓
姓名入力・確認
↓
StageArt紹介
↓
利用形態選択
↓
必要な初期設定
↓
Onboarding完了
↓
Person Home
```

Googleから `family_name` / `given_name` が取得できる場合は、姓名入力画面のDefault値として利用する。

利用者は必ず確認・編集できる。

---

# 03 StageArt紹介

姓名確認後、利用者へ次の趣旨を伝える。

> StageArtは、舞台芸術に関わる人たちのためのプラットフォームです。

利用者には、StageArtで次のような活動ができることを説明する。

- 劇団・企画団体などの団体を管理する。
- 団体に所属する。
- 公演・活動に参加する。
- 稽古や活動予定を確認する。
- 公演・活動を探す。
- 団体を探す。
- 観劇履歴を記録する。

この画面の次に、現在の利用目的を選択する。

---

# 04 利用形態の選択

最初の質問では、少なくとも以下を提示する。

```text
あなたはどのようにStageArtを利用しますか？

□ 団体を管理している
□ 団体に所属している
□ 公演・活動に参加している
□ 観劇を楽しみたい
```

複数選択を許可する。

これはPersonの固定Roleではない。

例えば同一Personが、

- 自分のOrganizationを管理する。
- 別Organizationに所属する。
- 複数ProductionにParticipantとして参加する。
- 観客として観劇履歴を記録する。

ことを同時に許容する。

---

# 05 団体を管理している場合

「団体を管理している」を選択した場合、Person Homeへ進む前にOrganization作成Flowへ入ることを基本とする。

## 最初の入力

初期Onboardingでは、最低限以下を入力する。

```text
団体名
```

将来的に団体種別、活動地域、説明等を追加できるが、初回入力を必要以上に重くしない。

## 作成結果

Organization作成と同時に、作成したPersonを適切なOrganization管理者としてMembershipへ登録する。

概念Flowは以下とする。

```text
団体名入力
↓
Organization作成
↓
作成者のMembership作成
↓
管理権限付与
↓
Onboardingへ戻る
```

したがって、団体管理者としてOnboardingを完了したPersonは、Person Home到達時点で少なくとも一つの管理対象Organizationを持つことを理想状態とする。

---

# 06 団体に所属している場合

「団体に所属している」を選択した場合、次の方法を提示する。

```text
[ 参加コードを入力 ]
[ QRコードを読み取る ]
[ 団体を探す ]
[ あとで設定する ]
```

## 参加コード

8文字程度のJoin Keyを入力し、対象Organizationを直接解決する。

```text
AB7K29XZ
```

解決後は対象Organization名を表示し、利用者が確認した後にMembership Flowへ進む。

## QRコード

QRコードを読み取り、Join Keyと同じ解決Flowへ進む。

QRコードはJoin Keyの別UIであり、別の参加Domainを作らない。

## 検索

Join KeyまたはQRコードを持たない場合にOrganization検索を利用する。

---

# 07 公演・活動に参加している場合

「公演・活動に参加している」を選択した場合、次の方法を提示する。

```text
[ 参加コードを入力 ]
[ QRコードを読み取る ]
[ 公演・活動を探す ]
[ あとで設定する ]
```

Join KeyまたはQRコードからProductionを直接解決できる。

Production参加はOrganization Membershipとは独立した既存Domain原則を維持する。

```text
Join Key
↓
Production解決
↓
対象確認
↓
必要に応じて参加区分選択
↓
Participant作成
```

Onboarding完了時点で、既に参加先が決まっているPersonは検索画面を経由せず、直接そのProductionへ参加できる。

---

# 08 観客として利用する場合

「観劇を楽しみたい」を選択したPersonは、OrganizationまたはProductionへの所属を必須としない。

観客利用を選択した場合でも、後から団体への所属やProduction参加を追加できる。

Onboardingでは必要に応じて、以下を案内する。

- 公演・活動を探す。
- 団体を探す。
- 観劇履歴を記録する。
- そのままOnboardingを完了する。

---

# 09 複数の利用形態を選択した場合

利用者が複数選択した場合は、選択内容を順番に処理する。

推奨順序は以下とする。

```text
1. 団体を管理している
2. 団体に所属している
3. 公演・活動に参加している
4. 観劇を楽しみたい
```

ただし、Organization作成後に「さらに団体に所属する」「公演に参加する」を選択できる。

最後に次を表示する。

```text
最初の設定が完了しました。

ほかにも設定しますか？

＋ 団体を作成する
＋ 団体に参加する
＋ 公演・活動に参加する

[ StageArtをはじめる ]
```

利用者が必要な初期設定を終えた時点でPerson Homeへ進む。

---

# 10 Person Home到達時の状態

Onboarding完了後、Person Homeを表示する。

最上部では、Personの姓を利用して歓迎する。

```text
おはようございます、[姓]さん
```

時間帯に応じて以下を切り替えることができる。

- おはようございます
- こんにちは
- こんばんは

Homeでは、Onboardingで確定した実際の状態に応じて内容を表示する。

## Organization管理者

- 自分が管理する団体
- 次の稽古・予定
- 管理機能

## Organization所属者

- 所属団体
- 次の稽古・予定
- 参加している公演・活動

## Production Participant

- 参加している公演・活動
- 次の稽古・予定

## 観客

- 公演・活動を探す
- 団体を探す
- 観劇履歴

重要なのは、Onboardingで「管理者」と回答しただけで管理機能を表示するのではなく、実際のOrganization / Membership / Permission状態をHome表示の正とすることである。

---

# 11 Join KeyとQRコード

本書は `03-InitialOnboardingAndJoinKey.md` のJoin Key仕様を維持する。

初期実装対象は以下とする。

```text
Organization Join Key
Production Join Key
```

それぞれについて、管理者側に少なくとも次の機能を設ける。

- 参加コード発行
- 参加コード確認
- QRコード表示
- 参加コード再発行または無効化

利用者側では、単一の参加入口から対象を解決できることを基本とする。

```text
参加コードを入力
または
QRコードを読み取る
```

対象種別は利用者が先に選択する必要はない。

StageArtが解決後、対象名称と種別を表示して確認を求める。

---

# 12 Domain / Application上の位置付け

Onboardingそのものを巨大な新規Domain Entityとして扱わない。

Onboardingは、既存のDomain操作を初回UXとして順序付けるApplication / UX上の概念とする。

主な関係は以下とする。

```text
Person
├─ 姓名
├─ Organization Membership
├─ Production Participation
└─ Onboarding Completion State
```

Onboarding中に実行される主要操作は既存または追加されるUse Caseへ接続する。

```text
Create Organization during Onboarding
Join Organization by Join Key
Join Production by Join Key
Resolve Join Key
Scan QR Code
Update Person Name
Complete Onboarding
```

Onboarding Stateは、将来の中断・再開を可能にするためApplication上で管理できるものとする。

ただし、Onboardingの選択結果をPersonの恒久的Roleとして保存してはならない。

---

# 13 Blueprint確定事項

本書により以下を確定する。

1. 初回OnboardingはPerson Home到達前に実施する。
2. 初回Onboardingの目的は、利用者がHome到達時点で可能な範囲で最初の活動先を持つ状態を作ることである。
3. 団体管理者を選択したPersonは、Onboarding中に団体名を入力してOrganizationを作成できる。
4. Organization作成者は作成直後に適切なMembershipと管理権限を持つ。
5. 団体所属者は、Onboarding中にJoin Key、QRコード、または検索によって所属先を決定できる。
6. Production参加者は、Onboarding中にJoin Key、QRコード、または検索によって参加先を決定できる。
7. Join KeyとQRコードはOrganization / Production双方の初期実装対象とする。
8. Organization / Productionの管理画面には参加コード発行・表示・管理導線を設ける。
9. 複数の利用形態を選択できる。
10. Onboarding回答は固定Roleではない。
11. Onboarding完了後もOrganization作成、Organization参加、Production参加を追加できる。
12. Person Homeでは、実際の所属・参加・権限状態に基づく情報を表示する。
13. Person Homeの歓迎表示は「おはようございます、[姓]さん」を基本とする。
14. 初回Homeは「何をすればよいかわからない」状態を残さず、Onboardingで確定したPersonの活動状態を反映する。

---

# 14 既存仕様との関係

本書は `03-InitialOnboardingAndJoinKey.md` を補足する確定仕様であり、初回Onboardingの完了状態について本書を優先する。

既存の以下の原則は変更しない。

- PersonはOrganizationに所属していなくてもStageArt利用者として成立する。
- Organization MembershipとProduction Participantは独立した関係である。
- 観客としての利用はOrganization Membershipを必須としない。
- Join Keyを知っているだけで管理権限を取得してはならない。
- Homeの権限表示はOnboarding回答ではなく実際のDomain状態を正とする。
- Join Keyは検索を完全に置き換えるものではなく、検索と併存する。
- QRコードはJoin Key方式の別UIであり、別の参加Domainを作らない。
