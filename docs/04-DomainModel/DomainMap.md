# StageArt Blueprint
# DomainMap

---

# Purpose

DomainMapはStageArt全体のドメイン構造を表す。

ER図やデータベース設計ではなく、
StageArtというサービスを構成する概念同士の関係を定義する。

---

# Domain Map

```text
                             Account
                                │
                     (利用者・認証情報)
                                │
              ┌─────────────────┴─────────────────┐
              │                                   │
          Person                           Organization
              │                                   │
              ├──────────────┐                    │
              │              │                    │
          Membership     History                 Project
              │              ▲                    │
              │              │                    │
              └──────────────┼────────────────────┘
                             │
                        Participant
                             │
                        Production
               ┌──────────┼──────────┐
               │          │          │
          Category     Genre       Tag
               │
          Performance
               │
      ┌────────┴────────┐
      │                 │
    Seats          Reservation
                         │
              ┌──────────┴──────────┐
              │                     │
      ReservationSeat         Companion
```

---

# Domain Overview

## Account

StageArt利用者を一意に識別する認証情報。

Googleログイン・メールログイン等を管理する。

AccountはPersonまたはOrganizationへ紐付く。

---

## Person

舞台芸術に関わる人物。

StageArt利用の有無に関わらず登録できる。

役者・スタッフ・観客などを区別しない。

---

## Organization

団体を表す。

例）

- 劇団
- 制作会社
- 協賛企業
- 芸能事務所
- 劇場（将来）

StageArt利用の有無に関わらず登録できる。

---

## Membership

PersonとOrganizationの所属関係を管理する。

---

## Project

公演制作プロジェクト。

利用者は直接意識しないInternal Domain。

将来的に

- 稽古
- タスク
- 予算
- ドキュメント

などを管理する。

---

## Production

観客へ公開される公演。

Productionには

- Participant
- Performance
- Category
- Genre
- Tag

が紐付く。

---

## Participant

Productionへ参加するPersonまたはOrganizationを表す。

Participantは

- Person
- Organization

のどちらにも紐付けできる。

例）

- 出演者
- スタッフ
- 協賛
- 制作協力
- 会場提供

など。

役割はCategoryとRoleで表現する。

---

## Performance

Productionの公演回。

例）

- 8/1昼
- 8/1夜

Performanceには

- Seats
- Reservation

が紐付く。

---

## Seats

Performanceの座席。

予約状態は保持しない。

---

## Reservation

観客予約。

Reservationには

- QRコード
- チェックイン状態
- Companion

が紐付く。

受付はReservation単位で管理する。

---

## ReservationSeat

指定席予約時にSeatとの対応を保持する。

自由席の場合は保持しない。

---

## Companion

同行者。

Reservationへ紐付く。

Companionは

- Person
- Account

へ後から紐付けできる。

これにより観劇履歴を継承できる。

---

## History

StageArtの活動履歴。

以下を統一的に管理する。

Person

- 出演履歴
- スタッフ履歴
- 観劇履歴

Organization

- 公演履歴
- 協賛履歴
- 制作協力履歴

Historyは

AUTO

MANUAL

の2種類を持つ。

---

## Category

公演形態マスター。

例）

- 舞台
- ライブ
- 映画
- 配信

---

## Genre

作品ジャンルマスター。

例）

- コメディ
- ホラー
- ミステリー

---

## Tag

検索用タグ。

StageArtは初期Tagを提供する。

利用者は自由にTagを追加できる。

Tagは

- Person
- Organization
- Production
- Performance

へ付与できる。

Tagの表記ゆれは運営が定期的に整理する。

---

# Design Principles

- 利用者はProjectなどの内部ドメインを意識しない。
- Person・OrganizationはStageArt利用の有無に関わらず登録できる。
- Accountは利用者を一意に識別する。
- ParticipantはProductionへの参加を統一管理する。
- Reservationは受付単位で管理する。
- Companionは履歴管理を目的とする。
- HistoryはStageArt最大の資産である。
- StageArtは未来だけでなく過去の活動も資産として管理する。