<?php

namespace wsydney76\versions\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\records\Entry as EntryRecord;
use wsydney76\versions\Versions;

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
    public function getAllDrafts(string $site, string $preferSite)
    {

        return Entry::find()
            ->site($site)
            ->unique()
            ->preferSites([$preferSite])
            ->anyStatus()
            ->drafts(true)
            ->andWhere(['not like','title','__temp'])
            ->orderBy('dateCreated desc')
            ->all();
    }

    public function getDraftsCount() {
        return Entry::find()
            ->site('*')
            ->unique()
            ->anyStatus()
            ->drafts(true)
            ->andWhere(['not like','title','__temp'])
            ->count();
    }


}
