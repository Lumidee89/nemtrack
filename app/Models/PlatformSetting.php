<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlatformSetting extends Model { protected $guarded=[]; protected function casts(): array { return ['value'=>'encrypted']; } public static function valueFor(string $key): ?string { return static::where('key',$key)->first()?->value; } }
