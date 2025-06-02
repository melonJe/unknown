<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = ['nickname', 'room_id'];
}