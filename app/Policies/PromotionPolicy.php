<?php
namespace App\Policies;

use App\Models\UserModel;
use App\Models\Promotion;

class PromotionPolicy
{
    public function view(UserModel $user, Promotion $promotion)
    {
        return true;
    }

    public function create(UserModel $user)
    {
        return in_array($user->role, ['admin', 'subadmin']);
    }

    public function update(UserModel $user, Promotion $promotion)
    {
        return in_array($user->role, ['admin', 'subadmin']) &&
               in_array($promotion->status, ['draft', 'pending_review']);
    }

    public function delete(UserModel $user, Promotion $promotion)
    {
        return in_array($user->role, ['admin', 'subadmin']) &&
               $promotion->status !== 'sent';
    }

    public function send(UserModel $user, Promotion $promotion)
    {
        return $user->role === 'customer_service' &&
               $promotion->status === 'ready_to_send';
    }

    public function review(UserModel $user, Promotion $promotion)
    {
        return $user->role === 'customer_service' &&
               $promotion->status === 'pending_review';
    }
}
