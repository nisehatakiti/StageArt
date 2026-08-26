# StageArt Blueprint

# System Administration Policy

Version : 1.0

---

# Purpose

本書は、StageArt全体を運用するシステム管理者（System Administrator）の責務、運用機能、バックアップ、メンテナンス、全体アナウンス、監査ログおよび将来のサービス移行を考慮した運用方針を定義する。

本書はOrganization / Productionの業務管理権限とは別に、StageArtサービスそのものを管理するためのシステム管理権限を定義する。

既存のOrganization Scope / Production ScopeのAuthorizationを変更するものではない。

---

# 1. System Administrator

StageArtには、通常のOrganization / Production権限とは独立したシステム管理者権限を設ける。

System AdministratorはStageArtサービス全体を管理するための権限を持つ。

基本方針は、通常利用者の権限体系を迂回して任意のデータを書き換えることを目的とするのではなく、障害調査・問い合わせ対応・運用管理を安全に行えるよう、全体を参照・操作できる管理者として設計することである。

---

# 2. 全権限による実機調査

System Administratorは、必要に応じて対象OrganizationまたはProductionを選択し、通常利用者が使用する画面・ナビゲーションへアクセスできる。

例：

```text
System Administrator
        ↓
Organizationを選択
        ↓
対象Organizationの通常画面
        ↓
Membership / Production / 会計 / その他の管理情報
```

```text
System Administrator
        ↓
Productionを選択
        ↓
対象Productionの通常画面
        ↓
参加者 / 稽古 / 公演 / チケット / 会計等
```

この機能は、ユーザーからの問い合わせ、障害調査、データ不整合調査等を実機に近い状態で確認するために使用する。

System Administratorが通常画面へアクセスした場合も、操作主体はSystem AdministratorとしてAudit Logへ記録する。

---

# 3. Authorization Boundary

Organization / Productionの通常権限とSystem Administrator権限は明確に分離する。

通常利用者：

```text
Person
  ↓
Scope
  ↓
Role
  ↓
Permission
```

System Administrator：

```text
Authenticated User
  ↓
System Administrator
  ↓
System-level Permission
```

ただし、UI上のアクセス可能範囲を広げる場合でも、Security BoundaryをUIだけに依存してはならない。

Server SideでSystem Administratorであることを検証する。

---

# 4. 全体アナウンス

System Administratorは、StageArt全ユーザーに対して一斉アナウンスを作成・配信できる。

主な用途：

- システムメンテナンスのお知らせ
- サービス障害・復旧のお知らせ
- 重要なサービス変更のお知らせ
- 利用者全体に影響する運用上の案内

アナウンスは管理画面から入力し、StageArt内通知およびEmailで配信する。

既存のNotification Policyに定義された「StageArt内通知＋Email」というV1の通知チャネル方針に従う。

---

# 5. Announcement Data

全体アナウンスには少なくとも以下を保持する。

- Title
- Body
- Created At
- Created By
- Published At
- Target Scope
- Delivery Status

Target ScopeはV1では原則として全ユーザーを対象とする。

将来的に対象を限定する必要が生じた場合は、別途Audience / Targeting仕様として定義する。

配信処理の成否はAudit / Delivery情報として確認可能とする。

---

# 6. メンテナンスモード

StageArtは、サービス保守・データベース保守・アプリケーション更新等のため、通常利用者のアクセスを一時的に制限できるMaintenance Modeを持つ。

基本状態：

```text
Normal
  ↓
Maintenance Scheduled
  ↓
Maintenance Active
  ↓
Normal
```

Maintenance Modeの開始・終了はSystem Administratorが管理する。

---

# 7. メンテナンス時の利用者体験

Maintenance Active中は、一般利用者に対してメンテナンス中であることを明確に表示する。

必要に応じて、事前に全体アナウンスを配信する。

表示内容には、少なくとも以下を含められるようにする。

- メンテナンス理由
- 開始予定時刻
- 終了予定時刻
- 最新のお知らせ

予定時刻を超過した場合も、System Administratorが状態を更新できる。

---

# 8. System Administratorのメンテナンスアクセス

Maintenance Active中であっても、System Administratorは管理画面および必要な通常画面へアクセスできるものとする。

これにより、メンテナンス中の動作確認、障害調査、復旧確認を可能とする。

ただし、Database Migration等、技術的に全アクセスを停止する必要がある作業については、Infrastructure側で別途完全停止を行う場合がある。

---

# 9. Audit Log

StageArtでは、重要なBusiness Factおよび管理操作についてAudit Logを記録する。

基本的に、データの登録・変更・削除・承認・拒否等の状態変更について、操作主体と時刻を記録する。

最低限、以下を記録する。

- Timestamp
- Actor User / Person
- Action
- Target Entity Type
- Target Entity ID
- Scope
- Result
- 必要に応じた変更前・変更後の情報

Audit Logは、誰が、いつ、何に対して、何を行ったかを追跡できることを目的とする。

---

# 10. Audit対象

少なくとも以下の操作をAudit対象とする。

- Organization登録・変更・削除
- Membership登録・変更・削除
- Production登録・変更・削除
- Participant登録・変更・削除
- Rehearsal / Performance等の主要業務情報変更
- Ticket / Reservation等の重要情報変更
- Accounting情報の登録・変更・承認等
- 権限・Role・Permissionの変更
- System Administratorによる管理操作
- 全体アナウンスの作成・配信
- Maintenance Modeの開始・終了
- Backupの実行・復旧等の管理操作

認証・セキュリティ上重要な操作についても、必要な範囲でAudit情報を記録する。

---

# 11. Audit Logの原則

Audit Logは、通常の業務データを削除・更新することで履歴が失われない構造とする。

Audit Log自身の改変権限は厳格に制限する。

System AdministratorがAudit Logを参照したこと自体も、必要に応じてAudit対象とする。

Audit Logは障害調査・問い合わせ対応・不正操作調査等に利用する。

---

# 12. 日次バックアップ

StageArtの永続データについて、日次バックアップを実施する。

基本スケジュールは、毎日午前2時頃にCRON等のスケジューラによって自動実行する。

```text
Daily
  02:00頃
    ↓
Backup Job
    ↓
Database / 必要な永続データ
    ↓
Backup Storage
```

実際のバックアップ対象、保持期間、暗号化、保存先、復旧手順等はInfrastructure / Operations仕様で具体化する。

---

# 13. Backup Monitoring

System Administratorは、バックアップの実行結果を確認できるものとする。

少なくとも以下を確認可能とする。

- 最終成功日時
- 最終失敗日時
- Backup Jobの状態
- Backup対象
- 復旧に利用可能なバックアップの有無

バックアップ失敗を放置しないため、将来的には失敗時の管理者通知も実装する。

---

# 14. Manual Backup / Recovery

必要に応じてSystem Administratorが手動バックアップを実行できるようにする。

また、障害発生時には、承認された手順に基づいてバックアップから復旧できる構造を持たせる。

Recovery操作は重大な管理操作であるため、必ずAudit Logへ記録する。

V1では、復旧手順そのものをUI上の自由操作として公開することを必須としない。

---

# 15. Backupとサービス移行

将来的なクラウドサービス、Infrastructure、Database等の移行を考慮し、バックアップを特定サービスに完全依存した形式だけに固定しない。

可能な範囲で、Domain Factを再構築・移行できる情報を保持する。

ただし、現フェーズではサービス移行そのものを実装目標とはしない。

---

# 16. Mirroring / Replication

将来的なサービス移行、可用性向上、障害対策のため、Database Mirroring / Replication等を導入する可能性はある。

ただし、V1ではミラーリング／レプリケーションを必須実装としない。

現フェーズでは、まず以下を優先する。

1. 正常な日次バックアップ
2. バックアップ状態の監視
3. 復旧可能性の確保
4. Audit Logによる操作追跡

サービス規模・可用性要件が明確になった段階で、Mirroring / Replication / Failover等を再評価する。

---

# 17. Operational Principles

System Administrationでは、以下を基本原則とする。

- 全権限を持つ管理者であっても、操作主体を隠さない。
- 管理者による操作をAudit Logへ残す。
- 本番データを直接Databaseから書き換える運用を基本としない。
- 通常画面・Application Use Caseを利用して調査・管理できる構造を優先する。
- バックアップを日次で自動取得する。
- バックアップ失敗を検知できるようにする。
- メンテナンス前には可能な限り事前アナウンスを行う。
- メンテナンス中も管理者が状態確認できるようにする。
- ミラーリング等の高度な冗長化は、必要性が明確になってから導入する。

---

# 18. V1 Scope

V1で以下を設計対象とする。

- System Administrator
- 全体アナウンス
- StageArt内通知＋Emailによる全体通知
- Maintenance Mode
- System Administratorによる通常画面アクセス
- Audit Log
- 日次自動バックアップ
- Backup状態確認
- 手動Backup
- Recovery手順を考慮したInfrastructure設計

以下は将来検討とする。

- Database Mirroring
- Database Replication
- Multi-region構成
- 高度なFailover
- 高度なAnnouncement Targeting

---

# Business Rules

- System AdministratorはStageArt全体を管理できる。
- System AdministratorはOrganization / Productionの通常画面へアクセスして調査できる。
- System Administratorによる操作もAudit対象とする。
- 全体アナウンスは管理画面から作成する。
- 全体アナウンスはStageArt内通知およびEmailで配信する。
- Maintenance ModeをSystem Administratorが開始・終了できる。
- Maintenance Active中もSystem Administratorは必要な管理画面・通常画面へアクセスできる。
- 重要な登録・変更・削除・承認等には時刻と操作主体を記録する。
- バックアップは原則として毎日午前2時頃に自動実行する。
- Backup / Recovery操作はAudit対象とする。
- ミラーリング／レプリケーションはV1の必須要件としない。
- 将来のサービス移行を妨げないバックアップ・データ管理を目指す。
