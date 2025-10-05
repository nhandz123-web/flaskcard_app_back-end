<?php

namespace App\Providers;

use App\Models\Deck;
use App\Models\Card;
use App\Policies\DeckPolicy;
use App\Policies\CardPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Deck::class => DeckPolicy::class,
        Card::class => CardPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}