# StageArt Blueprint — Questionnaire / アンケート機能

Version : 1.0
Status : Confirmed

## 1. Purpose

Production終了後の観客から感想・評価・意見を収集する匿名アンケート機能。

QuestionnaireはProduction-owned Entityとし、回答はReservationやPersonとは独立した匿名Responseとして扱う。

## 2. Domain Structure

```text
Production
  └─ Questionnaire
       └─ Anonymous Response
```

V1では1 Productionにつき1 Questionnaireを基本とする。

ProductionがCOMPLETEDになった後も、QuestionnaireがPUBLISHEDなら回答を受け付けられる。

## 3. Lifecycle

```text
DRAFT → PUBLISHED → CLOSED
```

- DRAFT：作成・編集。回答不可。
- PUBLISHED：公開ページから回答可能。
- CLOSED：回答受付終了。
- 任意の回答終了日時を設定でき、終了日時を過ぎた場合は回答不可。

## 4. Public Page

Productionの公開URL体系に合わせ、例えば以下のURLとする。

`https://stageart.top/{organization-slug}/{production-slug}/questionnaire`

実際のルーティングは既存公開ページ実装に合わせる。

URLにはPerson ID、Reservation ID、Account ID、Email、個人識別Token等を含めない。

公開ページにはProduction名、タイトル、説明、質問、回答欄、必須／任意を表示する。

一般観客はログインなしで回答できる。

## 5. Questions

Production管理者は質問を追加・編集・削除・並べ替えできる。タイトル、説明、選択肢、必須／任意も設定できる。

V1の質問形式：

1. 単一選択
2. 複数選択
3. 5段階評価
4. 自由記述
5. Yes / No

既存回答の意味を壊す変更は禁止する。既存回答が存在する公開済み質問の形式変更・削除等はDomain上で整合性を保てる制約を設ける。

## 6. Anonymous Response

Responseに保持するのは原則としてResponse ID、Questionnaire ID、回答日時、回答内容、および非識別的な最小技術情報のみとする。

以下をResponseへ保存してはならない：

- Person ID
- Reservation ID
- Booker name / email
- Account ID
- ExternalIdentity ID
- IssuedTicket ID
- attributedPersonId
- Organization Membership / Production Participant情報
- Check-in情報
- IP address
- User-Agent
- Device ID
- 回答者識別Cookie
- 回答者識別Token

Infrastructure上のアクセスログ等を回答者特定に利用するDomain設計にはしない。

## 7. Post-Performance Email

Performance終了後、該当Performanceの対象Reservationへアンケート依頼メールを送信する。送信単位はProductionではなくPerformance。

対象は主としてCHECKED_IN。CANCELLEDおよびNO_SHOWは対象外。

複数人数ReservationではGuestCount分のメールを送らず、Booker Emailへ1通送信する。

メールにはお礼、回答依頼、公開アンケートURLを含める。URLにはPerson ID、Reservation ID、Email等を含めない。

メールから回答してもResponseとReservation / Personを紐付けない。

同一Performance + Reservationへの重複送信を防止する。

メール送信失敗でQuestionnaire / Performance等の業務状態をRollbackしない。既存Notification / Email基盤を再利用し、V1でRetry Queueは導入しない。

## 8. QR Code

QRコードは公開アンケートURLから生成し、Emailと同じURLを使用する。

管理者はQRコードを表示、画像取得、印刷できる。

QRから回答してもPerson / Reservationとの関係は作成しない。

## 9. Submission Policy

V1では1人1回答を強制しない。同一人物の複数回答を禁止しない。

そのためCookie、Device ID、IP、User-Agent、Person ID、Reservation ID、Email、Account ID、個人識別Token等を回答制限に利用しない。

送信後は完了画面を表示し、送信済み回答の編集は不可。

## 10. Results

Production管理者は匿名性を維持して回答結果を確認する。

- 選択式：選択肢ごとの回答数
- 5段階評価：各評価の回答数、必要に応じた平均等
- 自由記述：回答本文一覧

管理者が回答者個人を特定できてはならない。将来Exportする場合も匿名性を維持する。

## 11. Management UI

Production管理画面に「アンケート」を配置し、以下を提供する。

- タイトル・説明
- 質問の追加・編集・削除・並べ替え
- 必須／任意
- 公開・終了
- 公開URL
- QRコード表示・画像取得・印刷
- 回答結果の確認

権限はProduction管理権限に従う。

## 12. Domain Relationship

Reservationはアンケート依頼メールの対象者選定にのみ利用する。

```text
Reservation ──X──> Response
Person      ──X──> Response

Production
  └─ Questionnaire
       └─ Anonymous Response

Performance
  └─ Reservation
       └─ Email recipient selection only
```

ReservationとResponseの間に回答者追跡関係を持たせない。

## 13. V1 Out of Scope

- 1 Productionに複数Questionnaire
- Performanceごとの別Questionnaire
- 回答者の本人特定
- 1人1回答の厳格な制限
- Check-in必須化
- Reservation / Person / TicketとResponseの紐付け
- アカウント認証必須の回答
- 複雑な分岐
- 高度な分析・回答者属性分析
- Pushによる依頼
- Retry Queue
- Performer / Staff向けアンケート
- Organization全体のアンケート
- テンプレート
- 必須CSV Export / グラフ表示

## 14. Acceptance Conditions

- Production管理者がアンケートを作成・編集できる
- QuestionnaireはProductionに所有される
- 質問を追加・編集・削除・並べ替えできる
- 必須／任意を設定できる
- V1の5種類の質問形式を利用できる
- DRAFT / PUBLISHED / CLOSEDを正しく扱う
- 公開URLを提供し、個人識別情報を含めない
- QRコードが公開URLを指す
- QRコードを表示・画像取得・印刷できる
- Performance終了後に対象Reservationへ依頼メールを送信する
- メールに公開URLを含め、個人識別情報を含めない
- メール／QRの入口からResponseとPerson / Reservationを紐付けない
- ResponseにPerson / Reservation / Email / IP / User-Agent / Device ID等を保存しない
- 選択式・5段階評価・自由記述の結果を匿名で確認できる
- 管理者が回答者を特定できない
- 1人1回答を強制しない
- 複数人数ReservationではBooker Emailへ1通送信する
- CANCELLED / NO_SHOWを依頼対象から除外する
- 同一Performance + Reservationへの重複送信を防止する
- メール失敗で業務状態をRollbackしない
- 既存Notification / Email基盤を再利用する
- Production権限に従って管理する
- 一般観客がログインなしで回答できる
- 送信後に完了画面を表示する
- 送信済み回答を編集できない
- ProductionがCOMPLETEDでもQuestionnaireがPUBLISHEDなら回答できる
- 任意の回答終了日時を設定できる
- 既存回答の意味を壊す質問変更を許可しない

## 15. Design Principle

> **「誰が答えたか」ではなく、「公演を見た人から何が寄せられたか」を扱う。**

EmailやQRは回答依頼の入口であり、回答者Identityへ変換してはならない。StageArtのPerson / Reservation管理とアンケートの匿名Feedback管理を明確に分離する。
