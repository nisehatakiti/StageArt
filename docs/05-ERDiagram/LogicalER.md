# StageArt Blueprint
# Logical ER Diagram

Version : 1.0

---

## Purpose

Logical ER Diagramは、各ドメインおよび関連エンティティの論理構造を表す。

---

```mermaid
erDiagram

    Account ||--|| Person : owns

    Person ||--o{ Membership : belongs
    Organization ||--o{ Membership : has

    Organization ||--o{ Project : owns

    Project ||--|| Production : publishes

    Production ||--o{ Performance : schedules
    Production ||--o{ Participant : participants

    Production }o--|| Category : category
    Production }o--o{ Genre : genres
    Production }o--o{ Tag : tags

    Performance ||--o{ Seat : seats

    Performance ||--o{ Reservation : reservations

    Reservation ||--o{ ReservationSeat : reserves
    Reservation ||--o{ Companion : accompanies

    Person ||--o{ Participant : participates

```

---

## Aggregate

```
Organization

└── Project

    └── Production

        ├── Participant

        ├── Performance

        │   ├── Seat

        │   └── Reservation

        │       ├── ReservationSeat

        │       └── Companion

        ├── Category

        ├── Genre

        └── Tag
```

---

## Aggregate Root

| Aggregate | Root |
|-----------|------|
| Organization | Organization |
| Project | Project |
| Production | Production |
| Performance | Performance |
| Reservation | Reservation |

Reservationは以下の子エンティティを管理する。

- ReservationSeat
- Companion

これらはReservationを経由してのみ変更できる。
