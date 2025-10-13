<?php

namespace App\Events;

use App\Models\Deck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeckDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deck;

    public function __construct(Deck $deck)
    {
        $this->deck = $deck;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Public channel 'decks'
        return new Channel('decks');
        // Nếu muốn dùng private channel (tăng bảo mật, chỉ user sở hữu nhận event):
        // return new PrivateChannel('decks.' . $this->deck->user_id);
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'deck.deleted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->deck->id,
        ];
    }
}