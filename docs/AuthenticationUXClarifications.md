# StageArt Blueprint

# Authentication UX Clarifications

Version : 1.0

---

## 01 Authentication と Googleサービス連携は別概念

StageArtにおける「GoogleアカウントでStageArtへログインすること」と、「Google Calendar / Google Drive等のGoogleサービスをStageArtから利用すること」は別の認証・認可として扱う。

GoogleでStageArtへ登録・ログインしたことだけを理由に、StageArtがユーザーのGoogle CalendarやGoogle Driveへアクセスできる状態にはしない。

Googleサービスへのアクセス権は、各サービスを実際に利用するタイミングで必要なScopeについて追加認可を取得する。

```text
GoogleでStageArtへログイン
        ↓
StageArt UserAccountを認証
        ↓
通常のStageArt利用

必要になった時だけ
        ↓
「Google Calendarと連携」
        ↓
Google側で追加権限を許可
        ↓
Calendar利用可能

必要になった時だけ
        ↓
「Google Driveと連携」
        ↓
Google側で追加権限を許可
        ↓
Drive利用可能
```

毎回StageArtからログアウトしてGoogleへ再ログインするようなUXにはしない。

---

## 02 Googleで登録したユーザー

GoogleをStageArtのAuthentication Providerとして利用しているユーザーは、StageArtへのログインについて既にGoogle認証済みである。

そのため、設定画面等に「Google認証」を改めて行わせるためのメニューを表示する必要はない。

Google Calendar / Drive等を利用する場合は、「Googleサービス連携」をサービス単位で表示し、必要になった時点で追加認可を行う。

例：

```text
ログイン方法
  Google
  └─ GoogleでStageArtにログイン中

Googleサービス
  Google Calendar
    └─ 未連携 / 連携済み
  Google Drive
    └─ 未連携 / 連携済み
```

「Googleログイン済み」と「Googleサービス連携済み」は別状態として管理する。

---

## 03 Email + Passwordで登録したユーザー

Email + PasswordをStageArtのAuthentication Providerとして利用しているユーザーについては、Google External Identityが未連携の場合、設定画面等に「Googleアカウントを連携」を表示できる。

本人がGoogleアカウントを明示的に連携することで、既存のUserAccountにGoogle ExternalIdentityを追加する。

```text
Email + Password
       ↓
Googleアカウントを連携
       ↓
同じUserAccount
   ├─ EmailCredential
   └─ ExternalIdentity(provider=google)
```

Emailアドレスが一致することだけを理由に別UserAccountを自動統合しない。本人が認証済みの状態で明示的に連携する既存方針を維持する。

---

## 04 設定画面の表示方針

ユーザーのAuthentication Providerの状態に応じて、設定項目を出し分ける。

### Google登録ユーザー

- 「Google認証」は表示しない
- Googleログイン済みであることをログイン方法として表示してよい
- Google Calendar / Drive等は、それぞれ独立したサービス連携項目として表示する

### Email + Password登録ユーザー

- Email + Passwordをログイン方法として表示
- Google External Identityが未連携なら「Googleアカウントを連携」を表示
- Google連携後は、Googleを追加のログイン方法として表示できる
- Google Calendar / Drive等のサービス連携は、Googleログイン連携とは別項目として扱う

---

## 05 Googleサービス連携の基本原則

Google Calendar、Google Driveその他の外部Googleサービスは、StageArtのUserAccount認証とは独立したExternal Connection / Authorizationとして扱う。

ユーザーが実際に対象サービスを利用するときにのみ必要な追加権限を要求する。

StageArtの初回登録時に、Calendar・Drive等の不要な権限をまとめて要求しない。

これにより、初回登録時の認証画面を簡潔にし、ユーザーがStageArtへ必要以上のGoogle権限を与えることを避ける。

---

## 06 UI上の用語

「Google認証」という表現は、StageArtへのログイン方法を指す場合に限定する。

Google Calendar / Google Drive等については、原則として「連携」または「Googleサービス連携」と表現する。

```text
StageArtへのログイン
  Googleでログイン

Googleサービス
  Calendarを連携
  Driveを連携
```

これにより、「StageArtへのログイン」と「Googleサービスへのアクセス許可」をユーザーが混同しないようにする。

---

## 07 今後の拡張

Google以外の外部サービスについても、StageArt UserAccountのAuthentication Providerと、Organization / Personが利用するExternal Connectionを混同しない。

サービス連携は必要なタイミングで個別認可できる構造を基本とし、認証基盤と外部サービス連携基盤を独立して拡張できるようにする。
