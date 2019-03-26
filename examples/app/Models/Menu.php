<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $guarded = ['id'];

    protected $fillable = [
        'parent_id',
        'name',
        'link',
        'controller',
        'action',
        'icon',
        'show',
        'sort',
        'created_at',
        'updated_at',
    ];
    
    /**
     * 父级
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function parent()
    {
        return $this->hasOne(Menu::class, 'id', 'parent_id');
    }
}
