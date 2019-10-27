<?php

namespace wsydney76\versions\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\User;
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

}
