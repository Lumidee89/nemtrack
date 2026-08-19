<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ModuleSubscription extends Model {protected $guarded=[];protected function casts():array{return ['starts_at'=>'datetime','expires_at'=>'datetime'];}}
