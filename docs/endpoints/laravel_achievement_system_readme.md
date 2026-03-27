# 📚 Laravel Achievement System (Library Website)

This guide explains how to build a **User Achievement System** for a library website where users earn badges based on reading activity.

---

# 🎯 Feature Overview

Users unlock achievements such as:
- 🔥 Streak (reading daily)
- 🏆 Elite (reading many books)
- ⚡ Fast (quick completion)
- 📖 Scholar (reading categories)
- ⭐ Critic (writing reviews)

---

# 🧱 Database Structure

## 1. achievements

| Field | Type |
|------|------|
| id | bigint |
| code | string (unique) |
| title | string |
| description | text |
| icon | string |
| condition_type | enum |
| condition_value | integer |
| created_at | timestamp |

---

## 2. user_achievements

| Field | Type |
|------|------|
| id | bigint |
| user_id | bigint |
| achievement_id | bigint |
| unlocked_at | timestamp |

---

## 3. user_reading_logs

| Field | Type |
|------|------|
| id | bigint |
| user_id | bigint |
| book_id | bigint |
| pages_read | integer |
| read_date | date |
| created_at | timestamp |

---

# ⚙️ Installation Steps

```bash
php artisan make:model Achievement -m
php artisan make:model UserAchievement -m
php artisan make:model UserReadingLog -m
```

Run migration:

```bash
php artisan migrate
```

---

# 🧠 Business Logic

## Condition Types

- COUNT → number of books
- STREAK → consecutive days
- SPEED → completion time
- CATEGORY → unique categories

---

# 🧩 Service Class

Create service:

```bash
php artisan make:service AchievementService
```

## Example Logic

```php
public function checkAchievements($userId)
{
    $achievements = Achievement::all();

    foreach ($achievements as $achievement) {
        $exists = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($exists) continue;

        switch ($achievement->condition_type) {
            case 'COUNT':
                $count = UserReadingLog::where('user_id', $userId)
                    ->distinct('book_id')->count('book_id');

                if ($count >= $achievement->condition_value) {
                    $this->unlock($userId, $achievement->id);
                }
                break;

            case 'STREAK':
                // simple streak logic
                $dates = UserReadingLog::where('user_id', $userId)
                    ->orderBy('read_date', 'desc')
                    ->pluck('read_date');

                $streak = $this->calculateStreak($dates);

                if ($streak >= $achievement->condition_value) {
                    $this->unlock($userId, $achievement->id);
                }
                break;
        }
    }
}

private function unlock($userId, $achievementId)
{
    UserAchievement::create([
        'user_id' => $userId,
        'achievement_id' => $achievementId,
        'unlocked_at' => now()
    ]);
}
```

---

# 🌐 API Endpoints

## 1. Get All Achievements

```
GET /api/achievements
```

---

## 2. Get User Achievements

```
GET /api/users/{id}/achievements
```

Response:

```json
[
  {
    "title": "STREAK",
    "unlocked": true
  }
]
```

---

## 3. Add Reading Log

```
POST /api/reading-logs
```

Body:

```json
{
  "user_id": 1,
  "book_id": 10,
  "pages_read": 20
}
```

---

## 4. Check Achievements

```
POST /api/users/{id}/check-achievements
```

---

# 🔁 Controller Example

```php
public function store(Request $request)
{
    $log = UserReadingLog::create($request->all());

    app(AchievementService::class)
        ->checkAchievements($request->user_id);

    return response()->json($log);
}
```

---

# 🎨 Frontend Integration

- Call `/api/users/{id}/achievements`
- Map to UI cards
- Locked → gray
- Unlocked → colored

---

# 🚀 Best Practices

- Cache achievements if needed
- Use queues for heavy calculations
- Add notifications when unlocked
- Prevent duplicate unlocks

---

# ✅ Summary

This system provides:
- Scalable achievement logic
- Clean API for frontend
- Easy extension for new badges

---

💡 Ready for production and easy integration with your UI.

