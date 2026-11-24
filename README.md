# Blue Lagoon Villa: Coastal Villa & Room Booking System

**Module:** IT1208 Web Technologies, Continuous Assessment 2 (Take-Home Assignment, 2026)
**Technologies:** HTML5, CSS3 (Bootstrap 5), JavaScript (ES6), PHP 8 (PDO), MySQL

A dynamic web application for a small beachfront villa in Mirissa, Sri Lanka (a fictional business).
Visitors can explore the villa, browse rooms, check availability and send booking requests.
The administrator manages rooms and confirms or cancels bookings.

---

## 1. Implemented features

### Guest / visitor side
- **Home page:** villa introduction, hero banner, highlights, featured rooms, location map and a clear *Book Now* button
- **Rooms page:** room cards with image, description, facilities, capacity and price; filter by type, guests, maximum price and facility; sort by price or capacity
- **Room details page:** full details, facilities, already-booked dates and an availability/price checker
- **Availability search:** check-in, check-out and number of guests, listing only suitable rooms that are free
- **Booking form:** room, dates, guests and special requests, with a live nights and total-cost summary
- **My Bookings:** a guest sees only their own bookings, with status (Pending / Confirmed / Cancelled), and can cancel pending bookings
- **Registration, login and logout** with PHP sessions

### Administrator side
- **Dashboard:** booking statistics, pending requests, upcoming arrivals and confirmed revenue per room
- **Manage rooms (CRUD):** add, view, edit (details, price, image) and delete or deactivate rooms
- **Manage bookings:** search, filter by status and room, pagination; confirm or cancel bookings
- **Overlap prevention:** two confirmed bookings for the same room can never overlap (see section 6)

### Additional features (optional section of the brief)
- Search and filter rooms by price, facilities and room type
- AJAX-based availability checking on the booking form (`api/check-availability.php`)
- Administrator dashboard with booking statistics
- Interactive location map (OpenStreetMap embed)
- Room image upload with preview

---

## 2. Software requirements

| Software | Version |
|----------|---------|
| XAMPP / WAMP / any Apache + PHP + MySQL stack | PHP **8.0 or newer**, MySQL 8 / MariaDB 10.4 or newer |
| PHP extensions | `pdo_mysql`, `fileinfo`, `mbstring` (all enabled by default in XAMPP) |
| Web browser | Any modern browser (Chrome, Firefox, Edge, Safari) |
| Internet connection | Needed to load Bootstrap, Bootstrap Icons and the map from CDNs |

---

## 3. Installation and configuration

1. **Copy the project.** Place the `villa-booking` folder inside `htdocs` (XAMPP) or `www` (WAMP).
   (You may rename the folder; links adjust automatically.)
2. **Start Apache and MySQL** from the XAMPP/WAMP control panel.
3. **Import the database.**
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`), choose the **Import** tab, select `db_structure.sql` and click *Go*.
   - Or from a terminal: `mysql -u root < db_structure.sql`
   - This creates the `villa_booking` database with tables, sample users, rooms and bookings.
4. **Check the database settings** in `config/db.php`. The defaults match XAMPP (`root`, empty password). Change `DB_USER` / `DB_PASS` if your server differs.
5. **Add images (optional).** Put `hero.jpg` in `assets/images/` and `room1.jpg` to `room6.jpg` in `assets/images/rooms/`. Pages show a placeholder if they are missing. See `assets/images/README.txt`.
6. **Open the site:** `http://localhost/villa-booking/`

On Linux or macOS, make `assets/images/rooms/` writable by the web server so the admin can upload room images.

---

## 4. Sample login credentials

All sample accounts use the password **`password`**. Change them before any real use.

| Role | Email | Password |
|------|-------|----------|
| Administrator | `admin@bluelagoonvilla.lk` | `password` |
| Guest | `nimali@example.com` | `password` |
| Guest | `kasun@example.com` | `password` |
| Guest | `sarah@example.com` | `password` |

New guests can register from the *Register* page. Self-registration always creates a **guest**; the admin account exists only in the SQL file.

---

## 5. Project structure

```
villa-booking/
├── db_structure.sql          Database structure and sample data
├── index.php                 Home page
├── rooms.php                 Rooms listing with filters
├── room-details.php          Single room details
├── availability.php          Availability search
├── book.php                  Booking form
├── my-bookings.php           Guest's bookings
├── cancel-booking.php        Guest cancels a pending booking
├── config/db.php             PDO connection
├── includes/                 auth.php, functions.php, components.php, header.php, footer.php
├── auth/                     register.php, login.php, logout.php
├── admin/                    index.php, rooms.php, room-form.php, room-delete.php,
│                             bookings.php, booking-action.php
├── api/check-availability.php  JSON endpoint used by AJAX
└── assets/                   css/style.css, js/main.js, images/
```

### Database (3 interconnected tables)
- `users` (user_id PK, name, email UNIQUE, contact_number, password hash, role)
- `rooms` (room_id PK, room_name, room_type, description, facilities, price_per_night, max_guests, image, room_status)
- `bookings` (booking_id PK, user_id FK to users, room_id FK to rooms, guests, check_in, check_out, total_amount, booking_status, special_requests, booking_date)

Foreign keys: deleting a user removes their bookings (`CASCADE`); a room with bookings cannot be deleted (`RESTRICT`), so it is deactivated instead. `CHECK` constraints enforce `check_out > check_in` and positive prices and capacities.

---

## 6. How the main rules work

- **Availability** is judged against **confirmed** bookings only. Two stays overlap when
  `existing.check_in < new.check_out AND existing.check_out > new.check_in`
  (a same-day check-out and check-in is allowed).
- **Overlap prevention:** when the admin confirms a booking, `admin/booking-action.php` runs a database transaction that locks the booking and room rows (`SELECT ... FOR UPDATE`), checks for an overlapping confirmed booking and only then confirms. Two admins acting at the same moment cannot double-book a room.
- **Price calculation:** nights and totals are calculated in JavaScript for display and **recalculated on the server** from the database price when the booking is saved.
- **Validation:** every form is validated in JavaScript (convenience) and again in PHP (authoritative).

## 7. Security measures

- Passwords stored with `password_hash()` and checked with `password_verify()`
- PDO prepared statements everywhere (no SQL built from user input)
- Output escaped with `htmlspecialchars()` (XSS protection)
- CSRF tokens on every POST form
- Session ID regenerated on login and logout, HttpOnly + SameSite cookies, 30-minute inactivity timeout
- Role-based access control (`require_login()`, `require_admin()`)
- Guests can only view and cancel **their own** bookings (queries are scoped by the session's user id)
- Login lock-out after repeated failed attempts; safe redirect handling
- Image uploads checked by real MIME type, size-limited and stored under random file names

## 8. Testing summary (manual)

| Test | Expected result |
|------|-----------------|
| Register with invalid data | Client and server messages shown, nothing saved |
| Login with wrong password | "Incorrect email or password" |
| Search availability for 21 to 23 Dec 2026 | Ocean View Deluxe is not listed (confirmed booking 20 to 24 Dec) |
| Book a room for dates overlapping a confirmed booking | Rejected with a clear message |
| Guest opens another guest's booking via cancel form | Not cancelled (scoped by user id) |
| Admin confirms two overlapping pending requests | The second confirmation is refused |
| Admin deletes a room that has bookings | Refused; room can be deactivated |
| Guest opens an `/admin/` URL | Redirected with "no permission" message |

---

## 9. Acknowledgements and external resources

- [Bootstrap 5.3](https://getbootstrap.com/) and [Bootstrap Icons](https://icons.getbootstrap.com/) (MIT licence), loaded from the jsDelivr CDN
- [OpenStreetMap](https://www.openstreetmap.org/) embedded map (data © OpenStreetMap contributors)
- Sample images: *add the source and licence of the photos you use here (e.g. Unsplash / Pexels).*
- All villa, guest and booking data is fictional.

## 10. Generative AI declaration

> Generative AI (Anthropic's Claude) was used to help generate and structure the source code of this project (PHP, SQL, JavaScript, CSS) and parts of this README. I reviewed, tested and adapted the code, and I can explain how each part works.
>
> *(Edit this statement so that it accurately describes what you personally wrote, changed and tested.)*
