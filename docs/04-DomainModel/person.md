# StageArt Blueprint
# Person

---

# Purpose

Personは舞台芸術に関わる人物を表す。

StageArt利用者である必要はない。

PersonはStageArt全体の中心となるドメインであり、
人物のプロフィール・所属・活動履歴などを管理する。

---

# Concept

Personは人物そのものを表す。

以下を区別しない。

- 役者
- スタッフ
- 演出家
- 脚本家
- 制作
- 音響
- 照明
- 観客
- その他舞台芸術に関わる人物

役割はProductionへのParticipantとして表現する。

Person自身は職種を持たない。

---

# Identity

StageArtでは人物を名前で識別しない。

人物はPersonIDによって一意に識別する。

表示名（DisplayName）はプロフィール情報であり、
人物を識別するキーではない。

芸名変更・改名・表記変更を行っても、
PersonIDは変更されない。

History・Participant・Reservation・Companionなどは
すべてPersonIDによって関連付けられる。

---

# Account

PersonはAccountを持たなくても登録できる。

AccountはStageArt利用者を表す認証情報である。

PersonとAccountは任意に紐付けられる。

本人確認後、
運営がPersonとAccountを関連付ける。

これにより過去の活動履歴を引き継ぐ。

---

# Profile

Personは以下を保持する。

- 表示名
- 表示名（ふりがな）
- プロフィール画像
- プロフィール
- 活動地域
- Webサイト
- SNS

本名は管理対象としない。

StageArtは活動名を管理する。

---

# Privacy

プロフィール項目ごとに公開設定を保持する。

例）

- プロフィール
- 活動地域
- SNS
- Webサイト

公開・非公開は利用者が設定できる。

---

# Membership

Personは複数Organizationへ所属できる。

例）

- 劇団
- 制作会社
- 芸能事務所（将来）

所属期間も保持する。

---

# Participant

PersonはProductionへParticipantとして参加する。

Participantでは

- Category
- Role

を管理する。

例）

Category

- CAST
- STAFF

Role

- ロミオ
- 演出
- 照明
- 音響

ParticipantからHistoryを生成する。

---

# History

PersonにはHistoryが蓄積される。

例）

- 出演履歴
- スタッフ参加履歴
- 観劇履歴

Historyは

AUTO

MANUAL

を区別する。

AUTO

StageArtから自動生成

MANUAL

利用者が過去の活動を登録

---

# Reservation

PersonはReservationとは直接関連しない。

Reservationは代表者およびCompanionを保持する。

本人確認後、

Reservation

↓

Companion

↓

Person

↓

Account

を紐付けることで、
過去の観劇履歴をPersonへ統合できる。

---

# Tag

PersonへTagを付与できる。

初期TagはStageArtが提供する。

利用者は自由にTagを追加できる。

Tag例

- #殺陣
- #歌唱
- #ダンス
- #英語
- #関西弁

Tagはコミュニティによって育成される。

表記ゆれ・重複は運営が定期的に整理する。

---

# Following

AccountはPersonをフォローできる。

フォローすると

- 次回出演
- 新着公演
- 活動履歴更新

などを通知できる。

---

# Identity Linking

PersonはStageArt利用前でも登録できる。

Account登録後、

AIが未紐付けPerson候補を抽出する。

AIは

- 氏名
- 出演履歴
- スタッフ履歴
- 観劇履歴
- その他関連情報

を利用して候補を提示する。

運営が候補を確認し、

本人へ確認メールを送信する。

本人承認後、

AccountとPersonを紐付ける。

AIは紐付けを自動決定しない。

最終判断は運営および本人確認によって行う。

---

# Future

将来的には以下を追加できる。

- 活動名変更履歴
- ポートフォリオ
- 受賞歴
- スキル
- ボイスサンプル
- 写真ギャラリー
- 動画
- 出演依頼受付

---

# Design Principles

- Personは人物そのものを表す。
- 名前は識別子ではなくプロフィール情報である。
- StageArtはPersonIDによって人物を識別する。
- PersonはStageArt利用の有無に関わらず登録できる。
- 過去・現在・未来の活動を一つのPersonへ集約する。
- AIは候補を提示するのみとし、本人情報の確定は人間が行う。

---

# Verification

Personは認証状態を保持する。

認証とは、
StageArtがPersonとAccountの同一性を確認したことを表す。

認証済みPersonにはVerifiedバッジを表示する。

Verifiedは本人確認を目的とした機能であり、
知名度や人気を示すものではない。

Verifiedとなることで、

- なりすまし防止
- プロフィールの信頼性向上
- 出演履歴の本人保証

を実現する。

認証は運営が本人確認を行った後に付与する。

将来的には劇団・事務所等による認証フローにも対応できる。