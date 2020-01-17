<?php

namespace wsydney76\versions\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\records\Entry as EntryRecord;
use wsydney76\versions\Versions;
use function Arrayy\create;
use function get_class;
use function in_array;
use function strpos;

class VersionsService extends Component
{
    /**
     * @return bool
     */
    public function showInternal()
    {

        $showInternalSetting = Versions::$plugin->getSettings()->showInternal;

        return $showInternalSetting == 'always'
            || ($showInternalSetting == 'admin' && Craft::$app->user->getIdentity()->admin)
            || ($showInternalSetting == 'dev' && Craft::$app->config->general->devMode);
    }

    public function getEntryById(int $id, string $site)
    {
        return Entry::find()
            ->site($site)
            ->anyStatus()
            ->id($id)
            ->one();
    }

    /**
     * @param string $site
     * @param string $preferSite
     * @return array|\craft\base\ElementInterface[]|Entry[]
     */
    public function getAllDrafts($site, string $preferSite)
    {

        $drafts = Entry::find()
            ->site($site)
            ->unique()
            ->preferSites([$preferSite])
            ->anyStatus()
            ->drafts(1)
            ->orderBy('dateCreated desc')
            ->all();
        foreach ($drafts as $draft) {
            $draft->scenario = Entry::SCENARIO_LIVE;
        }
        return $drafts;
    }

    public function getDraftsCount()
    {
        return Entry::find()
            ->site('*')
            ->unique()
            ->anyStatus()
            ->drafts(true)
            ->andWhere(['not like', 'title', '__temp'])
            ->count();
    }

    public function getAllowedSitesForUser(User $user)
    {
        $allowedSites = [];
        $sites = Craft::$app->sites->getAllSites();
        foreach ($sites as $site) {
            if ($user->can("editsite:{$site->uid}")) {
                $allowedSites[] = $site->handle;
            }
        }
        return $allowedSites;
    }

    public function getHelpInfo($id, $site, $draftId, $revisionId)
    {
        $info = [];
        $sourceEntry = Entry::find()->id($id)->site($site)->anyStatus()->one();
        $info['sourceEntry'] = $sourceEntry;
        $info['drafts'] = $sourceEntry ? $sourceEntry->drafts : [];
        if ($draftId) {
            $info['entry'] = Entry::find()->draftId($draftId)->site($site)->anyStatus()->one();
            $info['siteEntries'] = Entry::find()->draftId($draftId)->site('*')->anyStatus()->all();
            $info['is'] = 'draft';
        } elseif ($revisionId) {
            $info['entry'] = Entry::find()->revisionId($revisionId)->site($site)->anyStatus()->one();
            $info['siteEntries'] = Entry::find()->revisionId($revisionId)->site('*')->anyStatus()->all();
            $info['is'] = 'revision';
        } else {
            $info['entry'] = $sourceEntry;
            $info['siteEntries'] = Entry::find()->id($id)->site('*')->anyStatus()->all();
            $info['is'] = 'source';
        }

        foreach ($info['siteEntries'] as $siteEntry) {
            $siteEntry->scenario = Entry::SCENARIO_LIVE;
            $siteEntry->validate();
        }

        $info['settings'] = Versions::$plugin->settings;

        return $info;
    }

    public function compare($draftId, $siteHandle)
    {
        $draft = Entry::find()->draftId($draftId)->site($siteHandle)->anyStatus()->one();
        if (!$draft) {
            return false;
        }
        $source = Entry::find()->id($draft->getSourceId())->site($siteHandle)->anyStatus()->one();

        $site = Craft::$app->sites->getSiteByHandle($siteHandle);

        $data = [
            'title' => $draft->title,
            'draft' => $draft,
            'source' => $source,
            'changed' => []
        ];

        $query = new Query();
        $changedAttributes = $query
            ->from('{{%changedattributes}}')
            ->where(['elementId' => $draft->id, 'siteId' => $site->id])
            ->all();

        foreach ($changedAttributes as $changedAttribute) {
            $data['changed'][] = [
                'isAttribute' => true,
                'isRelation' => false,
                'isMatrix' => false,
                'fieldName' => $changedAttribute['attribute'],
                'fieldType' => 'attribute',
                'dateUpdated' => DateTimeHelper::toDateTime($changedAttribute['dateUpdated']),
                'propagated' => $changedAttribute['propagated'],
                'user' => $this->_getUserById($changedAttribute['userId']),
                'oldValue' => $source[$changedAttribute['attribute']],
                'newValue' => $draft[$changedAttribute['attribute']],
            ];
        }

        $query = new Query();
        $changedFields = $query
            ->from('{{%changedfields}}')
            ->where(['elementId' => $draft->id, 'siteId' => $site->id])
            ->all();

        foreach ($changedFields as $changedField) {
            $field = Craft::$app->fields->getFieldById($changedField['fieldId']);
            $fieldHandle = $field->handle;
            $isRelation = in_array(get_class($field), ['craft\fields\Assets', 'craft\fields\Entries']);
            $isMatrix = get_class($field) == 'craft\fields\Matrix';
            $data['changed'][] = [
                'isAttribute' => false,
                'isRelation' => $isRelation,
                'isMatrix' => $isMatrix,
                'fieldName' => $field->name,
                'field' => $field,
                'fieldType' => get_class($field),
                'dateUpdated' => DateTimeHelper::toDateTime($changedField['dateUpdated']),
                'propagated' => $changedField['propagated'],
                'user' => $this->_getUserById($changedField['userId']),
                'oldValue' => $isRelation ?
                    $source->getFieldValue($fieldHandle)->all() :
                    $source->getFieldValue($fieldHandle),
                'newValue' => $isRelation ?
                    $draft->getFieldValue($fieldHandle)->all() :
                    $draft->getFieldValue($fieldHandle),
            ];
        }

        return $data;
    }

    private function _getUserById($userId)
    {
        // https://www.yiiframework.com/doc/api/2.0/yii-caching-cacheinterface#getOrSet()-detail

        $key = ['user', ['userId' => $userId]];
        $cache = Craft::$app->cache;
        // $cache->delete($key);

        $user = $cache->getOrSet($key, function($cache) use ($userId) {
            return User::find()->id($userId)->one();
        });

        return $user;
    }
}
