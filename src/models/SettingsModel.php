<?php

namespace wsydney76\versions\models;

use craft\base\Model;

class SettingsModel extends Model
{
    public $showInternal = 'never';
    public $allowPreviewForSourceEntries = 1;
    public $allowMultipleDrafts = 1;
    public $allowEditSource = 'always';
    public $allowSaveHUD = [];
    public $helpCaption = 'Advanced help for workflow';
    public $helpUrl = '';
    public $enablePermissions = 1;

}
