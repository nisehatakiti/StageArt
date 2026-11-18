# PR #7 R14-B 運用手順書：email_key の鍵・レート制限・保存期間の削除

作成日: 2026-11-18（R14-B 第8段階）
対象ブランチ: `feature/r14b-participant-consent`
正本: `docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md`（`9b42eb5`）の §6.6・§6.7・§9.2
対象コード:
- `plugin/src/Infrastructure/WordPress/ParticipantInvitation/WordPressConfigEmailKeyring.php`（鍵の束の読み込み）
- `plugin/src/Application/ParticipantInvitation/InvitationSendService.php`（`email_key` の計算と30日間の集計）
- `plugin/src/Application/RateLimit/*`、`plugin/src/Infrastructure/WordPress/RateLimit/WordPressRateLimiter.php`（レート制限）
- `plugin/src/Infrastructure/WordPress/Retention/*`（保存期間の削除と WP-Cron の登録）
- `plugin/stageart.php`（無効化時の WP-Cron の解除）

本書は手順書であり、Dev・本番での実施を承認するものではない。`wp-config.php` の変更、コードの配置、DBへの接続・確認のSQLの実行は、環境ごとにユーザーの承認を得てから行う。テーブル接頭辞は `wp_` と仮定しているため、実環境の接頭辞に置き換える。**第8段階では、Dev・本番のどちらにも設定の変更を行っていない。**

---

## 1. 全体の流れ（リリースの前に必要なこと）

| # | 作業 | 必須 | 設定しない場合 |
|---|---|---|---|
| 1 | `wp-config.php` に `STAGEART_EMAIL_KEYS`・`STAGEART_EMAIL_KEY_CURRENT` を設定する（§2） | **必須** | 招待の作成・再送が、すべて500 `stageart_email_key_not_configured` になる（安全側。鍵なしのハッシュには切り替えない） |
| 2 | コードを配置し、スキーマ 1.49.0 への更新を確認する（`wp_stageart_rate_limit_buckets` などの表） | 必須 | レート制限・送信の記録が保存できず、招待の作成などが500になる |
| 3 | WP-Cron が動いていることを確認する（§4.3） | 必須 | 保存期間を過ぎた行が削除されない（正しさには影響しないが、表が増え続ける） |
| 4 | 動作の確認（§5） | 必須 | — |

レート制限には、設定項目はない（上限・時間枠はコードの値。§3）。

---

## 2. email_key の鍵（§6.7）

### 2.1 何に使うか

- 招待メールの送信の記録（`wp_stageart_participant_invitation_sends`）に、宛先のメールアドレスそのものではなく、`email_key = HMAC-SHA-256(鍵, 正規化したアドレス)` を保存する。正規化は前後の空白の除去と小文字化。
- 「同じ公演・同じアドレスへの送信は30日間に6回まで」の集計に使う。
- **鍵はDBに保存しない。`wp-config.php` の定数だけで与える。** `STAGEART_JWT_SECRET` とは別の値にする。
- 鍵の値は、ログ・エラーメッセージ・監査ログに出力されない。版の名前（例：`v1`）は出力されうる。

### 2.2 初回の設定

1. 鍵の値を作る（32バイト以上のランダムな値。下の例は64桁の16進＝32バイト分の乱数を文字列にしたもの）。

   ```
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

   - 生成した値は、チャット・チケット・Git・メールに貼らない。サーバー上で直接 `wp-config.php` に書く。
   - Dev と本番では、**別の値**にする。
2. `wp-config.php` の「That's all, stop editing!」の行より前に追記する。

   ```php
   define( 'STAGEART_EMAIL_KEYS', [
       'v1' => '<生成した値>',
   ] );
   define( 'STAGEART_EMAIL_KEY_CURRENT', 'v1' );
   ```

3. 書式の条件（満たさないものは「未設定」として扱われる）
   - `STAGEART_EMAIL_KEYS` は配列。文字列などの配列でない値は、全体が未設定になる。
   - 版の名前は、英数字と `.` `_` `-` の1〜20文字（`email_key_version` 列が VARCHAR(20) のため）。
   - 鍵の値は32バイト以上の文字列。31バイト以下・文字列でない値の版は、ないものとして扱われる。
   - `STAGEART_EMAIL_KEY_CURRENT` の版が、`STAGEART_EMAIL_KEYS` に有効な値で含まれていること。
4. `wp-config.php` のファイルの権限・所有者は、既存の運用（`STAGEART_JWT_SECRET` と同じ扱い）に合わせる。

### 2.3 設定の確認

- 招待を1件作成し、201になることを確認する（Dev の試験用の公演・試験用のアドレスで行う）。500 `stageart_email_key_not_configured` なら、上の書式の条件を満たしていない。
- 値そのものを画面・ログに表示して確かめることはしない。

### 2.4 通常のローテーション

1. 新しい版の鍵を `STAGEART_EMAIL_KEYS` に**追加**する（古い版は消さない）。

   ```php
   define( 'STAGEART_EMAIL_KEYS', [
       'v1' => '<古い値>',
       'v2' => '<新しい値>',
   ] );
   define( 'STAGEART_EMAIL_KEY_CURRENT', 'v2' );
   ```

2. `STAGEART_EMAIL_KEY_CURRENT` を新しい版に切り替える。以後の送信の記録は新しい版で作られる。
3. 旧い版は、次の「外せる条件」を満たすまで残す。30日以内に何度ローテーションしても、期間内に使われた版をすべて残す。
4. 条件を満たした版から外す。

**外せる条件**：その版で作った送信の記録が、すべて30日間の集計から外れた後。次の読み取りだけのSQLで確認する（本番での実行は、ユーザーの承認を得てから）。

```sql
SELECT MAX(requested_at) FROM wp_stageart_participant_invitation_sends
 WHERE email_key_version = 'v1';
-- 結果が「現在（UTC）- 30日」より前、または NULL（行がない）なら外せる。
-- 時刻の誤差に備え、さらに1日ほど待ってから外す【推奨】。
```

- 期間内に使われた版を外すと、その公演の招待の作成・再送は500 `stageart_email_key_not_configured` になる（少なく数えて送信を許すことはしない）。誤って外した場合は、同じ版の名前と同じ値で戻せば直る。
- 外した版の送信の記録は、§4 の保存期間（原則90日）で削除される。

### 2.5 鍵が漏えいした場合（緊急）

1. 直ちに新しい版を追加し、`STAGEART_EMAIL_KEY_CURRENT` を切り替える（2.4 の1・2）。
2. 漏えいした鍵は、**集計のためだけに**、外せる条件を満たすまで残す。
3. 直ちに外す必要がある場合は、その版の行を使う公演の招待の作成・再送が、最大30日間止まる。これを避けて「外した版の行を数えない」運用に一時的に切り替えるかどうかは、緊急時にユーザーが判断する（コードの変更が必要。本書の範囲外）。
4. 漏えいした鍵で計算された行は、保存期間で削除されるまで残る。

---

## 3. レート制限（§9.2）

### 3.1 制限の内容（設定項目なし）

| 対象 | 単位 | 上限 | 時間枠 |
|---|---|---|---|
| 招待の作成・再送（合算） | 操作する人物ごと | 30件 | 1時間（UTC の毎時0分から） |
| 招待の作成・再送（合算） | 公演ごと | 100件 | 1日（UTC の0時から。日本時間の9時） |
| `accept`・`reject` の失敗（403・404） | 人物ごと | 10回 | 10分（UTC の10分区切り） |

- 超えた場合：429 `stageart_rate_limited`。`data.retry_after`（時間枠の終了までの秒数）と、`Retry-After` ヘッダーを返す。処理は何も行われない。
- 固定の時間枠のため、枠の境目の前後で、最大で上限の2倍まで通る（設計どおり）。
- 招待の作成・再送は、**権限の確認の後**、入力の検証と業務の処理の前に数える。権限のない人の試みは数えないため、第三者が公演の枠を使い切ることはできない。入力の誤り（422）の試みは数える。
- 人物の枠を先に数え、超えた場合は公演の枠を数えない（1人の試みで、他の管理者の招待を止めないため）。
- `accept`・`reject` は、最初に現在の失敗の回数を読み、10回以上なら429（正当な招待でも、その10分間は429）。403・404で失敗したときだけ数える。成功・409・410は数えない。
- 招待の送信の上限（招待1件あたり5回、同じ公演・アドレスで30日間に6回）と辞退後30日の制限は、レート制限とは別に、常に適用される。

### 3.2 仕組みと運用上の注意

- 回数は `wp_stageart_rate_limit_buckets` に保存する（複数のPHPプロセスで正しく数えるため）。`bucket_key` は種類と対象（人物ID・公演ID）の HMAC で、IDそのものは保存しない。
- HMAC の鍵は、WordPress の `wp_salt('auth')` から作る（email_key の鍵とは別）。**WordPress のソルト（`AUTH_KEY`・`AUTH_SALT`）を変更すると、その時点の時間枠の回数が数え直しになる**（最大で上限の2倍まで通る。安全上の問題はない）。
- MySQL のデッドロック（1213）は3回まで再試行し、3回とも失敗した場合は429（`retry_after` は2秒）。それ以外のDBの失敗は500 `stageart_persistence_failed`。
- 接続元IPは使わない（`X-Forwarded-For` も使わない）。

### 3.3 回数の確認（必要な場合だけ。読み取りのみ、承認を得てから）

```sql
-- 現在の時間枠の行の数と最大の回数（bucket_key は HMAC のため、誰の行かは分からない）
SELECT COUNT(*), MAX(hits) FROM wp_stageart_rate_limit_buckets WHERE expires_at > UTC_TIMESTAMP();
```

特定の人物の制限を手動で解除する操作は、用意していない（時間枠の終了を待つ）。

---

## 4. 保存期間と削除の定期処理（§6.6）

### 4.1 削除の対象（この3つの表だけ）

| 表 | 削除の条件 | 1回の上限 |
|---|---|---|
| `wp_stageart_participant_events`（監査ログ） | `created_at` < 現在（UTC）- 2年 | 1,000行 |
| `wp_stageart_participant_invitation_sends`（送信の記録） | `requested_at` < 現在（UTC）- 90日、かつ、どの招待の `current_send_id` からも指されていない（各招待の最新の試行は残す） | 1,000行 |
| `wp_stageart_rate_limit_buckets`（レート制限） | `expires_at` < 現在（UTC） | 1,000行 |

**削除しないもの**：参加者の行（同意の情報を含む）、招待の行、出欠・精算・会計の記録、その他すべての表。

- 3つの表は、いずれもスキーマ 1.49.0（R14-B）で新設した表で、R14-B より前のデータは含まれない。
- 1,000行を超えた分は、翌日以降の実行で削除される。
- 送信の記録は、対象のIDを求めてから、そのIDだけを（期間の条件も重ねて）削除する2段階の処理（MySQL は複数の表を結合した DELETE に LIMIT を付けられないため）。

### 4.2 実行の方式

- WP-Cron のイベント `stageart_participant_data_retention`（1日1回、`daily`）。
- プラグインが有効な間、最初のリクエストの `init` で、未登録なら1時間後を初回として登録する。登録済みなら何もしない。
- プラグインを無効化すると、イベントは解除される。再び有効化すると、次のリクエストで登録される。
- 結果は PHP のエラーログに1行で出る（件数だけ。行の内容は出さない）。

  ```
  [StageArt retention] deleted participant_events=0 participant_invitation_sends=12 rate_limit_buckets=340
  ```

- 1つの表の削除に失敗しても、他の表の削除は続ける。失敗はエラー番号だけを記録し、残りは次回に回す。

  ```
  [StageArt retention] participant_events failed (mysql_errno=1205); the rest waits for the next run
  ```

- 同時に2回実行されても（WP-Cron の重複）、期間を過ぎた行を消すだけなので、余計な行は消えない。

### 4.3 WP-Cron の確認

WP-Cron は、サイトへのアクセスをきっかけに動く。`wp-config.php` で `DISABLE_WP_CRON` が `true` の環境では、サーバーの cron などで WP-Cron を実行する設定が別に必要（既存の運用を確認する）。

```
# 登録されているかの確認（読み取りのみ）
wp cron event list --fields=hook,next_run_gmt,recurrence --path=<wordpress-root> | grep stageart_participant_data_retention

# 手動で1回実行する（削除を伴う。Dev で確認する場合も承認を得てから）
wp cron event run stageart_participant_data_retention --path=<wordpress-root>
```

---

## 5. Dev・本番での確認の手順（承認を得てから行う）

1. バックアップの取得（既存の手順どおり）。
2. `wp-config.php` に鍵を設定する（§2.2）。Dev と本番で別の値。
3. コードを配置し、スキーマが 1.49.0 になったことを確認する（読み取りのみ）。

   ```sql
   SELECT option_value FROM wp_options WHERE option_name = 'stageart_db_schema_version';
   SHOW TABLES LIKE 'wp_stageart_rate_limit_buckets';
   ```

4. Dev で、試験用のアカウント・公演で次を確認する。
   - 招待の作成が201になる（鍵の設定の確認）。
   - 送信の記録の `email_key_version` が現在の版になっている（読み取りのSQL）。
   - WP-Cron に `stageart_participant_data_retention` が登録されている（§4.3）。
5. 本番は、Dev の確認の後、ユーザーの承認を得て同じ手順で行う。本番のデータを試験のために作成・削除しない。

### 戻し方

- 鍵の設定を誤った場合：`wp-config.php` を直す（DBの変更は不要）。
- 定期処理を止めたい場合：プラグインの無効化で解除される。または `wp cron event delete stageart_participant_data_retention`（有効な間は次のリクエストで再登録されるため、恒久的に止めるにはコードの変更が必要）。
- 削除された監査ログ・送信の記録は、バックアップからしか戻せない。
