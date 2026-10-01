<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->forEmployee(Employee::factory()->create())->create();
    }

    private function enableLocalRoutes(): void
    {
        $this->app['env'] = 'local';
        Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
    }

    public function test_session_login_works_without_an_origin_and_rotates_the_session(): void
    {
        $user = $this->user();
        $this->withSession(['existing' => 'value']);
        $oldId = session()->getId();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($oldId, session()->getId());
    }

    public function test_session_login_requires_csrf_outside_tests(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertStatus(419);
    }

    public function test_dev_tokens_are_stateless_hashed_and_expire_after_one_hour(): void
    {
        $this->enableLocalRoutes();
        $this->travelTo(now()->startOfSecond());
        $user = $this->user();

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/dev/token', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'expires_at']);

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);
        $this->assertNotSame($response->json('token'), $token->token);
        $this->assertTrue($token->expires_at->equalTo(now()->addHour()));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertGuest('web');
        $response->assertCookieMissing(config('session.cookie'));

        $this->withToken($response->json('token'))->getJson('/api/employees')->assertOk();
        Auth::forgetGuards();
        $this->travel(61)->minutes();
        $this->getJson('/api/employees')->assertUnauthorized();
    }

    public function test_five_failed_token_attempts_block_even_correct_credentials(): void
    {
        $this->enableLocalRoutes();
        $user = $this->user();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/dev/token', ['email' => $user->email, 'password' => 'wrong'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/dev/token', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->travel(61)->seconds();
        $this->postJson('/api/dev/token', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_session_and_token_login_share_the_attempt_limit(): void
    {
        $this->enableLocalRoutes();
        $this->app['env'] = 'testing';
        $user = $this->user();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->app['env'] = 'local';
        $this->postJson('/api/dev/token', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_successful_token_login_clears_previous_failures(): void
    {
        $this->enableLocalRoutes();
        $user = $this->user();
        $credentials = ['email' => $user->email, 'password' => 'password'];

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/dev/token', [...$credentials, 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/dev/token', $credentials)->assertOk();
        $this->postJson('/api/dev/token', [...$credentials, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/dev/token', $credentials)->assertOk();
    }

    public function test_logout_revokes_only_the_current_bearer_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        Auth::forgetGuards();
        $this->getJson('/api/employees')->assertUnauthorized();
    }

    public function test_session_logout_invalidates_session_without_revoking_other_tokens(): void
    {
        $user = $this->user();
        $user->createToken('other-device');
        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $oldId = session()->getId();
        $oldCsrf = session()->token();

        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Auth::forgetGuards();
        $this->getJson('/api/employees')->assertUnauthorized();
    }

    public function test_guests_cannot_access_employees_or_logout(): void
    {
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_first_party_logout_still_requires_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs($this->user(), 'web')->withHeader('Origin', 'http://localhost')
            ->postJson('/api/logout')->assertStatus(419);
    }

    public function test_dev_token_route_is_unavailable_outside_local(): void
    {
        $this->app['env'] = 'production';
        $this->postJson('/api/dev/token')->assertNotFound();
    }

    public function test_cached_local_token_route_still_rejects_production_requests(): void
    {
        $this->enableLocalRoutes();
        $this->app['env'] = 'production';
        $this->postJson('/api/dev/token', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertNotFound();
    }
}
