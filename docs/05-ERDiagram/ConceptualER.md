# StageArt Blueprint
# Conceptual ER Diagram

Version : 3.0

---

# Purpose

Conceptual ER Diagramは、StageArtを構成する主要なDomain同士の関係を表現する。

実装やデータベース構造ではなく、
Business上の概念と関係性を示す。

---

# Conceptual Model

```mermaid
erDiagram

    Account ||--o| Person : authenticates

    Person ||--o{ Membership : belongs_to
    Organization ||--o{ Membership : has

    Organization ||--o{ Project : owns
    Project ||--|| Production : manages

    Production ||--|| Person : has_primary_manager
    Production ||--o{ ProductionDelegate : delegates
    ProductionDelegate }o--|| Person : assigned_to
    ProductionDelegate }o--|| DelegateRole : has_role

    Production ||--o{ Performance : has
    Production ||--o{ Participant : has

    Participant }o--|| Subject : represents
    Subject ||--o| Person : refers_to
    Subject ||--o| Organization : refers_to

    Performance ||--o{ Reservation : accepts

    Reservation }o--o| Participant : handled_by
    Reservation ||--o{ Companion : has
    Reservation ||--o{ ReservationSeat : has

    Subject ||--o{ History : has

    Production ||--o{ History : relates_to
    Performance ||--o{ History : relates_to