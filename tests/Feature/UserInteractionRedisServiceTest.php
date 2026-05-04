<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\UserInteractionRedisService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UserInteractionRedisServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_records_login_metrics_in_redis(): void
    {
        Carbon::setTestNow('2026-05-04 10:15:00');

        $user = new User;
        $user->setRawAttributes([
            'id' => 42,
            'role_id' => 3,
        ]);
        $request = Request::create('/api/auth/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        Redis::shouldReceive('incr')->once()->with('metrics:logins:total');
        Redis::shouldReceive('hIncrBy')->once()->with('metrics:logins:daily:2026-05-04', 'total', 1);
        Redis::shouldReceive('hIncrBy')->once()->with('metrics:logins:daily:2026-05-04', 'role:reader', 1);
        Redis::shouldReceive('setEx')->once()->with('users:42:last_login_ip', 604800, '127.0.0.1');
        Redis::shouldReceive('setEx')->once()->with('users:42:last_login_at', 604800, '2026-05-04T10:15:00+00:00');

        app(UserInteractionRedisService::class)->recordLogin($user, $request);
    }

    public function test_records_book_interaction_metrics_in_redis(): void
    {
        Carbon::setTestNow('2026-05-04 10:15:00');

        $book = new Book;
        $book->setRawAttributes(['id' => 9]);
        $request = Request::create('/api/books/9', 'GET');

        Redis::shouldReceive('incr')->once()->with('metrics:books:detail_view:total');
        Redis::shouldReceive('incr')->once()->with('metrics:books:9:detail_view');
        Redis::shouldReceive('hIncrBy')->once()->with('metrics:books:detail_view:daily:2026-05-04', '9', 1);
        Redis::shouldReceive('zIncrBy')->once()->with('metrics:books:detail_view:top', 1, '9');

        app(UserInteractionRedisService::class)->recordBookInteraction($book, $request, 'detail_view');
    }
}
