<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter as FacadesRateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
class LoginRequest extends FormRequest
{
 
    public function authorize(): bool
    {
        return true;
    }

  
    public function rules(): array
    {
        return [
          'email' => ['required', 'string', 'email'],
          'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
  {
      $this->ensureIsNotRateLimited();

      if (! Auth::attempt(
          $this->only('email', 'password'),
          $this->boolean('remember')
      )) {
          FacadesRateLimiter::hit($this->throttleKey());

          throw ValidationException::withMessages([
              'email' => __('auth.failed'),
          ]);
      }

      FacadesRateLimiter::clear($this->throttleKey());
  }

  public function ensureIsNotRateLimited(): void
    {
        if (! FacadesRateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = FacadesRateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}