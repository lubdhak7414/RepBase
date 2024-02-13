-- RepBase Database Schema & Seed Data
-- MySQL 8.0 / MariaDB 10.11

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS booking;
DROP TABLE IF EXISTS membership;
DROP TABLE IF EXISTS class;
DROP TABLE IF EXISTS trainer;
DROP TABLE IF EXISTS plan;
DROP TABLE IF EXISTS member;
DROP TABLE IF EXISTS staff;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------
-- Core tables
-- -------------------------------------------------------

CREATE TABLE plan (
    Plan_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(80)    NOT NULL,
    PriceMonthly DECIMAL(8,2)   NOT NULL,
    DurationDays INT UNSIGNED   NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member (
    Member_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name       VARCHAR(120)  NOT NULL,
    Email      VARCHAR(180)  NOT NULL UNIQUE,
    Password   VARCHAR(255)  NOT NULL,
    Phone      VARCHAR(30)   DEFAULT NULL,
    JoinDate   DATE          NOT NULL DEFAULT (CURDATE())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE membership (
    Membership_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Member_id     INT UNSIGNED NOT NULL,
    Plan_id       INT UNSIGNED NOT NULL,
    StartDate     DATE         NOT NULL,
    EndDate       DATE         NOT NULL,
    Active        TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_ms_member FOREIGN KEY (Member_id) REFERENCES member (Member_id) ON DELETE CASCADE,
    CONSTRAINT fk_ms_plan   FOREIGN KEY (Plan_id)   REFERENCES plan   (Plan_id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trainer (
    Trainer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name       VARCHAR(120) NOT NULL,
    Specialty  VARCHAR(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE class (
    Class_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Title      VARCHAR(120)  NOT NULL,
    Trainer_id INT UNSIGNED  NOT NULL,
    StartsAt   DATETIME      NOT NULL,
    Capacity   INT UNSIGNED  NOT NULL DEFAULT 20,
    Room       VARCHAR(60)   DEFAULT NULL,
    CONSTRAINT fk_cl_trainer FOREIGN KEY (Trainer_id) REFERENCES trainer (Trainer_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE booking (
    Booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Class_id   INT UNSIGNED NOT NULL,
    Member_id  INT UNSIGNED NOT NULL,
    BookedAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status     ENUM('booked','waitlisted','cancelled') NOT NULL DEFAULT 'booked',
    CONSTRAINT fk_bk_class  FOREIGN KEY (Class_id)  REFERENCES class  (Class_id)  ON DELETE CASCADE,
    CONSTRAINT fk_bk_member FOREIGN KEY (Member_id) REFERENCES member (Member_id) ON DELETE CASCADE,
    CONSTRAINT uq_class_member UNIQUE (Class_id, Member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
    Class_id    INT UNSIGNED NOT NULL,
    Member_id   INT UNSIGNED NOT NULL,
    CheckedInAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Class_id, Member_id),
    CONSTRAINT fk_at_class  FOREIGN KEY (Class_id)  REFERENCES class  (Class_id)  ON DELETE CASCADE,
    CONSTRAINT fk_at_member FOREIGN KEY (Member_id) REFERENCES member (Member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment (
    Payment_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Member_id     INT UNSIGNED   NOT NULL,
    Membership_id INT UNSIGNED   NOT NULL,
    Amount        DECIMAL(8,2)   NOT NULL,
    PaidAt        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Method        ENUM('cash','card','bank_transfer') NOT NULL DEFAULT 'card',
    CONSTRAINT fk_pay_member     FOREIGN KEY (Member_id)     REFERENCES member     (Member_id)     ON DELETE CASCADE,
    CONSTRAINT fk_pay_membership FOREIGN KEY (Membership_id) REFERENCES membership (Membership_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff (
    Staff_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Username  VARCHAR(80)  NOT NULL UNIQUE,
    Password  VARCHAR(255) NOT NULL,
    Role      ENUM('admin','trainer') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Seed data
-- -------------------------------------------------------

-- Plans
INSERT INTO plan (Name, PriceMonthly, DurationDays) VALUES
    ('Basic',    19.99,  30),
    ('Standard', 34.99,  30),
    ('Premium',  54.99,  30),
    ('Annual',   29.99, 365);

-- Trainers
INSERT INTO trainer (Name, Specialty) VALUES
    ('Marcus Webb',    'Strength & Conditioning'),
    ('Priya Sharma',   'Yoga & Flexibility'),
    ('Danny Okonkwo',  'HIIT & Cardio'),
    ('Leila Navarro',  'Pilates & Core');

-- Members (passwords are bcrypt of "member123" — will be updated in commit #17)
-- Temporary md5 placeholder for naive era; real bcrypt applied at hardening commit
INSERT INTO member (Name, Email, Password, Phone, JoinDate) VALUES
    ('Alice Thornton',  'alice@example.com',   '$2y$12$0dUXclsJj.EpGo7pE7NDYO9UpM7FVGgBWzlbUJLlSKrGbqtfXr0nO', '555-0101', '2024-02-10'),
    ('Ben Castillo',    'ben@example.com',     '$2y$12$0dUXclsJj.EpGo7pE7NDYO9UpM7FVGgBWzlbUJLlSKrGbqtfXr0nO', '555-0102', '2024-02-15'),
    ('Clara Demir',     'clara@example.com',   '$2y$12$0dUXclsJj.EpGo7pE7NDYO9UpM7FVGgBWzlbUJLlSKrGbqtfXr0nO', '555-0103', '2024-03-01'),
    ('David Park',      'david@example.com',   '$2y$12$0dUXclsJj.EpGo7pE7NDYO9UpM7FVGgBWzlbUJLlSKrGbqtfXr0nO', '555-0104', '2024-03-10'),
    ('Emeka Osei',      'emeka@example.com',   '$2y$12$0dUXclsJj.EpGo7pE7NDYO9UpM7FVGgBWzlbUJLlSKrGbqtfXr0nO', '555-0105', '2024-04-01');

-- Staff (admin password: "admin2024", trainer password: "trainer2024")
-- bcrypt hashes generated with password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO staff (Username, Password, Role) VALUES
    ('admin',        '$2y$12$5aDVuoTMO8nWRPGXSmQ7GOAbTiQzVkwXYNVT8s3iiO0MnQmm5Yzda', 'admin'),
    ('marcus.webb',  '$2y$12$Oyk4k7YUXTrjrBnvHfN.dOhpQfJGpmDnlIfAXCHHqnvTQ7VoRXNWW', 'trainer'),
    ('priya.sharma', '$2y$12$Oyk4k7YUXTrjrBnvHfN.dOhpQfJGpmDnlIfAXCHHqnvTQ7VoRXNWW', 'trainer'),
    ('danny.ok',     '$2y$12$Oyk4k7YUXTrjrBnvHfN.dOhpQfJGpmDnlIfAXCHHqnvTQ7VoRXNWW', 'trainer'),
    ('leila.nav',    '$2y$12$Oyk4k7YUXTrjrBnvHfN.dOhpQfJGpmDnlIfAXCHHqnvTQ7VoRXNWW', 'trainer');

-- Memberships
INSERT INTO membership (Member_id, Plan_id, StartDate, EndDate, Active) VALUES
    (1, 3, '2024-02-10', '2024-03-10', 0),
    (1, 3, '2024-03-10', '2024-04-10', 0),
    (1, 3, '2024-04-10', '2024-05-10', 1),
    (2, 2, '2024-02-15', '2024-03-15', 0),
    (2, 2, '2024-03-15', '2024-04-15', 1),
    (3, 1, '2024-03-01', '2024-03-31', 0),
    (3, 2, '2024-04-01', '2024-05-01', 1),
    (4, 4, '2024-03-10', '2025-03-09', 1),
    (5, 1, '2024-04-01', '2024-05-01', 1);

-- Classes (mix of past and upcoming relative to mid-2024)
INSERT INTO class (Title, Trainer_id, StartsAt, Capacity, Room) VALUES
    ('Morning Strength',       1, '2024-05-06 07:00:00', 15, 'Studio A'),
    ('Lunchtime HIIT',         3, '2024-05-06 12:30:00', 20, 'Studio B'),
    ('Evening Yoga',           2, '2024-05-06 18:00:00', 12, 'Studio C'),
    ('Core Pilates',           4, '2024-05-07 09:00:00', 10, 'Studio C'),
    ('Strength Fundamentals',  1, '2024-05-07 17:00:00', 15, 'Studio A'),
    ('Cardio Blast',           3, '2024-05-08 06:30:00', 20, 'Studio B'),
    ('Flexibility Flow',       2, '2024-05-08 11:00:00', 12, 'Studio C'),
    ('Total Body Conditioning',1, '2024-05-09 07:00:00', 15, 'Studio A'),
    ('Power Yoga',             2, '2024-05-10 18:30:00', 12, 'Studio C'),
    ('HIIT Express',           3, '2024-05-11 07:30:00',  8, 'Studio B');

-- Bookings
INSERT INTO booking (Class_id, Member_id, BookedAt, Status) VALUES
    (1, 1, '2024-05-03 10:00:00', 'booked'),
    (1, 2, '2024-05-03 10:05:00', 'booked'),
    (1, 3, '2024-05-03 10:10:00', 'booked'),
    (2, 1, '2024-05-03 11:00:00', 'booked'),
    (2, 4, '2024-05-03 11:05:00', 'booked'),
    (3, 2, '2024-05-04 09:00:00', 'booked'),
    (3, 5, '2024-05-04 09:05:00', 'booked'),
    (4, 1, '2024-05-04 10:00:00', 'booked'),
    (4, 3, '2024-05-04 10:10:00', 'booked'),
    (5, 2, '2024-05-04 11:00:00', 'booked'),
    (6, 4, '2024-05-05 08:00:00', 'booked'),
    (7, 1, '2024-05-05 08:30:00', 'booked'),
    (8, 2, '2024-05-05 09:00:00', 'booked'),
    (9, 3, '2024-05-05 09:10:00', 'booked'),
    (10,1, '2024-05-06 07:00:00', 'booked'),
    (10,2, '2024-05-06 07:05:00', 'booked'),
    (10,3, '2024-05-06 07:10:00', 'booked'),
    (10,4, '2024-05-06 07:15:00', 'booked'),
    (10,5, '2024-05-06 07:20:00', 'booked');

-- Attendance (past classes)
INSERT INTO attendance (Class_id, Member_id, CheckedInAt) VALUES
    (1, 1, '2024-05-06 07:02:00'),
    (1, 2, '2024-05-06 07:04:00'),
    (2, 1, '2024-05-06 12:32:00'),
    (2, 4, '2024-05-06 12:31:00'),
    (3, 2, '2024-05-06 18:01:00'),
    (4, 1, '2024-05-07 09:03:00');

-- Payments
INSERT INTO payment (Member_id, Membership_id, Amount, PaidAt, Method) VALUES
    (1, 1, 54.99, '2024-02-10 10:00:00', 'card'),
    (1, 2, 54.99, '2024-03-10 10:00:00', 'card'),
    (1, 3, 54.99, '2024-04-10 10:00:00', 'card'),
    (2, 4, 34.99, '2024-02-15 11:00:00', 'cash'),
    (2, 5, 34.99, '2024-03-15 11:00:00', 'card'),
    (3, 6, 19.99, '2024-03-01 14:00:00', 'bank_transfer'),
    (3, 7, 34.99, '2024-04-01 14:00:00', 'card'),
    (4, 8, 359.88, '2024-03-10 09:00:00', 'card'),
    (5, 9, 19.99, '2024-04-01 16:00:00', 'cash');
