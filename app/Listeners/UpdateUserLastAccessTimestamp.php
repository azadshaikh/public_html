<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class UpdateUserLastAccessTimestamp
{
    /**
     * Create the event listener.
     */
    public function __construct(
        /**
         * The request instance.
         */
        protected Request $request
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->last_access = now();
            $event->user->save();
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Get the throttle key for the given request.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate($this->request->ip());
    }
}
