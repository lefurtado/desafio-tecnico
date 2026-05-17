<?php

namespace Tests\Feature;

use App\Jobs\ExportStudentsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExportLockTest extends TestCase
{
  use RefreshDatabase;

  public function test_lock_key_is_scoped_per_user(): void
  {
    $this->assertSame(
      'exports:students:user:1',
      ExportStudentsJob::lockKey(1)
    );
    $this->assertNotSame(
      ExportStudentsJob::lockKey(1),
      ExportStudentsJob::lockKey(2)
    );
  }

  public function test_failed_job_releases_lock_and_notifies_user(): void
  {
    $user = User::factory()->create();
    Cache::put(ExportStudentsJob::lockKey($user->id), true, 120);

    $job = new ExportStudentsJob($user->id);
    $job->failed(new \RuntimeException('boom'));

    $this->assertFalse(Cache::has(ExportStudentsJob::lockKey($user->id)));
    $this->assertDatabaseCount('notifications', 1);
  }

  public function test_concurrent_acquire_only_succeeds_once(): void
  {
    $key = ExportStudentsJob::lockKey(42);

    $this->assertTrue(Cache::add($key, true, 120));
    $this->assertFalse(Cache::add($key, true, 120)); // segundo clique perde
  }
}