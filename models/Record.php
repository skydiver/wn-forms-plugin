<?php

namespace Martin\Forms\Models;

use Backend\Facades\Backend;
use Winter\Storm\Database\Model;

class Record extends Model
{
    use \Winter\Storm\Database\Traits\SoftDelete;

    public $table = 'martin_forms_records';

    protected $dates = ['deleted_at'];

    public $attachMany = [
        'files' => ['System\Models\File', 'public' => false]
    ];

    public function getFormDataArrAttribute()
    {
        return (array) json_decode($this->form_data);
    }

    public function filterGroups()
    {
        return Record::orderBy('group')->groupBy('group')->lists('group', 'group');
    }

    public function getGroupsOptions()
    {
        return $this->filterGroups();
    }

    public function filesList()
    {
        return $this->files->map(function ($file) {
            return Backend::url('martin/forms/records/download', [$this->id, $file->id]);
        })->implode(',');
    }
}
