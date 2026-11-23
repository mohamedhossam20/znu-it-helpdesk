<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Listeners\SendTicketNotifications;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::subscribe(SendTicketNotifications::class);

        Gate::before(fn (User $user) => $user->hasRole(UserRole::SuperAdmin->value) ? true : null);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('tickets', fn (Request $request) => Limit::perHour((int) config('helpdesk.tickets_per_hour'))
            ->by((string) $request->user()?->id)
            ->response(fn () => response()->json([
                'message' => __('You have sent many requests in a short time. Please wait a little or call IT directly.'),
            ], 429)));

        RateLimiter::for('ticket-writes', fn (Request $request) => Limit::perMinute(20)->by((string) $request->user()?->id));
    }
}
