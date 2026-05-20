<?php

namespace App\Observers;

use App\Events\AdminSidebarUpdated;

class SidebarBadgeObserver
{
    public function created($model)
    {
        event(new AdminSidebarUpdated());
    }

    public function updated($model)
    {
        event(new AdminSidebarUpdated());
    }

    public function deleted($model)
    {
        event(new AdminSidebarUpdated());
    }
}
