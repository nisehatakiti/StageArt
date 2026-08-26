# StageArt Blueprint

# Publication State Model (Supplement)

Version : 1.0

---

# Purpose

`PublicPageGenerationPolicy.md` と `PublicSiteGenerationPolicy.md` は、
公開ページの生成・可視性についてすでに正式仕様を持つ（本書はその内容を
変更しない）。しかし、それらのドキュメントは「Publicity Status
(PRIVATE/PUBLIC)」と「情報公開日時」という2つの独立した概念を扱ってお
り、実装側の状態モデルとしてどう対応するかが明文化されていなかった。

本書は、その対応関係と、現在の実装が正式仕様のどこまでを満たしている
かを明確にする補足資料である。**正式仕様を変更するものではない。**

---

# Existing Blueprint (unchanged, cited here for reference)

`PublicSiteGenerationPolicy.md`:
- Production は `Publicity Status`（`PRIVATE` / `PUBLIC`）を持つ。
- `PRIVATE` から `PUBLIC` への変更が、公開サイトへの反映トリガーの一つ。

`PublicPageGenerationPolicy.md`:
- Production の各情報は「情報公開日時」を個別に持てる。
- ページ生成後もPHPが現在日時と情報公開日時を比較し、到達前は非表示、
  到達後は自動表示する。CRONによる再生成は不要（V1）。

これらを合わせると、正式仕様が意図する状態は実質的に以下の3段階である。

```text
PRIVATE (Publicity Status)
    ↓ 管理者がPUBLICへ変更
PUBLIC, かつ 現在日時 < 情報公開日時   … ページは存在するが対象情報は非表示
    ↓ 情報公開日時 到達
PUBLIC, かつ 現在日時 >= 情報公開日時  … 対象情報が表示される
```

---

# Current implementation (as of this Web β phase)

`Organization.publishedAt` / `Production.publishedAt` は、単純な
「null = 未公開」「非null = 公開中」の2値フラグである。
`isPublished()` は非null判定のみを行い、**現在日時との比較を行わない**。

したがって、`publishedAt` に未来の日時を設定しても、その値は無視されず
即座に「公開中」として扱われてしまう - 「予定」を表現できない。これは
バグではなく、Web β版でスコープ外とされた単純化であり、上記の正式仕様
（`PublicPageGenerationPolicy.md`）とは一致していない。

Public Slug / Public Page そのもの（`/organizations/by-slug/{slug}`,
`/productions/by-slug/{slug}`）は実装済みだが、Blueprintが定める静的PHP
生成・CRON非依存のスケジュール表示アーキテクチャは実装されていない。
現在はDBへの都度アクセスによる動的解決のみ。

---

# Target state model (design confirmation, not implemented this round)

最低限、以下の4状態を将来の実装が扱えるようにする。

| State | 意味 | 対応するBlueprint概念 |
|---|---|---|
| DRAFT | 未公開。Publicity StatusがPRIVATE相当 | `Publicity Status = PRIVATE` |
| SCHEDULED | 公開設定済みだが、情報公開日時が未到達 | `Publicity Status = PUBLIC` かつ `現在日時 < 情報公開日時` |
| PUBLISHED | 情報公開日時到達済み、一般公開中 | `Publicity Status = PUBLIC` かつ `現在日時 >= 情報公開日時` |
| ARCHIVED | Production Lifecycle終了後も記録として公開維持 | `PublicSiteLifecycle.md`の「公演終了後もProduction Public Siteは削除せず」 |

重要：ARCHIVEDはPublicity Statusとは独立したProduction Lifecycle側の状態
であり、`PublicSiteLifecycle.md`が明記する通り、公演終了は
「StageArtのProduction LifecycleとPublic Performance終了判定は独立」
とされている。したがってARCHIVEDは「公開状態が終わる」ことを意味せず、
「公演記録として表示し続けるが、Lifecycle上は終了している」ことを表す
表示上の区分として扱う。

---

# Minimal implementation path (for a future phase, not this round)

正式仕様（時刻比較によるスケジュール表示）を満たすために必要な最小限の
変更は、CRONや静的サイト生成の導入ではなく、以下で足りる。

1. `publishedAt` を「即時公開日時」ではなく「公開日時（過去・現在・未来
   いずれも可）」として扱う。
2. `isPublished()` を `publishedAt !== null` から
   `publishedAt !== null && now() >= publishedAt` へ変更する。

これは`PublicPageGenerationPolicy.md`の「No CRON Requirement for
Visibility」の考え方と一致する - CRONによる再生成なしに、リクエスト時
点の比較だけで「予定」を実現できる。

**今回のフェーズではこの変更を実施しない**（Modular Architecture整理タ
スクのスコープ外、`03-ModularArchitecture.md`の対象外リストにも整合）。
後続実装の対象として明記する。
