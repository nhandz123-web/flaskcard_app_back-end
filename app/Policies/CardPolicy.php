<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CardPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Card $card)
    {
        return $user->id === $card->user_id;
    }

    public function delete(User $user, Card $card)
    {
        return $user->id === $card->user_id;
    }
}