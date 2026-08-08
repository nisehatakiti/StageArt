# StageArt Blueprint
# Domain Model : Production

Version : 1.0

---

# Purpose

ProductionはStageArtにおいて観客へ公開される「公演」を管理するドメインである。

Productionは公演の基本情報、出演者、公演回など、観客が参照する情報を管理する。

ProductionはProjectから生成される公開情報であり、制作管理を目的としない。

---

# Concept

Productionは舞台芸術作品の公開単位である。

利用者が「公演を作る」を実行すると、StageArtはProjectとProductionを自動生成する。

Productionは観客へ公開される情報を保持し、Projectは制作活動を管理する。

---

# Identity

ProductionはProductionIDによって一意に識別される。

公演タイトルは識別子ではない。

同名公演が存在しても問題ない。

---

# Relationship

Productionは必ず一つのProjectに所属する。

```
Organization
    │
    └── Project
            │
            └── Production
                    ├── Performance
                    ├── Participant
                    ├── Category
                    ├── Genre
                    └── Tag
```

Productionは以下のドメインを管理する。

- Performance
- Participant
- Category
- Genre
- Tag

---

# Public Information

Productionは以下の公開情報を保持する。

- 公演タイトル
- キャッチコピー
- あらすじ
- 公演画像
- 公演期間
- 公演ステータス
- 公開URL

将来的には

- PV動画
- フライヤー
- ギャラリー

なども追加できる。

---

# Publication

Productionは公開状態を持つ。

- Draft
- Private
- Published
- Closed
- Archived

PublishedとなったProductionのみ観客へ公開される。

---

# Participant

Productionには複数のParticipantが登録される。

Participantは

- Person
- Organization

のどちらにも紐付けられる。

出演者・スタッフ・協賛・制作協力などを統一的に管理する。

---

# Performance

Productionは一つ以上のPerformanceを持つ。

Performanceは実際の公演回を表す。

例）

- 8/1 14:00
- 8/1 18:00
- 8/2 13:00

予約受付はPerformance単位で行う。

---

# Search

Productionは検索対象となる。

検索条件例

- キーワード
- Category
- Genre
- Tag
- 出演者
- 劇団
- 開催地域
- 開催期間

---

# History

Production終了後、

StageArtはProductionを削除しない。

ProductionはHistory生成の元データとして永続的に保持する。

---

# Design Decisions

Productionは公開情報のみを保持する。

以下は保持しない。

- 稽古
- タスク
- 予算
- 収支
- ドキュメント

これらはProjectが管理する。

Productionは公開、

Projectは制作、

という責務を持つ。

---

# Future

将来的に以下へ対応する。

- 配信公演
- 公演シリーズ
- 関連作品
- レビュー
- アンケート
- ファンクラブ限定公開

---

# Design Principles

- Productionは観客へ公開される公演である。
- ProductionはProjectから生成される。
- Productionは公開情報のみを管理する。
- 制作管理はProjectが担当する。
- Productionは終了後も削除しない。
- ProductionはHistory生成の基点となる。
