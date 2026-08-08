# StageArt Blueprint
# Domain Model : Tag

Version : 1.0

---

# Purpose

TagはStageArt全体で利用する自由検索用ドメインである。

Tagは検索性向上とコミュニティによる分類を目的とする。

---

# Concept

TagはCategoryやGenreでは表現できない情報を表現する。

例）

- #殺陣
- #歌唱
- #ダンス
- #女性限定
- #初心者歓迎
- #親子向け

---

# Identity

TagはTagIDによって一意に識別する。

タグ名は識別子ではない。

---

# Relationship

Tagは以下へ付与できる。

- Person
- Organization
- Production
- Performance

---

# Management

利用者は自由にTagを追加できる。

追加されたTagはStageArt全体で共有される。

表記ゆれや重複は運営が定期的に整理する。

---

# Search

Tagは検索インデックスとして利用する。

Tagによって

- 公演検索
- 劇団検索
- 人物検索

を実現する。

---

# Future

将来的に

- 人気Tag
- 関連Tag
- Tagランキング
- AIによるTag提案

へ対応する。

---

# Design Principles

- Tagは自由な分類を表す。
- 利用者は追加できる。
- TagはStageArt全体で共有する。
- 表記ゆれは運営が管理する。
