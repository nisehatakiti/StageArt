# Golden Rule

> **利用者はドメインモデルを意識しない。**

StageArtは、利用者にシステムの内部構造を理解させることを目的としない。

利用者が入力するのは「やりたいこと」であり、内部ドメインを操作することではない。

利用者が

- 劇団を作る
- 公演を作る
- チケットを販売する
- 稽古を登録する
- 受付をする

という操作を行うと、StageArtは必要なドメインオブジェクトを自動生成・管理する。

Project、Production、Reservation、Historyなどの内部ドメインは、システムが責任を持って生成・維持する。

新しい機能を追加する場合も、この原則を満たさなければならない。

利用者に内部構造を意識させる設計は、StageArtでは設計上の欠陥とみなす。

---

## Examples

利用者が「公演を作る」を実行すると、StageArtは以下を自動生成・準備する。

- Project
- Production
- Production Schedule
- Production Checklist
- Document Workspace
- Future Finance Workspace

**Public Web Pageは公演作成時点では一般公開しない。**

公演の情報公開が未設定または未公開の場合、Public Pageを生成・公開してはならない。

利用者が情報公開日時を予約した場合は、StageArtが指定日時にBackground Job等で公開処理を実行し、その時点でPublic Pageを生成・公開する。

利用者は、これらの内部構造を意識する必要はない。
