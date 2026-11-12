# Claude Code 引き継ぎ指示：Public Landing / Login Host Routing

更新日: 2026-10-16  
Repository: `nisehatakiti/StageArt`  
Branch: `feature/user-account-identity`  
基準HEAD: `c3af1828e456c39ef99987f7686e4162d110de51`

## 1. 目的と確定方針

公開サイトのルートURLはログイン画面ではなく、StageArtの機能紹介ページとして維持する。ログイン操作時だけ、対応する環境のApplicationへ移動させる。

| 用途 | URL | 期待動作 |
|---|---|---|
| 本番Public Site | `https://stageart.top/` | 機能紹介ページ |
| 本番Application Login | `https://app.stageart.top/login/` | 本番ログイン画面 |
| 開発Public Site | `https://dummy.stageart.top/` | 機能紹介ページ |
| 開発Application Login | `https://dev.stageart.top/login/` | 開発ログイン画面 |

- `stageart.top` のログイン導線は `app.stageart.top/login/` へ。
- `dummy.stageart.top` のログイン導線は `dev.stageart.top/login/` へ。
- 団体・公演のPublic Page URL（Slug付き）は変更しない。
- API、DBスキーマ、認証仕様、他のサブドメインの責務は変更しない。
- URL設計の正本は `docs/03-PublicPageURLAndPublicationSchedule.md`。
- 対象実装は `mobile-rn/src/app/index.tsx`、`mobile-rn/src/api/site-routing.ts`、`mobile-rn/src/components/public-landing-page.tsx` および関連テスト。

## 2. 作業開始時の必須確認

1. 最初に現在の作業ディレクトリ、Git branch、HEAD、`git status --short`、GitHub branch HEADを確認する。
2. 基準HEADと一致していない場合、作業を進める前に差分・追加コミットを調査して報告する。既存変更を破棄・上書きしない。
3. `feature/user-account-identity` 以外のブランチで作業しない。force-pushは禁止。
4. 既存実装とテストを読み、実装済みの変更を重複して作らない。
5. この依頼の範囲外のUI整理・リファクタリングを行わない。

## 3. テスト

`mobile-rn` ディレクトリで、リポジトリに定義された正規のコマンドを確認してから実行する。

1. ルーティング単体テスト（`src/api/site-routing.test.ts`）。
2. 公開ランディングページテスト（`src/app-tests/public-landing-page.test.tsx`）。
3. 既存の認証・ルート遷移テスト（少なくとも `src/app-tests/navigation.test.tsx` と `src/app-tests/login-screen-render.test.tsx`）。
4. TypeScript型チェック、Lint、Web export/build。
5. 既存テストの失敗は今回の失敗と混同しない。失敗時はベースラインと比較し、新規失敗があればデプロイを停止する。
6. Build成果物が実際に生成され、対象ホストに配置できる構成か確認する。

テストで最低限確認する期待値：
- `stageart.top`、`www.stageart.top`、`dummy.stageart.top` のrootはPublic Landing。
- `app.stageart.top`、`dev.stageart.top` はApplication entry。
- Production Public Landingのログイン先は `https://app.stageart.top/login/`。
- Development Public Landingのログイン先は `https://dev.stageart.top/login/`。
- localhost等のローカル開発で既存のApplication entryが壊れない。
- Public Landingへの変更で既存の認証・アプリケーション内ルートが壊れない。

## 4. デプロイ前の安全条件

1. テスト、型チェック、Lint、Web buildの結果を記録する。
2. デプロイ先ごとに現在の配置物・リリース識別子を確認し、既存ファイルのバックアップまたは復旧可能なリリースを確保する。
3. 実際のホスティング構成を確認し、同じビルド成果物を無条件に全ホストへ上書きしない。各ホストが同じWeb exportを共有する設計か、ホスト別の配信設定が必要かを確認する。
4. 既存の公開団体ページ・公演ページのルーティング、SPA fallback、サブパス設定を維持する。
5. DNS、TLS証明書、Webサーバー設定を変更する必要がある場合、現状と具体的変更内容を先に報告して停止する。依頼の範囲外のインフラ変更を推測で行わない。
6. API、DB、認証設定、WAF設定は変更しない。

## 5. デプロイ順序

### Phase A：Development

1. テストとWeb buildが成功した場合のみ、開発環境へデプロイする。
2. `https://dummy.stageart.top/` を実ブラウザで開き、機能紹介ページが表示されることを確認する。
3. ページ内のヘッダー・メイン・フッターのログイン導線をクリックし、`https://dev.stageart.top/login/` へ遷移することを確認する。
4. `https://dev.stageart.top/` が従来どおりApplication entryとして動作し、未認証時にログインへ誘導することを確認する。
5. 既存の団体Public Pageと公演Public Pageの代表URLを確認し、ルートページ変更による回帰がないことを確認する。
6. DevToolsのConsole/NetworkでJSエラー、404、CORS、資産読み込み失敗を確認する。

### Phase B：Production

Developmentの検証がすべて成功した場合のみ進む。

1. 本番デプロイ前に、本番側のバックアップ・ロールバック手順・配置先を再確認する。
2. 本番Webフロントエンドだけをデプロイする。API・DB・他サブドメインは変更しない。
3. `https://stageart.top/` が機能紹介ページとして表示されることを確認する。
4. ヘッダー・メイン・フッターのログイン導線が `https://app.stageart.top/login/` へ遷移することを確認する。
5. `https://app.stageart.top/` が従来どおりApplication entryとして動作することを確認する。
6. 既存の団体Public Page・公演Public Pageを確認し、Console/Networkに新規エラーがないことを確認する。

## 6. 停止条件

以下のいずれかに該当したら、デプロイまたは次の環境への展開を停止し、原因と証拠を報告する。

- 基準HEADからの予期しない差分、未コミット変更、未pushコミットがある。
- 新規テスト失敗、型エラー、Lintエラー、Web build失敗がある。
- デプロイ先の特定、バックアップ、ロールバック手順を確認できない。
- 既存の団体・公演Public PageのURLルーティングを維持できない。
- DNS、TLS、Webサーバー等のインフラ変更が必要だが、変更範囲・影響が確定できない。
- API、DB、WAF、認証設定への変更が必要になる。
- 実ブラウザでログイン先や公開ページが期待どおり動作しない。

## 7. 最終報告フォーマット

次を省略せず報告する。

1. 作業開始時と終了時のbranch / local HEAD / GitHub HEAD。
2. `git status --short`（開始時・終了時）。
3. 実行したテスト・型チェック・Lint・Web buildの正確なコマンドと結果。
4. 各環境のデプロイ先、デプロイ日時、デプロイしたSHA、バックアップ先、ロールバック方法。
5. 実ブラウザで確認した4つのURLと、各ログイン導線の実際の遷移先。
6. 団体Public Page・公演Public Pageの回帰確認結果。
7. Console / Networkでのエラー有無。
8. 未実施項目・既存失敗・残課題。未確認を「成功」と表現しない。

この作業では、ユーザーの承認なく対象範囲を広げない。テスト結果が不明、環境が特定できない、または停止条件に該当する場合は、変更を追加せず状況を報告する。
