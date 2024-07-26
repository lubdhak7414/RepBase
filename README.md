# RepBase

Gym membership, class-booking, and attendance management system built with PHP 8.2 and MySQL.

## Features

- Member registration, login, and membership tracking
- Class schedule with capacity limits and automatic waitlist promotion
- Trainer roster and attendance check-in
- Admin panel: member management, payments, expiring memberships, revenue reports

## Prerequisites

- PHP 8.2+
- MySQL 8.0 / MariaDB 10.11+
- Web server or `php -S localhost:8080`

## Setup

1. `CREATE DATABASE repbase;`
2. `mysql -u root repbase < database.sql`
3. Edit `config.php` with your DB credentials
4. `php -S localhost:8080`

## Demo accounts

| Role    | Credential               | Password   |
|---------|--------------------------|------------|
| Member  | alice@example.com (email) | member123 |
| Admin   | admin (username)          | admin123  |
| Trainer | marcus.webb (username)    | trainer123 |

## Sample queries

### Remaining seats in a class
```sql
SELECT c.Title, c.Capacity - COUNT(b.Booking_id) AS seats_left
FROM class c
LEFT JOIN booking b ON b.Class_id = c.Class_id AND b.Status = 'booked'
GROUP BY c.Class_id;
```

### Expiring memberships (next 7 days)
```sql
SELECT m.Name, m.Email, ms.EndDate
FROM membership ms
JOIN member m ON ms.Member_id = m.Member_id
WHERE ms.EndDate BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
  AND ms.Active = 1;
```

### Revenue per plan
```sql
SELECT p.Name, SUM(pay.Amount) AS total_revenue
FROM payment pay
JOIN membership ms ON pay.Membership_id = ms.Membership_id
JOIN plan p ON ms.Plan_id = p.Plan_id
GROUP BY p.Plan_id
ORDER BY total_revenue DESC;
```

## License

MIT
