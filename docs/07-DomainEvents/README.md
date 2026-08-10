# StageArt Blueprint
# 07 - Domain Events

Version : 2.0

---

# Purpose

Domain Eventは、Domain内で発生した重要なBusiness Eventを表す。

Domain Eventは「何が起きたか」という事実を表現し、
そのEventを契機として別のBusiness Processを開始する。

Domain Eventを利用することで、
Domain間の直接依存を減らし、
Business Processを疎結合に連携する。

---

# Concept

Domain Eventは、発生元DomainがBusiness Actionを完了したことを表す。

```text
Domain
    │
    │ Business Action
    ▼
Domain Event
    │
    ▼
Event Handler
    │
    ▼
Business Process