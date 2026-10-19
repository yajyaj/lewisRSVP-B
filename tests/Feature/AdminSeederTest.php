<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.name' => 'Admin',
            'admin.username' => 'admin',
            'admin.email' => 'admin@localhost.test',
            'admin.password' => 'a-secure-seeder-password',
        ]);
    }

    public function test_database_seeder_creates_admin_once_with_a_hashed_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@localhost.test')->sole();
        $this->assertSame('admin', $admin->username);
        $this->assertTrue(Hash::check('a-secure-seeder-password', $admin->password));

        $initialPasswordHash = $admin->password;
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@localhost.test')->count());
        $this->assertSame($initialPasswordHash, $admin->fresh()->password);
    }

    public function test_seeding_preserves_existing_user_details_and_all_sanctum_token_rows(): void
    {
        $admin = User::factory()->create([
            'name' => 'Existing Admin',
            'username' => 'existing-admin',
            'email' => 'admin@localhost.test',
            'password' => 'an-existing-password',
        ]);
        $ordinaryUser = User::factory()->create();
        $adminTokenId = $this->createTokenRow($admin, 'admin-token');
        $userTokenId = $this->createTokenRow($ordinaryUser, 'user-token');
        $adminPasswordHash = $admin->password;
        $adminToken = DB::table('personal_access_tokens')->where('id', $adminTokenId)->first();
        $userToken = DB::table('personal_access_tokens')->where('id', $userTokenId)->first();
        config(['admin.password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@localhost.test')->count());
        $this->assertSame('Existing Admin', $admin->fresh()->name);
        $this->assertSame('existing-admin', $admin->fresh()->username);
        $this->assertSame($adminPasswordHash, $admin->fresh()->password);
        $this->assertEquals(
            $adminToken,
            DB::table('personal_access_tokens')->where('id', $adminTokenId)->first(),
        );
        $this->assertEquals(
            $userToken,
            DB::table('personal_access_tokens')->where('id', $userTokenId)->first(),
        );
        $this->assertSame(2, DB::table('personal_access_tokens')->count());
        $this->assertSame(1, User::query()->whereKey($ordinaryUser->id)->count());
    }

    private function createTokenRow(User $user, string $tokenName): int
    {
        return DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->id,
            'name' => $tokenName,
            'token' => hash('sha256', $tokenName),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
