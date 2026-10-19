# Web 用途別ビルド手順（公開サイト / アプリ）

StageArt の Web は、同じソースから **公開サイト用** と **アプリ用** の2種類をビルドする。

| 配置先 | 用途 | `--site` | `--api` |
|---|---|---|---|
| `dummy.stageart.top` | 公開サイト（開発確認用） | `public` | `development` |
| `stageart.top` / `www.stageart.top` | 公開サイト | `public` | `production` |
| `dev.stageart.top` | アプリ（開発） | `app` | `development` |
| `app.stageart.top` | アプリ（本番） | `app` | `production` |

公開サイト用ビルド（`EXPO_PUBLIC_STAGEART_SITE=public`）では、認証画面（`/login`、`/register`、`/forgot-password`、`/reset-password`、`/set-name`、`/registration-pending`）が UI を一切描画しない。書き出された HTML にもフォームが含まれないため、JavaScript 無効時も認証フォームは表示されない（JavaScript 有効時は公開トップ `/` へ戻る。無効時は空白ページになる）。仕組みは `mobile-rn/src/components/app-only-route.tsx` を参照。

`/verify-email` と `/verify-notification-email` は、メール内リンクの送信先（`STAGEART_EMAIL_VERIFICATION_BASE_URL`）が未確認のため対象外としている。

## メール内リンクの送信先

メールからリンクされる画面のホストは、WordPress の `wp-config.php` の定数で決まる（リポジトリ外。`plugin/src/Presentation/Plugin.php` が読み込む）。

| 定数 | メール内リンク | 未定義時の既定値 | 公開サイト用ビルドでの扱い |
|---|---|---|---|
| `STAGEART_EMAIL_VERIFICATION_BASE_URL` | `{値}/verify-email?token=…`、`{値}/verify-notification-email?token=…` | `https://dev-stageart.hatakiti.com/verify-app` | 変更していない（対象外） |
| `STAGEART_PARTICIPANT_INVITATION_BASE_URL` | `{値}/register?token=…`（参加者招待・団体メンバー招待の両方） | `https://dev.stageart.top` | `/register` を表示しない。値が公開ホストを指すと、招待リンクは公開トップへ戻ってしまう |

パスワードリセットのメールはトークンだけを送り、リンクを含まない（`WordPressAuthMailer::sendPasswordResetEmail`）。

**各サーバーでの実際の設定値は未確認**（リポジトリからは分からない）。値を推測で決めず、公開サイト用ビルドを配置する前に、次の手順で確認する。

1. 開発・本番それぞれの WordPress サーバーで、`wp-config.php` に上の2つの定数が定義されているかを**読み取りのみ**で確認する（WP-CLI が使える場合は `wp config get <定数名>`。未定義ならエラーになり、上の既定値が使われる）。値を変更しない。
2. どちらの値も公開ホスト（`stageart.top`、`www.stageart.top`、`dummy.stageart.top`）を指していないことを確認する。
3. `STAGEART_EMAIL_VERIFICATION_BASE_URL` のホストに、`/verify-email` と `/verify-notification-email` を表示するビルドが配置されていることを確認する。
4. 確認した値と確認日を記録する。公開ホストを指していた場合は配置を止め、扱いを決めてから進める。

## ビルド

`mobile-rn` で実行する。`--out` には空のディレクトリ（または以前の書き出し先）を指定する。

```bash
npm run export:web:site -- --site public --api development --out <出力先>
```

このスクリプトは、書き出し・`.htaccess` 生成・検査を続けて行う。次の場合は実行を拒否する。

- `--site` / `--api` が不正
- `--out` が `mobile-rn/dist`、プロジェクト自身、またはその親ディレクトリ
- `--out` が空でなく、以前の書き出し先でもないディレクトリ（`expo export` が中身を消すため）

従来の `npm run export:web`（`mobile-rn/dist` へのアプリ用ビルド）は変更していない。

## 検査

書き出し済みのディレクトリは、配置前に必ず検査する。

```bash
npm run verify:web -- <出力先> --site public --api development
```

- `public`：許可リスト（予約確認・予約・アンケート・メール確認ページ）以外の HTML にフォーム入力欄が無いこと、`index.html` が公開トップであること
- `app`：`login.html` にログインフォームがあること、`index.html` が公開トップではないこと
- 共通：認証ページ・公開スラッグページ・`.htaccess` のスラッグ書き換えルールが存在すること、JS バンドルが指定した API だけを参照すること

## 誤用防止

- `app.config.ts` は `EXPO_PUBLIC_STAGEART_SITE` に `public` / `app` / 未設定以外の値があるとエラーで停止する。
- `EXPO_PUBLIC_STAGEART_SITE=public` は EAS（ネイティブ）ビルドでは拒否される（`EAS_BUILD_PLATFORM` で判定）。さらに実行時もネイティブでは公開サイト扱いにならない。
- `.env` には `EXPO_PUBLIC_STAGEART_SITE` を書かない（`npm run export:web` などの通常ビルドまで公開用になるため）。
- 配置時は `.deploy-manifest.txt` に `site=public|app` と `api=development|production` を記録し、上の表と一致することを確認する。
- アプリ用ビルドを公開ホストに誤配置した場合も、実行時のホスト判定で認証画面は表示されない（ただし JavaScript 無効時の HTML にはフォームが残る）。
