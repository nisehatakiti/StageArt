# StageArt Blueprint
# 04 - Domain Model

---

# Purpose

Domain ModelはStageArtが管理する業務上の概念（ドメイン）を定義する。

StageArtはデータベースや画面を中心に設計するのではなく、
舞台芸術における業務そのものをドメインとして表現する。

Business Flowが利用者の行動を定義するのに対し、
Domain ModelはStageArt内部で管理される概念と責務を定義する。

---

# Domain Structure

```
Organization
      │
   Project
      │
 Production
 ├── Performance
 ├── Participant
 ├── Category
 ├── Genre
 └── Tag

Performance
├── Seats
└── Reservation

History
```

---

# Domain Overview

## Organization

劇団・ユニット・制作会社・企業などの団体を表す。

一つのOrganizationは複数のProjectを持つ。

---

## Project

Projectは公演制作プロジェクトを表す。

利用者はProjectを直接意識しない。

利用者が「公演を作る」を実行すると、
StageArtは内部でProjectを自動生成する。

Projectには将来的に以下が紐付く。

- 稽古
- スケジュール
- タスク
- ドキュメント
- 予算
- 収支
- 助成金

ProjectはStageArt内部で制作全体を管理するInternal Domainである。

---

## Production

Productionは利用者・観客へ公開される「公演」を表す。

Productionには以下が含まれる。

- タイトル
- 公演概要
- 公演画像
- Category
- Genre
- Tag
- Participant
- Performance

一つのProductionは複数のPerformanceを持つ。

---

## Performance

Performanceは実際の公演回を表す。

例）

- 8/1 14:00
- 8/1 18:00
- 8/2 13:00

Performanceには以下が紐付く。

- Seats
- Reservation

---

## Seats

SeatsはPerformanceに存在する座席を表す。

Seatsは座席情報のみを保持する。

予約状態は保持しない。

座席の予約状況はReservationから判断する。

---

## Reservation

Reservationは予約を表す。

Reservationは

- 予約者
- Performance
- チケット種別
- 枚数

を管理する。

Reservationは子エンティティとしてReservationSeatを持つ。

### 自由席

ReservationSeatが存在しない場合は自由席とする。

### 指定席

ReservationSeatへSeatを登録する。

Reservationは必ずQuantityを保持する。

受付はReservationのStatus変更で管理する。

CheckInドメインは持たない。

---

## Participant

ParticipantはProductionへ参加するPersonまたはOrganizationを表す。

StageArtでは

- 出演者
- スタッフ
- 協賛企業
- 制作協力
- 会場提供

などを別ドメインで管理しない。

すべてParticipantとして統一管理する。

Participantは

- Person
または
- Organization

へ紐付く。

ParticipantはCategoryによって役割を表現する。

例）

| 対象 | Category | Role |
|-------|----------|------|
| Person | CAST | ロミオ |
| Person | STAFF | 演出 |
| Organization | SPONSOR | 協賛 |
| Organization | SUPPORT | 制作協力 |
| Organization | VENUE | 会場 |

この設計により

Personから見れば

- 出演履歴
- スタッフ参加履歴

Organizationから見れば

- 協賛履歴
- 制作協力履歴
- 会場利用履歴

をすべて同じ仕組みで管理できる。

通常はProduction単位でParticipantを登録する。

公演回ごとに入力する必要はない。

---

## History

HistoryはStageArtにおける活動履歴を表す。

HistoryはParticipantおよびReservationから自動生成される。

利用者の立場によって表示内容が変化する。

例）

Person

- 出演履歴
- スタッフ参加履歴
- 観劇履歴

Organization

- 公演履歴
- 協賛履歴
- 制作協力履歴
- 会場提供履歴

HistoryはStageArtが自動生成する履歴（AUTO）と、
利用者が過去の活動を登録する履歴（MANUAL）の2種類を持つ。

これによりサービス開始以前の活動も資産として蓄積できる。

---

## Category

Productionの公開形態を表すマスター。

例）

- 舞台
- ミュージカル
- 朗読劇
- ダンス
- 音楽ライブ
- 映像作品
- 配信
- ワークショップ

---

## Genre

作品ジャンルを表すマスター。

例）

- コメディ
- ホラー
- ミステリー
- SF
- 青春
- 恋愛

---

## Tag

TagはStageArt全体で利用する検索・分類用データである。

Tagは

- Person
- Organization
- Production
- Performance

へ付与できる。

初期状態ではStageArtがプリセットTagを提供する。

利用者は自由にTagを追加できる。

追加されたTagはTagマスターへ登録され、
以後すべての利用者が利用できる。

Tagの表記ゆれ・重複についてはシステムで厳密に制御しない。

管理者が定期的に整理・統合することで検索品質を維持する。

---

# Domain Classification

## Core Domain

- Person
- Organization
- Project
- Production
- Performance
- Participant
- Reservation
- History

## Supporting Domain

- Seats
- Category
- Genre
- Tag

---

# Golden Rule

利用者はドメインモデルを意識しない。

利用者は

- 劇団を作る
- 公演を作る
- チケットを予約する

だけを操作する。

Projectなどの内部ドメインはStageArtが自動生成・管理する。

利用開始後の活動履歴は可能な限り自動で蓄積される。

---

# Future Domain

Version 1.5以降

- Rehearsal
- Finance
- FanClub
- Store
- Notification
- Messaging


---

# Identity Linking

StageArtではPersonおよびOrganizationは、StageArt利用の有無に関わらず登録できる。

そのため、Person・OrganizationとAccountは独立した概念として管理する。

AccountはStageArt利用者を一意に識別するための認証情報であり、
PersonまたはOrganizationへ任意に紐付けられる。

Accountを持たないPerson・Organizationは「未紐付け」として管理する。

---

## Identity Linking Flow

Account登録後、StageArtはAIを利用して未紐付けPerson・Organizationとの照合を行う。

AIは以下の情報をもとに候補を抽出する。

- 氏名
- 団体名
- 出演履歴
- スタッフ参加履歴
- 協賛履歴
- 公演情報
- その他StageArt内の関連情報

AIは候補ごとに一致度を算出し、
運営へ確認候補として提示する。

AIはPerson・Organizationを自動で紐付けない。

---

## Human Review

運営はAIが提示した候補を確認し、
本人確認を実施する候補を選択する。

---

## Identity Confirmation

運営が候補を選択すると、
StageArtは本人確認メールを自動生成・送信する。

例）

件名

StageArt 登録情報確認のお願い

本文

StageArtへご登録ありがとうございます。

登録情報を確認したところ、
以下の公演参加履歴が見つかりました。

2026年
『夏の終わり』

スタッフ（照明）

こちらの参加履歴はご本人のもので間違いありませんか。

【はい】

【いいえ】

本人から承認を得た後、
運営がAccountとPersonまたはOrganizationを紐付ける。

---

## AI Policy

AIは以下を担当する。

- 候補検索
- 一致度算出
- 確認メール生成
- 運営への候補提示

AIは本人情報を自動で確定しない。

最終判断は運営および本人確認によって行う。

---

## Design Principle

StageArtでは

「AIは決定しない。
AIは提案する。」

を基本方針とする。

本人情報・履歴・参加情報など、
重要なデータの確定は必ず人間の確認を経て行う。

これにより、高いデータ品質と利用者の信頼性を維持する。