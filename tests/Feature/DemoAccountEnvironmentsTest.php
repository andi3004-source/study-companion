<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** The demo account works on local and staging, never on production. */
class DemoAccountEnvironmentsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-sandi-khusus-tes-demo';

    private function enableDemo(): void
    {
        config(['demo.enabled' => true, 'demo.email' => 'demo@studycompanion.test', 'demo.password' => self::PASSWORD]);
    }

    /** @return array<string, array{string}> */
    public static function nonProduction(): array
    {
        return ['local' => ['local'], 'staging' => ['staging']];
    }

    #[DataProvider('nonProduction')]
    public function test_seeder_runs_outside_production(string $env): void
    {
        $this->enableDemo();
        $this->app['env'] = $env;

        (new DemoUserSeeder)->run();

        $user = User::where('email', 'demo@studycompanion.test')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
    }

    #[DataProvider('nonProduction')]
    public function test_login_box_shows_outside_production(string $env): void
    {
        $this->enableDemo();
        $this->app['env'] = $env;

        $this->get('/login')->assertOk()->assertSee('Akun demo (lokal dan staging, bukan production)')->assertSee(self::PASSWORD);
    }

    public function test_production_still_refuses_and_hides_the_box(): void
    {
        $this->enableDemo();
        $this->app['env'] = 'production';

        $this->get('/login')->assertOk()->assertDontSee('Akun demo')->assertDontSee(self::PASSWORD);

        $this->expectException(RuntimeException::class);
        (new DemoUserSeeder)->run();
    }

    public function test_staging_box_needs_both_the_flag_and_a_password(): void
    {
        $this->app['env'] = 'staging';

        config(['demo.enabled' => true, 'demo.password' => '']);
        $this->get('/login')->assertDontSee('Akun demo');

        config(['demo.enabled' => false, 'demo.password' => self::PASSWORD]);
        $this->get('/login')->assertDontSee('Akun demo')->assertDontSee(self::PASSWORD);
    }
}
