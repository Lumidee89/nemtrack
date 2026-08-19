<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionOrder extends Model {protected $guarded=[];protected function casts():array{return ['items'=>'array','paid_at'=>'datetime'];}public function user(){return $this->belongsTo(User::class);}public function organization(){return $this->belongsTo(Organization::class);}}
