<?php

namespace App\Providers;

use App\Services\AdminAccountServices;
use App\Services\ArtistAccountServices;
use App\Services\AuthenticationServices;
use App\Services\SubAdminAccountServices;
use App\Services\StaffAccountServices;
use App\Services\CustomerServiceAccountServices;
use App\Services\UserAccountServices;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthenticationServices::class, function () {
            return new AuthenticationServices([
                new AdminAccountServices(),
                new SubAdminAccountServices(),
                new ArtistAccountServices(),
                new StaffAccountServices(),
                new CustomerServiceAccountServices(),
                new UserAccountServices(),
            ]);
        });

        $this->app->register(\App\Providers\BroadcastServiceProvider::class);
    }

    public function boot(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        Relation::morphMap([
            'user'     => \App\Models\UserModel::class,
            'admin'    => \App\Models\AdminModel::class,
            'subadmin' => \App\Models\SubAdminModel::class,
            'artist'   => \App\Models\ArtistModel::class,
            'staff'    => \App\Models\StaffModel::class, // ← added
        ]);

        \App\Models\OrdersModel::observe(\App\Observers\SidebarBadgeObserver::class);
        \App\Models\ReturnRefundModel::observe(\App\Observers\SidebarBadgeObserver::class);
        \App\Models\Inquiry::observe(\App\Observers\SidebarBadgeObserver::class);
        \App\Models\Message::observe(\App\Observers\SidebarBadgeObserver::class);

        \Illuminate\Support\Facades\Gate::policy(\App\Models\Promotion::class, \App\Policies\PromotionPolicy::class);

        $caPath = 'D:\Download\cacert.pem';
        if (file_exists($caPath)) {
            stream_context_set_default([
                'ssl' => [
                    'cafile'           => $caPath,
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);
        }
    }
}