<?php

namespace wsydney76\versions\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use wsydney76\versions\behaviors\EntryBehavior;

class ChangesetController extends Controller
{

    /**
     * @return \craft\base\ElementInterface
     * @throws \Throwable
     * @throws \yii\base\ExitException
     */
    public function actionTest()
    {
        $draft = Entry::find()->id(136)->drafts(true)->anyStatus()->one();

        // All checks incl permisson have to happen before applying the draft
        $draft->scenario = ENTRY::SCENARIO_LIVE;

        if ($draft->validate()) {
            return Craft::$app->drafts->applyDraft($draft);
        } else {
            return Craft::dd($draft->errors);
        }
    }
}
