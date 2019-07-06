<?php

namespace wsydney76\versions;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use wsydney76\versions\behaviors\EntryBehavior;
use wsydney76\versions\models\SettingsModel;
use wsydney76\versions\services\VersionsService;
use yii\base\Event;
use function array_splice;

/**
 * Custom module class.
 *
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('my-module')`.
 *
 * You can change its module ID ("my-module") to something else from
 * config/app.php.
 *
 * If you want the module to get loaded on every request, uncomment this line
 * in config/app.php:
 *
 *     'bootstrap' => ['my-module']
 *
 * Learn more about Yii module development in Yii's documentation:
 * http://www.yiiframework.com/doc-2.0/guide-structure-modules.html
 */
class Versions extends Plugin
{

    public $hasCpSettings = true;
    /**
     * Initializes the module.
     */
    public function init()
    {

        // Set Nav
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $event) {
            $count = Entry::find()->site('*')->unique()->anyStatus()->drafts(true)->count();
            $nav = [
                'url' => 'versions/drafts',
                'label' => Craft::t('versions', 'Drafts'),
                'icon' => '@app/icons/field.svg',
                'badgeCount' => $count
            ];
            foreach ($event->navItems as $i => $navItem) {
                if ($navItem['url'] == 'entries') {
                    break;
                }
            }
            array_splice($event->navItems, $i + 1, 0, [$nav]);
        });

        // Register Edit Screen extensions
        Craft::$app->view->hook('cp.entries.edit.details', function(&$context) {
            if ($context['entry'] != null) {
                $entry = $context['entry'];
                $drafts = [];
                if ($entry->id && !$entry->isDraft) {
                    $drafts = Entry::find()->draftOf($entry->id)->siteId($entry->siteId)->all();
                }
                return Craft::$app->view->renderTemplate('versions/hook_versions.twig',
                    [
                        'entry' => $entry,
                        'drafts' => $drafts
                    ]);
            }
        });

        // Register Service as Craft Variable
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $e) {
            $e->sender->set('versions', VersionsService::class);
        });

        // Register Behaviors
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event){
                if (Craft::$app->request->isCpRequest) {
                    $event->behaviors[] = EntryBehavior::class;
                }
        });

        parent::init();
    }

    /**
     * @return Model|SettingsModel|null
     */
    protected function createSettingsModel()
    {
        return new SettingsModel();
    }

    /**
     * @return string|null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function settingsHtml()
    {
        return Craft::$app->view->renderTemplate('versions/settings', [
            'settings' => $this->getSettings()
        ]);
    }

}
