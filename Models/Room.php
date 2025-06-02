<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $primaryKey = 'room_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['room_id', 'board'];

    public $timestamps = false;

    protected $casts = [
        'board' => 'array',
    ];
}
