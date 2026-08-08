# StageArt Blueprint
# Domain Model : Participant

Version : 1.0

---

# Purpose

ParticipantはProductionへ参加する人物または団体を管理するドメインである。

StageArtでは出演者だけでなく、スタッフ、制作、協賛など、公演へ参加するすべての主体をParticipantとして管理する。

---

# Concept

Participantは「公演への参加」という事実を表す。

PersonやOrganizationそのものではない。

Productionへどのような立場で参加しているかを表現する。

---

# Identity

ParticipantはParticipantIDによって一意に識別する。

PersonIDやOrganizationIDは識別子ではない。

---

# Relationship

Participantは必ず一つのProductionへ所属する。

Participantは以下のいずれかを参照する。

- Person
- Organization

```
Production
    │
    └── Participant
            ├── Person
            └── Organization
```

---

# Participant Type

Participantは参加区分を持つ。

例）

- Cast
- Staff
- Organizer
- Sponsor
- Supporter

Typeは役割の分類であり、権限ではない。

---

# Role

Participantは表示用の役割名を持つ。

例）

- 主演
- 助演
- 演出
- 脚本
- 舞台監督
- 音響
- 照明
- 制作
- 主催
- 協賛

Roleは公演ごとに自由に設定できる。

---

# Credit

Participantはクレジット順を保持する。

StageArtはこの順番を利用して出演者一覧やスタッフ一覧を表示する。

---

# Visibility

Participantは公開設定を持つ。

公開の場合

Productionページへ表示する。

非公開の場合

内部管理のみ利用する。

---

# History

Production終了後、

Participant情報は削除しない。

Personの出演実績、

Organizationの参加実績はParticipantから自動生成される。

---

# Design Decisions

Participantは「公演への参加」を管理する。

Person情報は保持しない。

Organization情報は保持しない。

出演履歴はPersonへ保存しない。

ParticipantからHistoryを生成する。

---

# Future

将来的に以下へ対応する。

- キャストグループ
- スタッフグループ
- ゲスト出演
- 日替わり出演
- 出演期間
- 出演回指定
- SNSリンク

---

# Design Principles

- Participantは参加という事実を表す。
- Personとは責務を分離する。
- Organizationとは責務を分離する。
- 出演実績はParticipantから生成する。
- Roleは公演ごとに自由に設定できる。
- ParticipantはProductionへ所属する。
