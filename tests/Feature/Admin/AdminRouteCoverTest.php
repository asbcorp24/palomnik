<?php

namespace Tests\Feature\Admin;

use App\Models\PilgrimageRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRouteCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_replace_and_remove_route_cover(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $create = $this->actingAs($admin)->post('/admin/modules/routes', [
            'title' => 'Маршрут с обложкой',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'is_group' => 0,
            'is_published' => 0,
            'cover_image' => $this->tinyPng('route-cover.png'),
        ]);

        $create->assertSessionHasNoErrors();
        $route = PilgrimageRoute::query()->firstOrFail();
        $create->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');
        $this->assertNotNull($route->cover_path);
        Storage::disk('public')->assertExists($route->cover_path);

        $oldPath = $route->cover_path;
        $this->actingAs($admin)
            ->get('/admin/modules/routes/'.$route->id.'/edit')
            ->assertOk()
            ->assertSee('Обложка маршрута')
            ->assertSee('Фото маршрута')
            ->assertSee('Удалить текущую обложку');

        $replace = $this->actingAs($admin)->put('/admin/modules/routes/'.$route->id, [
            'title' => $route->title,
            'slug' => $route->slug,
            'category' => $route->category,
            'difficulty' => $route->difficulty,
            'duration_days' => $route->duration_days,
            'is_group' => 0,
            'is_published' => 0,
            'cover_image' => $this->tinyPng('route-cover-new.png'),
        ]);

        $replace->assertSessionHasNoErrors();
        $replace->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');
        $route->refresh();
        $this->assertNotSame($oldPath, $route->cover_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($route->cover_path);

        $newPath = $route->cover_path;
        $remove = $this->actingAs($admin)->put('/admin/modules/routes/'.$route->id, [
            'title' => $route->title,
            'slug' => $route->slug,
            'category' => $route->category,
            'difficulty' => $route->difficulty,
            'duration_days' => $route->duration_days,
            'is_group' => 0,
            'is_published' => 0,
            'remove_cover' => 1,
        ]);

        $remove->assertSessionHasNoErrors();
        $remove->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');
        $route->refresh();
        $this->assertNull($route->cover_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Администратор маршрутов',
            'email' => 'route-cover@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function tinyPng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'route-cover-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
