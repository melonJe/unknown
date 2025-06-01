<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomUser extends Model
{
    protected $table = 'room_users';
    public $timestamps = false; // joined_at은 자동처리하므로 Eloquent timestamps 불필요

    protected $primaryKey = null; // 복합키이므로 단일 PK 없음
    public $incrementing = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'pos_x',
        'pos_y',
        'dice',
        'joined_at'
    ];

    protected $casts = [
        'dice' => 'array',           // JSONB 자동 변환
        'joined_at' => 'datetime'
    ];

    // 관계 정의 (선택적)
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
