# 📚 Library Notification System (Laravel Backend)

## 🎯 Objective
Build a role-based notification system for a library website that supports:
- 👤 User (reader)
- ✍️ Author
- 🛠️ Admin

The system should handle in-app, real-time, and optional push notifications (Firebase-ready).

---

## 👥 Roles & Permissions

### 1. 👤 User (Website User)
- Receive notification when:
  - Start reading a book
  - Finish reading a book
  - New book from followed author
- Can:
  - View notifications
  - Mark as read

---

### 2. ✍️ Author
- Receive notification when:
  - Someone reads their book
  - Someone finishes their book
- Can:
  - View analytics notifications (engagement)

---

### 3. 🛠️ Admin
- Receive notification when:
  - New book is published
  - Suspicious activity
  - High traffic / system alerts
- Can:
  - Send system-wide notifications
  - Manage all notifications

---

## 🧩 Feature Overview

### Core Actions
| Action | Trigger | Target Role |
|------|--------|------------|
| Start reading | User opens book | User + Author |
| Finish reading | User completes book | User + Author |
| Publish book | Author creates book | Admin |
| System alert | Admin action | All users |

---

## 🗄️ Database Design

### 1. `reading_histories`
| Field | Type |
|------|------|
| id | bigint |
| user_id | FK |
| book_id | FK |
| started_at | timestamp |
| finished_at | timestamp |
| created_at | timestamp |

---

### 2. `notifications`
| Field | Type |
|------|------|
| id | bigint |
| user_id | FK |
| role | enum(user, author, admin) |
| title | string |
| message | text |
| type | string |
| is_read | boolean |
| data | json |
| created_at | timestamp |

---

## ⚙️ Business Logic Flow

### 📌 1. User Starts Reading
- Save reading history
- Create notification:
  - For User → "You started reading"
  - For Author → "Someone started your book"

---

### 📌 2. User Finishes Reading
- Update `finished_at`
- Create notification:
  - For User → "You completed this book 🎉"
  - For Author → "User completed your book"

---

### 📌 3. Author Publishes Book
- Notify Admin

---

### 📌 4. Admin Sends System Notification
- Broadcast to:
  - All Users OR
  - Specific Role

---

## 🔌 API Endpoints

### 👤 USER APIs

#### Start Reading
**POST** `/api/reading/start`

#### Finish Reading
**POST** `/api/reading/finish`

#### Get Notifications
**GET** `/api/user/notifications`

#### Mark as Read
**POST** `/api/user/notifications/{id}/read`

---

### ✍️ AUTHOR APIs

#### Get Notifications
**GET** `/api/author/notifications`

---

### 🛠️ ADMIN APIs

#### Get All Notifications
**GET** `/api/admin/notifications`

#### Send Notification
**POST** `/api/admin/notifications/send`

```json
{
  "target": "all | user | author",
  "title": "System Update",
  "message": "New features available"
}
```

---

## 🧠 Backend Logic (Service Example)

```php
class NotificationService
{
    public function notifyReadingStart($user, $book)
    {
        // Notify User
        Notification::create([
            'user_id' => $user->id,
            'role' => 'user',
            'title' => 'Reading Started',
            'message' => "You started {$book->title}",
        ]);

        // Notify Author
        Notification::create([
            'user_id' => $book->author_id,
            'role' => 'author',
            'title' => 'New Reader',
            'message' => "Someone started your book",
        ]);
    }
}
```

---

## ⚡ Real-Time (Optional)

Use:
- Laravel Events
- Broadcasting (Pusher / WebSocket)

---

## 🔔 Firebase (Optional - Recommended for Mobile)

Use Firebase if:
- Flutter mobile app
- Background notifications

---

## 🔐 Authentication

Use:
- Laravel Sanctum

Protect routes:
```
auth:sanctum
```

---

## 🧪 Testing Checklist

- [ ] User gets notification when reading
- [ ] Author gets notification when book read
- [ ] Admin gets notification when book published
- [ ] Role-based filtering works
- [ ] Mark as read works

---

## 📦 Integration Tips

- Use role-based API endpoints
- Show notification badge per role
- Use polling or real-time update

---

## 📈 Future Improvements

- Push notification (Firebase)
- Email notification
- Notification preferences
- Follow author feature

---

## ✅ Summary

This system supports:
- Multi-role notification system
- Clean API structure
- Scalable architecture
- Ready for mobile & real-time upgrade

---

If needed, I can generate:
- Full Role Middleware
- Policy & Authorization
- Event + Listener setup
- Firebase integration

