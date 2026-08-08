# StageArt Blueprint
# Logical ER Diagram

```mermaid
erDiagram

    Account ||--|| Person : owns

    Person ||--o{ Membership : belongs
    Organization ||--o{ Membership : has

    Organization ||--o{ Project : owns

    Project ||--|| Production : publishes

    Production ||--o{ Performance : schedules

    Production }o--|| Category : category
    Production }o--o{ Genre : genres
    Production }o--o{ Tag : tags

    Production ||--o{ Participant : participants

    Performance ||--o{ Reservation : reservations

    Performance ||--o{ Seat : seats

    Reservation ||--o{ ReservationSeat : reserves
    Reservation ||--o{ Companion : accompanies

    Person ||--o{ Participant : participates

```
