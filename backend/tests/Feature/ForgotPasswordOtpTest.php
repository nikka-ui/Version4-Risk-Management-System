<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('/forgot-password')
            ->assertSee('name="_token"', false);
    }

    public function test_forgot_password_form_includes_csrf_token(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('action="/forgot-password"', false);
    }

    public function test_otp_is_emailed_and_resets_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'username' => 'reporter',
            'email' => 'reporter@rms.local',
            'password' => 'old-pass-1',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $this->post('/forgot-password', ['username' => 'reporter'])
            ->assertRedirect('/forgot-password/reset');

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use ($user) {
            return $mail->hasTo($user->email) && strlen($mail->otp) === 6;
        });

        $otp = '';
        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->withSession(['password_reset_username' => 'reporter'])
            ->post('/forgot-password/reset', [
                'otp' => $otp,
                'password' => 'new-pass-1',
                'password_confirmation' => 'new-pass-1',
            ])
            ->assertRedirect('/login?flash=password_reset');

        $this->assertTrue(Hash::check('new-pass-1', $user->fresh()->password));
    }

    public function test_unknown_username_does_not_send_mail(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['username' => 'nobody'])
            ->assertRedirect('/forgot-password/reset');

        Mail::assertNothingSent();
    }
}
