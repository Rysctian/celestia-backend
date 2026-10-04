<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(bool $withSession = true): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->input('email'))->first();

        if (! $user) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => "Email doesn't exist!",
            ]);
        }

        if (! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($withSession) {
            Auth::guard('web')->login(
                $user,
                $this->boolean('remember')
            );
        }

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
