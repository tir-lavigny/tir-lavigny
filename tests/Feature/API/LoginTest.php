<?php

namespace Tests\Feature\API;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cannot_login_without_required_fields(): void
    {
        $response = $this->postJson(route('api.login'));

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        $user = AuthTestHelper::mockUser();

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'incorrect',
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Wrong credentials.',
        ]);

        AuthTestHelper::clearUser($user);
    }

    public function test_cannot_login_with_wrong_email(): void
    {
        $response = $this->postJson(route('api.login'), [
            'email' => 'unexists@mail.example',
            'password' => 'incorrect',
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Wrong credentials.',
        ]);
    }

    public function test_can_login(): void
    {
        $user = AuthTestHelper::mockUser();

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $response->assertCookieNotExpired('refreshToken');

        $response->assertJsonStructure(AuthTestHelper::$loginSuccessBody);

        $accessToken = $response->decodeResponseJson()['accessToken'];

        $this->assertTrue(AuthTestHelper::verifyAccessToken($accessToken));

        AuthTestHelper::clearUser($user);
    }

    public function test_cannot_login_without_register_access(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'This account cannot access the register.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_cashier_manager_can_login(): void
    {
        $user = AuthTestHelper::mockUser();
        $user->syncRoles('cashier manager');

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        AuthTestHelper::clearUser($user);
    }

    public function test_accountant_can_login(): void
    {
        $user = AuthTestHelper::mockUser();
        $user->syncRoles('accountant');

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        AuthTestHelper::clearUser($user);
    }

    public function test_can_refresh_token(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        $moveTime = config('sanctum.expiration') + 5;

        // Manually make access token expired
        $this->travel($moveTime)->minutes();
        $this->assertFalse(AuthTestHelper::verifyAccessToken($tokens['accessToken']));

        $response = $this
            ->withUnencryptedCookie('refreshToken', $tokens['refreshToken'])
            ->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$tokens['accessToken'])
            ->postJson(route('api.refresh'));

        $response->assertStatus(200);

        $response->assertJsonStructure(AuthTestHelper::$refreshSuccessBody);

        $accessToken = $response->decodeResponseJson()['accessToken'];

        $this->assertTrue(AuthTestHelper::verifyAccessToken($accessToken));

        AuthTestHelper::clearUser($user);
    }

    public function test_can_refresh_token_after_login(): void
    {
        $user = AuthTestHelper::mockUser();

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $response->assertCookieNotExpired('refreshToken');

        $refreshToken = $response->getCookie('refreshToken', false)->getValue();

        $accessToken = $response->decodeResponseJson()['accessToken'];

        // Manually make access token expired
        $this->travel(config('sanctum.expiration') + 5)->minutes();
        $this->assertFalse(AuthTestHelper::verifyAccessToken($accessToken));

        $response = $this
            ->withCookie('refreshToken', $refreshToken)
            ->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$accessToken)
            ->postJson(route('api.refresh'));

        $response->assertStatus(200);

        $response->assertJsonStructure(AuthTestHelper::$refreshSuccessBody);

        $accessToken = $response->decodeResponseJson()['accessToken'];

        $this->assertTrue(AuthTestHelper::verifyAccessToken($accessToken));

        AuthTestHelper::clearUser($user);
    }

    public function test_cannot_refresh_token_without_register_access(): void
    {
        $user = AuthTestHelper::mockUser();
        $tokens = AuthTestHelper::generateTokens($user);
        $user->removeRole('cashier');

        $response = $this
            ->withUnencryptedCookie('refreshToken', $tokens['refreshToken'])
            ->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$tokens['accessToken'])
            ->postJson(route('api.refresh'));

        $response->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        AuthTestHelper::clearUser($user);
    }

    public function test_can_logout(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        $response = $this
            ->withUnencryptedCookie('refreshToken', $tokens['refreshToken'])
            ->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$tokens['accessToken'])
            ->postJson(route('api.logout'));

        $response->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        AuthTestHelper::clearUser($user);
    }

    public function test_logout_revokes_all_tokens(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->postJson(route('api.logout'), [], [
            'Authorization' => 'Bearer '.$tokens['accessToken'],
            'Cookie' => 'refreshToken='.$tokens['refreshToken'],
        ])->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        AuthTestHelper::clearUser($user);
    }

    public function test_logout_clears_refresh_token_cookie(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        $response = $this->postJson(route('api.logout'), [], [
            'Authorization' => 'Bearer '.$tokens['accessToken'],
            'Cookie' => 'refreshToken='.$tokens['refreshToken'],
        ]);

        $response->assertCookieExpired('refreshToken');

        AuthTestHelper::clearUser($user);
    }

    public function test_access_token_expiration(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        // Manually make access token expired
        $this->travel(config('sanctum.expiration') + 10)->minutes();

        $response = $this->withHeader('Authorization', 'Bearer '.$tokens['accessToken'])
            ->getJson(route('test'));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        AuthTestHelper::clearUser($user);
    }

    public function test_refresh_token_expiration(): void
    {
        $user = AuthTestHelper::mockUser();

        $tokens = AuthTestHelper::generateTokens($user);

        // Manually make access token expired
        $this->travel(config('sanctum.rt_expiration') + 5)->minutes();

        $response = $this
            ->withCredentials()
            ->withUnencryptedCookie('refreshToken', $tokens['refreshToken'])
            ->withHeader('Authorization', 'Bearer '.$tokens['accessToken'])
            ->postJson(route('api.refresh'));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        AuthTestHelper::clearUser($user);
    }
}
