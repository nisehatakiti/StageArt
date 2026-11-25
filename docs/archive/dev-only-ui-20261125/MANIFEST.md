# Dev独自画面の退避記録（archive/dev-only-ui-20261125）

作成日：2026-11-25
目的：Dev環境（dev.stageart.top / dev-api.stageart.top）にのみ存在する画面・関連実装を、将来再利用できる状態で保存する。
このブランチは **保存専用** であり、`feature/user-account-identity` へマージしない。移植は別作業として行う。

---

## 1. 退避元と識別情報

| 項目 | 内容 |
|---|---|
| ブランチ起点 | `df24b62`（`preserve/dev-wip-20261027`。`2301dd4` の上に、Devへ載せた未コミット作業315ファイルを記録したスナップショット） |
| 起点の親 | `2301dd4`（元のworktreeの `feature/user-account-identity` ローカルHEAD） |
| 開発ブランチとの比較基準 | `8472c48`（PR #9 head、修正コミット前）。`c6480c2`（`origin/feature/user-account-identity`） |
| Dev実コードのスナップショット | ローカル `C:\StageArt-Backups\dev-code-20261125\`（2026-11-25取得） |
| ├ プラグイン | `dev-api-plugin-stageart.tar.gz`  sha256 `a4c156cfedde3b7cde428cf96dd6a4b34765e6a6154e25e87fd914c3a6dc6669` |
| ├ フロントエンド（ビルド済み静的ファイル） | `dev-frontend.tar.gz`  sha256 `657b08c0eb024ba49532f0c1e082fe92036c00e4c521ae5968ba386de1b715bc` |
| └ Dev上の全ファイルのsha256（2041件、取得後に全件一致を確認） | `remote-sha256.txt`  sha256 `ab75ce201d72df972a4a243cb3ef16977762a32df5f3d35e9ce5b4acb9e8f069` |
| Devのデプロイ記録 | Dev上 `wp-content/plugins/stageart/.deploy-manifest.txt` と `dev.stageart.top/.deploy-manifest.txt`（tarに含まれる） |

### Devの実コードと `df24b62` の関係（プラグイン `src/` ほか1076ファイルを比較）

- 一致 1053 / 相違 17 / Devに無い 6。
- 相違・欠落は、Devへのデプロイ（2026-10-27）後に元のworktreeでコミットされた `2616bc9`（団体会計の公演別実績・費目別予実）と `2301dd4`（チェックインの現金計上）に由来する。相違17件のうち15件は `fc963c6` 時点の内容と一致し、残り2件（`AccountingModuleBootstrap.php`, `WordPressJournalEntryRepository.php`）は当時の未コミット作業の途中版。
- つまり `df24b62` は「Devの独自機能をすべて含み、さらにその後の2コミット分が進んだ」状態である。Devと完全に同じバイト列が必要な場合は上記tarを使う。
- 相違ファイルの一覧：`C:\StageArt-Backups\dev-code-20261125\dev-vs-df24b62-plugin-diff.txt`

### Gitに入れていないもの

- 公開ページのヒーロー画像プリセット `plugin/assets/hero/**`（122ファイル、約130MB）。Dev上と元のworktreeにのみあり、どのコミットにも含まれていない。容量が大きいためこのブランチにもコミットせず、Devのtar（プラグイン）で保存した。ファイル名とsha256は `hero-assets-sha256.txt` に記録した。`manifest.json` も同じ場所にある。

---

## 2. 退避対象の機能と主なファイル

ファイルの状態は `8472c48`（PR #9）との比較。A＝Devにのみ存在、M＝両方にあるが内容が異なる。

### 2.1 経費の申請・承認画面

注意：Devにあるのは **承認待ち一覧** と **経費詳細（承認・差し戻し、申請者の提出・取り下げ・再提出）** の画面である。**経費の新規入力フォームはDevにも存在しない**（経費の作成はDevでもAPIのみ）。

| ファイル | 状態 | 役割 |
|---|---|---|
| `mobile-rn/src/app/(app)/productions/[id]/expenses.tsx` | A | 公演の経費申請一覧（承認待ち／すべて） |
| `mobile-rn/src/app/(app)/expenses/[expenseId].tsx` | A | 経費詳細。承認（revision付き）・差し戻し（理由必須）・申請者の提出／取り下げ／再提出 |
| `mobile-rn/src/features/expense/{api,types,permissions,useExpenses}.ts` | A | API呼び出し・型・表示権限・React Queryフック |
| `mobile-rn/src/features/notifications/notificationRoute.ts` | A | 経費通知から詳細画面への遷移 |
| `mobile-rn/src/app/(app)/production/[id]/accounting.tsx`, `production/[id]/_layout.tsx`, `home.tsx` | M | 会計タブから経費一覧への導線、ルーティング |
| `mobile-rn/src/features/production/permissions.ts`, `mobile-rn/src/types/api.ts` | M | `canViewProductionAccounting` 等の判定、APIの型 |
| テスト `mobile-rn/src/app-tests/expense/**`（15件＋fixture） | A | 画面テスト |

依存するAPI（PR #9の系統にも同じルートがある）：
`GET /productions/{id}/expenses`、`GET /expenses/{id}`、`PATCH /expenses/{id}/{submit|withdraw|resubmit|approve|reject}`。承認は `{ revision, approved_amount? }`。

バックエンドの相違（Dev側の `plugin/src/Application/Expense/*`, `Domain/Expense/*`, `ExpenseRestController.php`, `AccountingModuleBootstrap.php`, `WordPressExpenseRepository.php`, `LegacyExpenseMigration*.php` など）：PR #9の系統のほうが新しい（R3〜R14-Aのレビュー修正、経費編集は申請者本人のみ、承認金額は申請金額と一致、旧経費移行の監査・復元など）。**移植時はバックエンドをDev版に戻さず、PR #9系統のAPIに画面を合わせること。**

移植時に必要な作業：
- 画面とフックを開発ブランチへ持ち込み、APIの応答型（`types/api.ts`）を開発ブランチの `ExpenseRestController` に合わせる。
- 承認金額の変更UI（`expense-detail-approve-changed-amount` テスト）は、現行仕様（承認金額＝申請金額、ExpenseApprovalWorkflow.md v1.10以降）と矛盾するため削除または差し戻し導線に置き換える。
- 表示権限は現行の役割（公演管理者・公演代理人・会計担当）に合わせる（`canManageAccounting` 等、PR #9の `features/production/permissions.ts`）。
- 経費の新規入力画面は別途設計が必要（Devにも無い）。

### 2.2 公開ページ編集画面

| ファイル | 状態 | 役割 |
|---|---|---|
| `mobile-rn/src/components/public-page-settings-panel.tsx` | A | テーマ・ヒーロー画像の設定パネル |
| `mobile-rn/src/components/public-page-content-panel.tsx`, `public-content-layout.tsx` | A | 公開ページのコンテンツ（お知らせ等）と並び順の編集 |
| `mobile-rn/src/components/public-page-hero.tsx`, `organization-logo.tsx` | A | 公開ページのヒーロー表示、団体ロゴ |
| `mobile-rn/src/features/publicPage/**` | A | API・型・テーマ・フック |
| `mobile-rn/src/app/(app)/organizations/[id]/edit.tsx`, `productions/[id]/edit.tsx` | M | 団体・公演の編集画面に「公開ページ」タブを追加 |
| `mobile-rn/src/app/[organizationSlug]/index.tsx`, `[organizationSlug]/[productionSlug]/index.tsx` | M | 公開側ページへのテーマ・ヒーロー・コンテンツ反映 |
| `mobile-rn/src/components/themed-text.tsx`, `hooks/use-theme.ts` | M | テーマ対応 |
| `plugin/src/Application/PublicPage/**`（22件）, `plugin/src/Domain/PublicPage/**`（10件） | A | ユースケース・ドメイン |
| `plugin/src/Presentation/Rest/PublicPageSettingsRestController.php`, `PublicPageContentRestController.php`, `PublicResponseCachePolicy.php` | A | REST API と公開キャッシュヘッダー |
| `plugin/src/Infrastructure/WordPress/Persistence/WordPressPublicPage{Settings,Content}Repository.php`, `WordPressContentLayoutRepository.php` | A | 永続化 |
| `plugin/src/Infrastructure/WordPress/Media/{WordPressHeroImageUploader,WordPressHeroPresetCatalog,ImagickImageOptimizer}.php`, `Application/Media/ImageOptimizationPolicy.php` | A | ヒーロー画像のアップロード・最適化（Imagick）・プリセット一覧 |
| `plugin/assets/hero/**` | Gitなし | プリセット画像（上記1.参照） |
| `plugin/src/Presentation/Plugin.php`, `Infrastructure/WordPress/Schema/Installer.php`, `Organization/ProductionRestController.php`, `WordPress{Organization,Production}Repository.php` | M | ルート登録・テーブル定義・公開APIへの反映 |
| テスト `web-*-public-page-*.test.tsx`, `public-page-hero.test.tsx`, `PublicPageTheme.test.ts`, `plugin/tests/**/PublicPage*` | A | |

依存するAPI（PR #9の系統には無い）：
`GET/PUT /{organizations|productions}/{id}/public-page`、`POST .../public-page/hero-image`、`/organizations/{id}/public-page/{content|content-layout|contents|contents/{content_id}}`。

依存するテーブル（Dev DBに存在、PR #9のInstallerには無い）：`stageart_public_page_settings`、`stageart_public_page_layouts`、`stageart_public_page_contents`。

移植時に必要な作業：
- バックエンド（ユースケース・REST・リポジトリ・Installerのテーブル定義・SchemaUpgraderの版）を開発ブランチへ移植する。開発ブランチのスキーマ版（1.48.0以降）と番号が衝突しないよう、新しい版で追加する。
- 認可（`PublicPageAuthorization`）を現行の役割（公演代理人は公開ページを編集できる。PR #9）に合わせる。
- ヒーロー画像のプリセットを配置する方法（Git管理するか、配置手順で別送するか）を決める。

### 2.3 団体代理人の管理領域

| ファイル | 状態 | 役割 |
|---|---|---|
| `mobile-rn/src/app/(app)/organizations/[id]/members.tsx` | M | メンバー管理画面に代理人ごとの「管理領域」編集を追加 |
| `mobile-rn/src/features/organization/{api.ts,useOrganizations.ts,permissions.ts}` | M/A | 管理領域の取得・更新API、フック、権限判定 |
| `mobile-rn/src/components/chrome/useNavMenu.ts`, `components/organization-placeholder-screen.tsx` | M | 管理領域に応じたメニュー表示 |
| `plugin/src/Domain/Membership/DelegateArea.php` | A | 管理領域の値オブジェクト |
| `plugin/src/Application/Organization/{List,Update}OrganizationDelegateAreas*.php`, `OrganizationDelegateAreasResult.php` | A | ユースケース |
| `plugin/src/Application/Organization/OrganizationAuthorizationService.php`, `Core/Contract/OrganizationCapability.php`, `Core/Adapter/CoreAuthorizationAdapter.php` | M | 管理領域による団体スコープの認可 |
| `plugin/src/Application/{JoinKey,Membership,OrganizationInvitation}/*`, `CreateProductionUseCase.php` | M | 管理領域を持つ代理人への参加コード・参加申請・招待・公演作成の許可 |
| `plugin/src/Infrastructure/WordPress/Persistence/WordPressMembershipRepository.php`, `Schema/Installer.php` | M | 永続化とテーブル定義 |
| `plugin/src/Presentation/Rest/OrganizationRestController.php` | M | ルート |
| テスト `web-organization-members-delegate-areas-*.test.tsx`, `membersScreenHarness.tsx`, `plugin/tests/**/OrganizationDelegateArea*`, `JoinKeyAndMembershipRequestAuthorizationTest.php`, `SchemaUpgrade143PathTest.php` | A | |

依存するAPI（PR #9の系統には無い）：`GET /organizations/{id}/delegates`、`PUT /organizations/{id}/delegates/{personId}/areas`。
（`POST /organizations/{id}/delegates` と `DELETE .../delegates/{personId}` はPR #9の系統にもある。）

依存するテーブル：`stageart_membership_delegate_areas`（Dev DBに3行。Dev schema 1.43.0で追加）。

移植時に必要な作業：
- 参加コードの認可はPR #9で「団体の参加コードはOwnerのみ」に確定済み。Dev版は「管理領域を持つ代理人にも許可」しているため、**そのまま移植すると確定仕様に反する**。移植前に仕様の確認が必要。
- スキーマ版の付け直し（Devの1.43.0は開発ブランチの版と衝突する）。

---

## 3. 取り出し方

```sh
# このブランチから特定ファイルを取り出す（作業ツリーを汚さずに確認する例）
git show archive/dev-only-ui-20261125:"mobile-rn/src/app/(app)/productions/[id]/expenses.tsx"

# 機能単位で別ブランチへ持ち込む例（移植作業用ブランチ上で）
git checkout archive/dev-only-ui-20261125 -- mobile-rn/src/features/expense
git restore --staged .   # checkout -- はステージもするため

# 開発ブランチとの差分
git diff origin/feature/user-account-identity archive/dev-only-ui-20261125 -- mobile-rn/src/features/publicPage
```

ヒーロー画像：`tar xzf C:\StageArt-Backups\dev-code-20261125\dev-api-plugin-stageart.tar.gz dev-api.stageart.top/wp-content/plugins/stageart/assets/hero` の後、`hero-assets-sha256.txt` で照合する。

---

## 4. 注意

- このブランチは `df24b62` に退避記録を足しただけで、コードは変更していない。
- `feature/user-account-identity` へマージしない。移植は機能単位で、現行仕様に合わせて行う。
- Dev DBのデータ（公開ページ設定4件・レイアウト1件・コンテンツ4件、管理領域3件）はDev DBに残る。開発ブランチのコードをDevへ配置しても、これらのテーブルは削除されない（参照されなくなるだけ）。
