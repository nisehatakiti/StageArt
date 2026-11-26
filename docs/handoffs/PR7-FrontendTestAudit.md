# PR #7 R7-2：Frontend（mobile-rn）のテスト・型チェック・lintの整理（2026-11-10）

R6時点（Head `b27a422`）で、Jest 248スイート中38〜40スイート・46〜48件が失敗、`tsc --noEmit` 3件、`expo lint` エラー12件・警告13件だった。R7の対応と、残した失敗を記録する。テストの削除、型チェック回避の `any`、lintルールの無効化は行っていない。

## 1. 失敗の原因の分類

失敗の大半は、2026-10-05〜07の画面変更（ユーザーのUIコミット）にテストが追随していなかったもの。画面と仕様書を照合し、次の3つに分けた。

| 分類 | 対応 |
| --- | --- |
| A. 画面の変更が仕様と整合している（テストが古い） | テストを現在の画面に合わせて更新。元のテストが確認していた内容（例：エラー時の表示、空の状態、送信内容）は、機能の移動先で同じく確認する |
| B. 画面側の不具合 | 画面を修正 |
| C. 画面の変更が仕様書と矛盾している | **変更していない**（失敗のまま残す。ユーザー判断待ち） |

## 2. A：テストを更新したもの

| 画面の変更（コミット） | テスト | 更新内容 |
| --- | --- | --- |
| Homeから「団体を作る」ショートカットを削除（2513c52） | login-flow、google-login-flow、index-boot-authenticated、home-favorites-conditional、home-empty-organizations、web-dashboard-greeting | 「Homeに到達した」目印をHomeのルート `home-screen` に変更。ショートカットは「無いこと」を確認 |
| Homeの団体一覧・公演一覧を「所属団体」（/organizations）へ移動（3113937） | home-organizations-401/403、home-single-org、home-multi-org-switch、web-dashboard-organizations、web-dashboard-organizations-empty、home-empty-productions | 同じ内容（エラー文言、空の状態、名前・役割・公演数、行からの遷移）を移動先の画面で確認。仕様（04-HomeRoleBasedMenu.md §04「所属Organization：名称と入口」）は、Homeのメニュー「所属団体」から満たされると判断した |
| 同上 | home-dashboard-error-productions-ok、home-dashboard-ok-productions-error | 「一方の失敗が他方を壊さない」確認を、現在のHomeの構成（ダッシュボード、挨拶、公演名の取得）で行う |
| 同上 | home-to-production | Homeから公演へ入る経路を、次の稽古のカード（公演のシェル内の稽古詳細）に変更。戻る操作でHomeに戻ることを確認 |
| 空のコメント一覧は何も表示しない（51b144c） | schedule-comment-list、attendance-detail-comment-delete、schedule-item-comment-delete、attendance-detail-comments-post | 空の表示が無いこと、削除したコメントが消えることを確認 |
| コメントの投稿者を名前で表示（0f4b3e0） | schedule-comment-list、schedule-to-detail | Person IDではなく名前（不明なら「名前未設定」）で表示することを確認 |
| 稽古名を「備考」に置換（64d53af、ec81f29） | attendance-rehearsal-create、-create-cast-staff-submit、-create-deadline、-edit-initial-values | 入力先を備考に変更し、送信内容が `title: null`・`description` であることを確認 |
| 稽古の編集は管理者のみ（f575bc5） | attendance-rehearsal-edit-*（4件） | 管理者の公演情報（`GET /productions/{id}`）をモックに追加 |
| 稽古一覧の行に稽古名を表示しない（f4851c9 等） | attendance-rehearsal-list | 稽古名が表示されないこと、種別が表示されることを確認 |
| 過去公演の登録は通常の作成画面へ転送（3bfbce8） | web-organization-productions-create-past | 通常の作成フォームが表示されることを確認 |

テストの書き方のみの変更：
- attendance-rehearsal-edit-submit：`renderRouter()` で多段のStack/Tabの奥の入力が画面の状態に反映されない既知の制約（schedule-comment-post.test.tsx に記載）により、入力した場所が送信されなかった。作成画面のテストと同じ単体描画に変更（確認内容は同じ）。
- attendance-rehearsal-create / -cast-staff-submit：各ワーカーで最初に画面を描画するテストだけ、全件の並列実行時にメンバー一覧の表示が既定の1秒を超えることがあった（単独では成功）。待機の上限だけを5秒にした。
- web-production-settlement-overpaid：1ファイルに `renderRouter()` を2回呼ぶと状態が残る既知の制約のため、2つ目のケースを別ファイル（-not-overpaid）に分けた。

## 3. B：画面を修正したもの

| 画面 | 不具合 | 修正 |
| --- | --- | --- |
| タイムテーブル項目の詳細（schedule/[itemId].tsx） | 投稿者名の対応表を渡しておらず、すべてのコメントが「名前未設定」になっていた（0f4b3e0は稽古詳細だけに対応） | 稽古詳細と同じ規則の `useCommentAuthorNames` を追加して渡す |
| Home | すべての欄が条件付きのため、データが無いと何も描画されず、到達を確認できる要素がなかった | ルートに `testID="home-screen"` を付与 |

## 4. C：仕様と矛盾するため残したもの（失敗7件、R7時点。R8で解消 → §7）

| テスト | 画面から削除された機能（コミット） | 仕様書 |
| --- | --- | --- |
| attendance-detail-add-members-add、-add-members-display | 稽古詳細からの参加メンバー追加（f575bc5） | 20-RehearsalManagementScreen.md §6.2 Add After Creation「稽古作成後も、稽古詳細画面からProductionメンバーを追加できる」 |
| attendance-detail-activate、-complete | 稽古の開始・終了操作（2c0c20b。フックは残っているが、どの画面からも呼ばれない） | 同書のステータス定義（稽古終了後の状態）と §7.2 |
| attendance-detail-respond、-full | 稽古詳細の「あなたの回答」表示（fdee2c8）。回答ボタンにも選択中の状態がない（本人の状態は名簿の行には表示される） | 同 §7.2「既存の本人回答・管理者による実績更新構造を基本として維持する」。管理者の出席実績（出席・遅刻・早退・欠席）の記録もfdee2c8で削除されており、§7.2と矛盾 |
| schedule-timetable-create-link | 「＋ タイムテーブルを作成」（22e0285）。稽古詳細のタイムテーブル項目追加・公開も削除済み（2c0c20b）で、アプリからタイムテーブルを作成・公開できない | 04-CommonNavigationDesign.md §20.3 |

対応の選択肢：(1) 仕様どおり画面に機能を戻す、(2) 削除が意図どおりなら、仕様書を改訂したうえでテストを更新する。

## 5. tsc・lint

| 項目 | 原因 | 対応 |
| --- | --- | --- |
| tsc：`activityType`（2件） | 画面とAPIクライアントは `activity_type` を送るが、フック（useRehearsals.ts）の入力型に無かった（Backendの稽古API・`api.ts` は対応済み） | フックの入力型に追加 |
| tsc：`@/global.css`（1件） | CSSの型宣言は、`expo start` が生成する `expo-env.d.ts`（.gitignore対象）にある。新しいチェックアウト・CIでは存在しない | `src/types/expo-types.d.ts` で同じ `expo/types` を参照（独自の型宣言は追加していない） |
| lint：`react-hooks/set-state-in-effect`（9件） | 取得したデータでフォームを初期化する処理をeffect内で行っていた | 作成画面等と同じく、同じ条件で描画中に初期化する形に変更。条件のない2件（メンバー・参加者の備考）は「どのデータで初期化したか」で判定し、データが変わったときだけ再実行（従来と同じ動作） |
| lint：`react-hooks/rules-of-hooks`（2件） | `useCancelParticipantDirect` はフックではない通常の関数だったが、名前が `use` で始まっていた（実害はない） | `cancelParticipantDirect` に改名 |
| lint：`react-hooks/refs`（1件） | 描画中にrefへ代入 | effectで更新（最新のコールバックを保持する定型の形） |
| 警告13件 | 未使用の変数・関数・import（表示しなくなったパンくずのデータ6件等）、重複import、`Array<T>`、不要になった `eslint-disable` コメント2件 | すべて解消（意図的に残した警告はない） |

`expo lint` の対象外の `scripts/*.js`（Node用スクリプト）は、`eslint .` を直接実行すると `__dirname` の未定義（3件）が出る。`expo lint` の対象ではなく、今回は変更していない。

## 6. testIDとアクセシビリティの方針（R7で整理）

- 画面のルートには、到達確認用の安定したtestID（例：`home-screen`）を付ける。中身が条件付きで空になりうる画面では特に必要。
- 操作要素（ボタン・行）には `{画面}-{要素}-{ID}` 形式のtestIDと `accessibilityRole`・`accessibilityLabel` を付ける。選択状態を持つボタンには `accessibilityState.selected` を付ける（稽古詳細の回答ボタンには現在ない。4の判断と合わせて対応する）。
- 仕様で表示しないと決めた要素（空の状態など）は、テストで「無いこと」を確認する。
- 画面を変更するコミットでは、同じコミットで関連テストを更新する（今回の失敗の大半は、画面変更後にテストを実行していなかったことが原因）。

## 7. R8（2026-11-11）：仕様矛盾7件の解消

R8指示書 §2 の確定事項に従って画面を直し、7件はすべて成功するようにした（テストの削除なし）。仕様の記録は 20-RehearsalManagementScreen.md §21、04-CommonNavigationDesign.md §20.3.1。

| テスト | R8の対応 |
| --- | --- |
| attendance-detail-add-members-add、-display | 稽古詳細に「参加メンバーを追加」を戻した（f575bc5 以前の部品を再利用）。Backendは追加者にだけ通知するよう修正 |
| attendance-detail-activate、-complete | 手動の開始・終了ボタンは設けない（確定事項）。テストを「確定の稽古に開始・終了ボタンが無く、activate/completeを呼ばない」「実施中の稽古で管理者がプルダウンで実績を選び［更新］で保存する」に書き換えた。後者は、行の選択がrenderRouter()の多段Stack/Tabの奥で画面の状態に届かない既知の制約のため単体描画 |
| attendance-detail-respond、-full | 「あなたの出欠」に本人の回答を表示し、回答ボタンは未回答のときだけ表示。-full の日時の確認は、日付と時刻が別要素になった現在の画面（32e281c 等）に合わせた（同じ値を確認） |
| schedule-timetable-create-link | 「＋ タイムテーブルを作成」を戻し、押すとタイムテーブルの作成・編集画面を開く（稽古一覧ではない）ことを確認する内容に変更 |

関連して変更・追加したテスト：attendance-detail-remarks（回答後はボタン・入力欄を出さず備考を表示）、schedule-timetable-create-link-member、timetable-editor-save-new、timetable-editor-save-existing-draft、home-rehearsal-card-attendance。

画面側の不具合として、稽古詳細の一覧（FlatList）が `extraData` なしでは行の選択状態の変化で再描画されない問題を、プルダウン追加時に `extraData` を指定して防いだ。
