<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\HealthReminder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AuthAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Registration checks that the email domain can receive mail. The lookups are
        // faked so the rest of this file does not depend on the network.
        Validator::fakeDnsLookups();
    }

    public function test_registration_creates_a_profile_and_starts_onboarding(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'username' => 'new_user',
            'email' => 'new@example.com',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonStructure(['token']);

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertSame('female', $user->sex);
        $this->assertSame('new_user', $user->username);
        $this->assertNull($user->onboarding_completed_at);
        $this->assertNotNull($user->consented_at);
    }

    public function test_registration_sends_a_welcome_notification(): void
    {
        $this->postJson('/api/v1/register', [
            'first_name' => 'Wendy',
            'last_name' => 'Wilson',
            'username' => 'wendy_w',
            'email' => 'wendy@example.com',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ])->assertCreated();

        $user = User::where('email', 'wendy@example.com')->firstOrFail();

        $welcome = $user->notifications()->first();
        $this->assertNotNull($welcome);
        $this->assertSame('Welcome to MediByte', $welcome->data['title']);
        $this->assertSame('welcome', $welcome->data['kind']);
        $this->assertSame('/profile-setup', $welcome->data['url']);
    }

    public function test_registration_title_cases_names_automatically(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'first_name' => 'jUAN',
            'last_name' => 'dela  CRUZ',
            'username' => 'title_case',
            'email' => 'titlecase@example.com',
            'password' => 'password123',
            'sex' => 'male',
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.first_name', 'Juan')
            ->assertJsonPath('user.last_name', 'Dela Cruz')
            ->assertJsonPath('user.name', 'Juan Dela Cruz');
    }

    public function test_registration_rejects_names_with_numbers_symbols_or_emoji(): void
    {
        $this->postJson('/api/v1/register', [
            'first_name' => 'Juan123',
            'last_name' => 'Cruz! 😊',
            'username' => 'bad_names',
            'email' => 'badnames@example.com',
            'password' => 'password123',
            'sex' => 'male',
            'consent' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);

        $this->assertDatabaseMissing('users', ['email' => 'badnames@example.com']);
    }

    public function test_profile_updates_title_case_names_and_reject_non_letters(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile', [
            'first_name' => 'maria',
            'last_name' => 'santos',
        ])->assertStatus(201)
            ->assertJsonPath('first_name', 'Maria')
            ->assertJsonPath('last_name', 'Santos')
            ->assertJsonPath('name', 'Maria Santos');

        $this->postJson('/api/v1/profile', [
            'first_name' => 'M4ria',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('first_name');
    }

    public function test_female_user_must_choose_a_cycle_status_before_finishing_onboarding(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'female'])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/complete')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period_status');
    }

    public function test_female_user_can_finish_onboarding_without_enabling_the_cycle_tracker(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'prefer_not_to_track',
        ])->save();
        $profile = $user;

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/complete')->assertOk();

        $profile->refresh();
        $this->assertNotNull($profile->onboarding_completed_at);
        $this->assertFalse($profile->show_period_tracker);
    }

    public function test_male_user_can_finish_onboarding_without_a_cycle_status(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        $profile = $user;

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/complete')->assertOk();

        $profile->refresh();
        $this->assertNotNull($profile->onboarding_completed_at);
        $this->assertNull($profile->period_status);
        $this->assertFalse($profile->show_period_tracker);
    }

    public function test_cycle_tracker_is_only_visible_to_female_users_with_an_active_cycle(): void
    {
        $active = new User(['sex' => 'female', 'period_status' => 'active']);
        $notYet = new User(['sex' => 'female', 'period_status' => 'not_yet']);
        $menopause = new User(['sex' => 'female', 'period_status' => 'menopause']);
        $preferNot = new User(['sex' => 'female', 'period_status' => 'prefer_not_to_track']);
        $male = new User(['sex' => 'male', 'period_status' => 'active']);

        $this->assertTrue($active->show_period_tracker);
        $this->assertFalse($notYet->show_period_tracker);
        $this->assertFalse($menopause->show_period_tracker);
        $this->assertFalse($preferNot->show_period_tracker);
        $this->assertFalse($male->show_period_tracker);
    }

    public function test_switching_to_male_clears_the_cycle_status(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'active',
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile', ['sex' => 'male'])->assertCreated();

        $user->refresh();
        $this->assertSame('male', $user->sex);
        $this->assertNull($user->period_status);
        $this->assertFalse($user->show_period_tracker);
    }

    public function test_period_tracker_endpoint_rejects_inactive_profiles(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'menopause',
        ])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/period-predict')->assertForbidden();
        $this->getJson('/api/v1/period-logs')->assertForbidden();
    }

    public function test_period_page_is_gated_for_inactive_profiles(): void
    {
        $male = User::factory()->create(['sex' => 'male', 'period_status' => null]);
        $this->actingAs($male)->get('/period')->assertForbidden();

        $female = User::factory()->create(['sex' => 'female', 'period_status' => 'active']);
        $this->actingAs($female)->get('/period')->assertOk();
    }

    public function test_a_user_can_update_their_profile_identity(): void
    {
        $user = User::factory()->create([
            'username' => 'old_username',
            'sex' => 'male',
            'period_status' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Person',
            'username' => 'Updated_Name',
            'email' => 'updated@example.com',
        ])->assertCreated()
            ->assertJsonPath('name', 'Updated Person')
            ->assertJsonPath('username', 'updated_name')
            ->assertJsonPath('email', 'updated@example.com');

        $user->refresh();
        $this->assertSame('Updated Person', $user->name);
        $this->assertSame('updated_name', $user->username);
        $this->assertSame('updated@example.com', $user->email);

        // An edit that leaves sex out must keep the answer given at sign-up.
        $this->assertSame('male', $user->sex);
    }

    public function test_registration_rejects_invalid_and_duplicate_usernames(): void
    {
        User::factory()->create(['username' => 'taken_name']);

        $base = [
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ];

        $this->postJson('/api/v1/register', array_merge($base, [
            'username' => 'bad-name',
            'email' => 'bad@example.com',
        ]))->assertStatus(422)->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/register', array_merge($base, [
            'username' => 'TAKEN_NAME',
            'email' => 'taken@example.com',
        ]))->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_registration_rejects_an_email_domain_that_cannot_receive_mail(): void
    {
        Validator::fakeDnsLookups(false);

        $this->postJson('/api/v1/register', [
            'first_name' => 'Ghost',
            'last_name' => 'Mailbox',
            'username' => 'ghost_mailbox',
            'email' => 'ghost@mail.example',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Enter an email at a domain that can receive mail. Check the spelling, especially the part after the @.');

        $this->assertDatabaseMissing('users', ['email' => 'ghost@mail.example']);
    }

    public function test_a_user_can_log_in_with_their_username(): void
    {
        $user = User::factory()->create(['username' => 'unique_login']);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withHeader('Referer', 'http://localhost/login');

        $this->postJson('/api/v1/login', [
            'email' => 'unique_login',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email', $user->email);
    }

    public function test_logging_in_again_clears_the_welcome_notification(): void
    {
        $user = User::factory()->create(['username' => 'returning_user']);

        $user->notify(new HealthReminder([
            'dedupe_key' => 'welcome',
            'kind' => 'welcome',
            'title' => 'Welcome to MediByte',
            'body' => 'Your account is ready.',
            'url' => '/profile-setup',
        ]));

        $this->assertSame(1, $user->notifications()->count());

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withHeader('Referer', 'http://localhost/login');

        $this->postJson('/api/v1/login', [
            'email' => 'returning_user',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_a_password_reset_link_can_be_requested_and_used(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = null;

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, we have sent a password reset link.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'newpassword123',
        ])->assertOk()->assertJsonPath('user.email', $user->email);
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, we have sent a password reset link.');
    }

    public function test_forgot_password_says_so_when_the_mail_server_fails(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->andThrow(new TransportException('Connection refused'));

        $manager = Mockery::mock(Factory::class);
        $manager->shouldReceive('mailer')->andReturn($mailer);

        Mail::swap($manager);

        $this->postJson('/api/v1/forgot-password', ['email' => 'reset@example.com'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'We could not send the reset email just now. Please try again in a moment.');
    }

    public function test_web_login_creates_a_session(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'onboarding_completed_at' => now(),
        ])->save();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withHeader('Referer', 'http://localhost/login');

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email', $user->email);

        $this->assertAuthenticated();
        $this->get('/dashboard')->assertOk();
    }

    public function test_public_pages_render_and_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Log in to MediByte');
        $this->get('/register')->assertOk()->assertSee('Create your MediByte account');
        $this->get('/privacy')->assertOk()->assertSee('Privacy notice');

        $this->get('/settings')->assertRedirect('/login');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Profile and goals');

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Settings')
            ->assertDontSee('Profile and goals');
    }
}
