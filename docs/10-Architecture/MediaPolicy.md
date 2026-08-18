# StageArt Blueprint

# Media Policy

Version : 1.0

---

# Purpose

StageArtで扱うプロフィール画像、Organization画像、Productionフライヤー、Production写真等の画像保存方式を統一する。

StageArtは画像原本の保管サービスを目的とせず、公開WebページおよびStageArt UIで利用するための最適化済み画像を保持する。

---

# Upload Limit

画像アップロード時の入力ファイルサイズ上限は、原則10MBとする。

10MBは保存後の容量ではなく、StageArtが受け付けるアップロード元ファイルの上限である。

アップロード後はStageArt側で画像を正規化するため、元ファイルの容量をそのまま永続保存しない。

---

# Normalized Image Sizes

アップロードされた画像は、原本を保存せず、以下の2種類へ変換して保存する。

- Main Image：長辺1600px
- Thumbnail：長辺600px

縦横比は維持し、画像を引き伸ばしたりトリミングしたりして比率を変更してはならない。

横長画像、縦長画像、正方形画像のいずれも同一ルールを適用する。

例：

- 4000 × 2250 → Main 1600 × 900 / Thumbnail 600 × 338
- 2250 × 4000 → Main 900 × 1600 / Thumbnail 338 × 600
- 3000 × 3000 → Main 1600 × 1600 / Thumbnail 600 × 600

1600px未満の画像については不要な拡大を避け、元画像の解像度を超えて引き伸ばさない。

---

# Original File Policy

アップロードされた原本ファイルは永続保存しない。

変換処理完了後、StageArtの通常ストレージには原本を残さず、Main ImageとThumbnailのみを保存する。

これにより、劇団・個人が長期間利用しても原本画像の蓄積によってストレージを圧迫しない構成とする。

---

# Supported Media Targets

少なくとも以下の画像用途に同一の正規化ルールを適用する。

- Person Profile Image
- Organization Logo / Image
- Production Flyer
- Production Photo

将来追加されるWeb公開用画像についても、原則として本Policyを適用する。

---

# Storage Model

一つの画像Assetについて、StageArtは以下の2つの利用可能な派生画像を管理する。

```text
Image Asset
 ├── Main Image     長辺1600px
 └── Thumbnail      長辺600px
```

Main Imageは詳細表示・大きなカード・公開ページ等に使用する。

Thumbnailは一覧・カード・小窓等、通信量を抑える必要がある表示に使用する。

画面側が原本画像を参照することを前提としてはならない。

---

# Format Normalization

アップロード時のJPEG、PNG、その他StageArtが受け付ける画像形式は、StageArt側でWeb表示に適した保存形式へ正規化する。

保存形式の具体的な選択はInfrastructure実装時に決定するが、透過情報が必要な画像については透過を失わないことを必須とする。

画像形式を理由に利用者が事前に画像編集ソフトで変換しなければならない設計にはしない。

---

# Replacement and Cleanup

プロフィール画像やフライヤー等を差し替えた場合、旧Assetが他のBusiness Factから参照されていないことを確認した上で、不要になった旧Main Image / Thumbnailを削除できる設計とする。

同一画像Assetを複数箇所で参照している場合は、一箇所の差し替えによって他の参照を破壊してはならない。

ストレージ上の孤立Assetを定期的に検出・削除できるInfrastructure設計を妨げない。

---

# Privacy and Access

Person Profile Image等の個人関連画像は、Public設定と整合するアクセス制御を行う。

非公開プロフィール画像を一般公開URLから取得できる状態にしてはならない。

OrganizationおよびProductionの公開画像についても、Public Visibilityと整合した公開制御を行う。

---

# Business Rules

1. アップロード元ファイルは原則10MB以下とする。
2. 原本ファイルは永続保存しない。
3. 保存する画像はMain ImageとThumbnailの2種類を基本とする。
4. Main Imageの長辺は1600pxを基準とする。
5. Thumbnailの長辺は600pxを基準とする。
6. 縦横比を維持する。
7. 元画像が1600px未満の場合は不要な拡大を行わない。
8. プロフィール画像、Organization画像、Productionフライヤー、Production写真に同一ルールを適用する。
9. 非公開画像を一般公開してはならない。
10. 画像差し替え時に他の参照画像を破壊してはならない。
11. 原本保存を前提とした外部ストレージ用途にはしない。

---

# Design Principle

StageArtの画像管理は「大きな原本を保管すること」ではなく、「必要十分な品質で、公開ページを軽量に表示すること」を目的とする。

1600pxのMain Imageと600pxのThumbnailへ統一することで、UI実装を単純化し、公開ページの通信量とConoHaストレージ使用量を抑える。
