<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Models\Room;
use Models\User;

class RoomTurn extends Model
{
    protected $table = 'room_turns';

    protected $primaryKey = 'room_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'current_turn_user_id',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    // Room 관계
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    // 현재 턴 유저
    public function currentTurnUser()
    {
        return $this->belongsTo(User::class, 'current_turn_user_id');
    }
}
