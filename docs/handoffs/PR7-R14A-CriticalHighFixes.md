# PR #7 R14-A：Critical・High指摘の修正（2026-11-14）

R13全体レビューのCritical・High指摘を修正した記録。開始時のHead `3b5cb8e`。各修正は独立したコミット。

## 判断事項（ユーザー判断、2026-11-14）

- **F1**：新規予約・当日券・人数の追加はPUBLISHEDの公演回だけ受け付ける（SOLD_OUT・CANCELLED・FINISHEDは拒否）。受付（Check-in）はCANCELLEDだけ拒否する。人数の減少・セルフサービスの取消・受付取消は全状態で可。
- **N4**：今回は修正しない（調査と設計案のみ、下記）。
- **N2**：MigrationでDRAFTのProductionをPLANNINGへ更新する（スキーマ1.48.0）。

## 修正内容

| 指摘 | 内容 | 主な変更 |
|---|---|---|
| F6（Critical） | 招待トークンなしのメール登録では招待を解決しない。メール確認の完了時に解決する。未確認のメール認証情報は「既存利用者」として扱わない | `RegisterWithEmailUseCase`、`VerifyEmailUseCase`、`FindPersonByEmailUseCase`、`Plugin.php` |
| N1（Critical） | `PUT /productions/{id}` は送られた項目だけを変更する。nullを送った項目は削除。定員・上演時間を送ったときだけ公演回へ反映 | `UpdateProductionCommand::$providedFields`、`UpdateProductionUseCase`、`ProductionRestController` |
| F1（High） | 公演回の状態による受付（上記）。Performance行のロック後に判定。拒否は409 `stageart_performance_not_accepting` | `Performance::acceptsNewReservations()/acceptsCheckIn()`、`PerformanceCapacityGuard`、`CheckInProcessor::lockForCheckIn()`、`RestFailureResponder`、画面の文言 |
| F9（High） | 外部リンク・SNS（OTHER）・フライヤー・ロゴのURLは http/https のみ（大文字小文字、前後空白、制御文字・空白の混入も確認）。画面も保存前に確認し、公開ページは条件を満たさないURLを開かず表示もしない | `Application\Shared\WebUrl`、各UseCase、`mobile-rn/src/utils/webUrl.ts`、公開ページ |
| F13（High） | 公演回の開演日時をAsia/Tokyoで組み立てる（PHPの既定タイムゾーンに依存しない）。保存値は変更なし | `Performance::startDateTime()` |
| N2（High） | スキーマ1.48.0：`UPDATE … SET status='PLANNING' WHERE status='DRAFT'`（該当行のstatusのみ、冪等、失敗時はバージョンを上げずに再実行） | `Installer`、`SchemaUpgrader` |
| N3（High） | URLのないSNS行を読み取れるようにした（`OrganizationSNS::reconstitute()` の型） | `OrganizationSNS` |

## 既存データの確認用SQL（読み取りのみ。Dev・Productionでは未実行、実行には別途承認が必要）

```sql
-- F9: http(s) 以外のURL
SELECT id, organization_id, url FROM wp_stageart_organization_external_links WHERE url NOT REGEXP '^https?://';
SELECT id, organization_id, profile_url FROM wp_stageart_organization_sns WHERE profile_url IS NOT NULL AND profile_url NOT REGEXP '^https?://';
SELECT id, flyer_url FROM wp_stageart_productions WHERE flyer_url IS NOT NULL AND flyer_url NOT REGEXP '^https?://';
SELECT id, logo_url FROM wp_stageart_organizations WHERE logo_url IS NOT NULL AND logo_url NOT REGEXP '^https?://';
-- N2: アップグレードで PLANNING に移る行
SELECT id, name, created_at FROM wp_stageart_productions WHERE status = 'DRAFT';
-- N3: URLのないSNS行（R14-A以降は読み取れる）
SELECT id, organization_id, service FROM wp_stageart_organization_sns WHERE profile_url IS NULL OR profile_url = '';
```

テーブル名の接頭辞 `wp_` は環境に合わせる。F9の既存の不正URLは、Backendが更新を拒否するため、見つかった場合は画面から削除して登録し直す（またはユーザー承認のうえで個別に修正する）。公開ページは該当URLを表示しない。

## N4（同意のない参加者登録）の調査と設計案（未実装）

- 経路：`POST /productions/{id}/participants`（subject_type=PERSON、任意の人物ID）と、招待作成時にそのメールが既存利用者（R14-A以降は確認済みのメールのみ）なら招待せず直接追加する分岐（`CreateParticipantInvitationUseCase`、団体側は `CreateOrganizationInvitationUseCase`）。応答の `ParticipantResult` に氏名・メールが入る。公演・団体は誰でも作成できるため、実質ログイン済みの誰でも可能。
- 設計案A（推奨）：人物IDによる直接追加を廃止し、既存利用者にも招待を送る。本人がログイン中に承諾するAPI（例：`POST /participant-invitations/{id}/accept`、招待メールのトークンまたは本人のメール一致で確認）と画面を新設する。人物検索（F5）は存在有無・氏名を返さない形にする。
- 設計案B：直接追加を同じ団体のACTIVEメンバーに限定し、応答からメールを除く。
- 影響：参加者追加の画面フロー、招待メールの文面、既存テストの多数。仕様変更を伴うため実装前にユーザー判断が必要。

## 検証

- PHPUnit全件、Jest全件、TypeScript、lint、Web export、php -l、git diff --check。
- 実MySQL 5.7（隔離DB `stageart_r14a`＝`stageart_r12b` のコピー＋旧形式の行、`stageart_r14afresh`＝新規インストール）。REST 88項目（`C:\StageArt-TestEnvironment\scripts\r14a.sh`）。修正前のコードでは、DRAFT行と URL のないSNS行で一覧・公開団体ページが500になることを再現。
- 未検証：MySQL 8.0／MariaDB、実機・ブラウザでの操作、実メール配信。
