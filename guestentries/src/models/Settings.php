<?php

namespace craft\guestentries\models;

use craft\base\Model;

class Settings extends Model
{
    public $entryVariable = 'entry';
    public $allowGuestSubmissions;
    public $defaultAuthors;
    public $enabledByDefault;
    public $validateEntry;



}