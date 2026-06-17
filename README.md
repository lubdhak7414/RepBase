# RepBase

Gym management system — members, class bookings, waitlists, attendance, and payments. PHP + MySQL, no framework.

The booking/waitlist flow is transaction-heavy: booking a full class puts you on the waitlist, and cancelling a spot auto-promotes the next person in line.

## Roles

- **Member** — view schedule, book/cancel classes, see membership status and payment history
- **Trainer** — view their own class roster, mark attendance
- **Admin** — everything: manage members, record payments, view expiring memberships, revenue by plan

## Setup

You need PHP 8.2+ and MySQL 8.0 / MariaDB 10.11+.

```bash
mysql -u root -p -e "CREATE DATABASE repbase;"
mysql -u root -p repbase < database.sql
```

Edit `config.php`, then start the server:

```bash
php -S localhost:8080
```

## Accounts

| Role | Login | Password |
|------|-------|----------|
| Member | alice@gym.local (email field) | member123 |
| Admin | admin | admin123 |
| Trainer | marcus.webb | trainer123 |

## Queries

Available spots per class:
```sql
SELECT c.Title, c.Capacity - COUNT(b.Booking_id) AS seats_left
FROM class c
LEFT JOIN booking b ON b.Class_id = c.Class_id AND b.Status = 'booked'
GROUP BY c.Class_id;
```

Members whose memberships expire in the next week:
```sql
SELECT m.Name, m.Email, ms.EndDate
FROM membership ms
JOIN member m ON ms.Member_id = m.Member_id
WHERE ms.EndDate BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
  AND ms.Active = 1;
```

Revenue by plan:
```sql
SELECT p.Name, SUM(pay.Amount) AS total_revenue
FROM payment pay
JOIN membership ms ON pay.Membership_id = ms.Membership_id
JOIN plan p ON ms.Plan_id = p.Plan_id
GROUP BY p.Plan_id
ORDER BY total_revenue DESC;
```

## Docker

```bash
docker compose up --build
```

App runs at http://localhost:8080. The database imports automatically on first start. Demo credentials are the same as above.

```bash
docker compose down -v
```

## License

MIT
