<?php

namespace wsydney76\versions\behaviors;

use Craft;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\records\Entry as EntryRecord;
use yii\base\Behavior;

class EntryBehavior extends Behavior
{
    public function getDrafts()
    {
        return Entry::find()
            ->site($this->owner->site)
            ->anyStatus()
            ->draftOf($this->owner->id)
            ->orderBy('id desc')
            ->all();
    }

    public function getRevisions()
    {
        return Entry::find()
            ->site($this->owner->site)
            ->anyStatus()
            ->revisionOf($this->owner->id)
            ->orderBy('num desc')
            ->all();
    }

    public function getEntriesForAllSites()
    {
        return Entry::find()
            ->site('*')
            ->anyStatus()
            ->id($this->owner->id)
            ->all();
    }

    public function getVersionCreated()
    {
        $entryRecord = EntryRecord::findOne($this->owner->id);
        if (!$entryRecord) {
            return null;
        }
        return DateTimeHelper::toDateTime($entryRecord->dateCreated);
    }

    public function canSave()
    {
        $entry = $this->owner;
        if ($entry->propagating || $entry->duplicateOf || $entry->resaving || ElementHelper::isDraftOrRevision($entry)) {
            return true;
        }

        if (!Craft::$app->user->can('ignoreVersionsRestrictions')) {
            $settings = Craft::$app->plugins->getPlugin('versions')->settings;
            if (isset($settings['allowEditSourceIfDraftsExist']) && !$settings['allowEditSourceIfDraftsExist']) {
                if (count($this->getDrafts())) {
                    return false;
                }
            }
        }

        return true;
    }
}
