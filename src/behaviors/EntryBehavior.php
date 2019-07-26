<?php

namespace wsydney76\versions\behaviors;

use Craft;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\records\Entry as EntryRecord;
use wsydney76\versions\Versions;
use yii\base\Behavior;

class EntryBehavior extends Behavior
{
    public function getDrafts()
    {
        $entry = $this->owner;
        if (ElementHelper::isDraftOrRevision($entry) || !$entry->id) {
            return [];
        }
        return Entry::find()
            ->site($entry->site)
            ->anyStatus()
            ->draftOf($entry->id)
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

        if (Craft::$app->request->isConsoleRequest) {
            return true;
        }

        if (Craft::$app->user->can('ignoreVersionsRestrictions')) {
            return true;
        }
        /** @var Entry $entry */
        $entry = $this->owner;
        $settings = Versions::$plugin->settings;

        $p = Craft::$app->request->getParam('p');
        if ($p == 'admin/actions/elements/save-element' && in_array($entry->section->handle, $settings['allowSaveHUD'])) {
            return true;
        }

        if ((!$entry->id) || $entry->propagating || $entry->duplicateOf || $entry->resaving || ElementHelper::isDraftOrRevision($entry)) {
            return true;
        }

        if (isset($settings['allowEditSource'])) {
            if ($settings['allowEditSource'] == 'never') {
                $entry->addError('title', 'Saving the current entry is not allowed. Use a draft for updating the entry.');
                return false;
            }
            if ($settings['allowEditSource'] == 'nodrafts') {
                if (count($this->getDrafts())) {
                    $entry->addError('title', 'Saving the current entry is not allowed. Use existing draft for updating the entry.');
                    return false;
                }
            }
        }

        return true;
    }

    public function isDraftEditable()
    {

        /** @var Entry $entry */
        $entry = $this->owner;
        if ($entry->isEditable) {
            return true;
        }
        $uid = $entry->section->uid;
        $user = Craft::$app->user->identity;

        if ($user->can("editentries:{$uid}")) {
            return true;
        }

        if ($entry->authorId == $user->id) {
            if ($user->can("editpeerentries:{$uid}")) {
                return true;
            }
        } else {
            if ($user->can("editpeerentrydrafts:{$uid}")) {
                return true;
            }
        }
        return false;
    }
}
