<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-sandi-khusus-tes-demo';

    protected function enableDemo(?string $password = self::PASSWORD): void
    {
        config([
            'demo.enabled' => true,
            'demo.email' => 'demo@studycompanion.test',
            'demo.password' => $password,
        ]);
    }

    public function test_seeder_refuses_in_production(): void
    {
        $this->enableDemo();
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production');

        try {
            (new DemoUserSeeder)->run();
        } finally {
            $this->assertDatabaseMissing('users', ['email' => 'demo@studycompanion.test']);
        }
    }

    public function test_seeder_refuses_without_password(): void
    {
        $this->enableDemo(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_ACCOUNT_PASSWORD kosong');

        (new DemoUserSeeder)->run();
    }

    public function test_seeder_does_nothing_when_disabled(): void
    {
        config(['demo.enabled' => false, 'demo.password' => self::PASSWORD]);

        (new DemoUserSeeder)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_creates_verified_onboarded_user_and_updates_on_rerun(): void
    {
        $this->enableDemo();

        (new DemoUserSeeder)->run();
        (new DemoUserSeeder)->run();

        $this->assertSame(1, User::where('email', 'demo@studycompanion.test')->count());
        $user = User::where('email', 'demo@studycompanion.test')->first();
        $this->assertSame('Akun Demo', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->onboarded);
        $this->assertNotNull($user->start_date);
        $this->assertNotSame(self::PASSWORD, $user->password, 'Password must be stored hashed.');

        // Can log in and reach a page behind auth + verified + onboarded.
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_login_page_shows_demo_box_only_when_enabled(): void
    {
        $this->enableDemo();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Akun demo (hanya lingkungan lokal)')
            ->assertSee('demo@studycompanion.test')
            ->assertSee(self::PASSWORD)
            ->assertSee('Isi otomatis')
            ->assertSeeHtml('aria-labelledby="akun-demo-judul"');
    }

    public function test_login_page_has_no_trace_when_disabled(): void
    {
        config(['demo.enabled' => false, 'demo.password' => self::PASSWORD]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee(self::PASSWORD)
            ->assertDontSee('Akun demo')
            ->assertDontSee('demo@studycompanion.test')
            ->assertDontSee('Isi otomatis');
    }

    public function test_login_page_hides_demo_box_in_production(): void
    {
        $this->enableDemo();
        $this->app['env'] = 'production';

        $this->get('/login')
            ->assertOk()
            ->assertDontSee(self::PASSWORD)
            ->assertDontSee('Akun demo');
    }
}
