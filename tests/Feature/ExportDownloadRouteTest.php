<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExportDownloadRouteTest extends TestCase
{
  use RefreshDatabase;

  public function test_unauthenticated_user_cannot_access_download(): void
  {
    $response = $this->get('/export/download?path=exports/foo.xlsx');

    $this->assertNotEquals(200, $response->status(), 'Usuário não autenticado não pode receber o arquivo.');
  }

  public function test_request_without_valid_signature_is_unauthorized(): void
  {
    $user = User::factory()->create();

    $this->actingAs($user)
      ->get('/export/download?path=exports/foo.xlsx')
      ->assertStatus(401);
  }

  public function test_path_traversal_attempt_is_blocked(): void
  {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute(
      'export.download',
      now()->addMinutes(5),
      ['path' => '../../.env']
    );

    $this->actingAs($user)->get($url)->assertStatus(404);
  }

  public function test_path_outside_exports_folder_is_blocked(): void
  {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute(
      'export.download',
      now()->addMinutes(5),
      ['path' => 'logs/laravel.log']
    );

    $this->actingAs($user)->get($url)->assertStatus(404);
  }

  public function test_valid_signed_url_downloads_existing_file(): void
  {
    $user = User::factory()->create();
    $dir = storage_path('app/exports');
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    $filename = 'exports/test_' . uniqid() . '.xlsx';
    file_put_contents(storage_path('app/' . $filename), 'fake-xlsx-content');

    $url = URL::temporarySignedRoute(
      'export.download',
      now()->addMinutes(5),
      ['path' => $filename]
    );

    $this->actingAs($user)->get($url)
      ->assertOk()
      ->assertDownload('alunos.xlsx');

    @unlink(storage_path('app/' . $filename));
  }
}