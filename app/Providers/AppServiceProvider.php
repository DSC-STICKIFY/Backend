<?php

namespace App\Providers;

use App\Services\AdminAccountServices;
use App\Services\ArtistAccountServices;
use App\Services\AuthenticationServices;
use App\Services\SubAdminAccountServices;
use App\Services\UserAccountServices;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthenticationServices::class, function ($app) {
          return new AuthenticationServices([
            $app->make(AdminAccountServices::class),
            $app->make(SubAdminAccountServices::class),
            $app->make(ArtistAccountServices::class),
            $app->make(UserAccountServices::class),
        ]);
        });

        $this->app->register(\App\Providers\BroadcastServiceProvider::class);
    }

        public function boot(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        // ✅ I-add ang subadmin ug artist sa morphMap
        Relation::morphMap([
            'user'     => \App\Models\UserModel::class,
            'admin'    => \App\Models\AdminModel::class,
            'subadmin' => \App\Models\SubAdminModel::class,
            'artist'   => \App\Models\ArtistModel::class, // ✅ ADDED
        ]);

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