<?php

namespace App\Http\Requests;

use Yeosz\LaravelCurd\ApiRequest;

class CreateMenuRequest extends ApiRequest
{
    public $only = [
        'parent_id',
        'name',
        'link',
        'controller',
        'action',
        'icon',
        'show',
        'sort',
    ];

    protected $correct = [
        ['parent_id', 'intval'],
        ['sort', 'intval'],
        ['show', 'intval'],
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => 'required',
            'sort' => 'integer',
        ];
        return $rules;
    }
}
