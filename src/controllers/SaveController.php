<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\guestentries\controllers;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\guestentries\events\SaveEvent;
use craft\guestentries\models\SectionSettings;
use craft\guestentries\models\Settings;
use craft\guestentries\Plugin;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\models\Section;
use craft\web\Controller;
use craft\web\Request;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Guest Entries controller
 */
class SaveController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Constants
    // =========================================================================

    /**
     * @event SaveEvent The event that is triggered before a guest entry is saved.
     */
    const EVENT_BEFORE_SAVE_ENTRY = 'beforeSaveEntry';

    /**
     * @event SaveEvent The event that is triggered after a guest entry is saved.
     */
    const EVENT_AFTER_SAVE_ENTRY = 'afterSaveEntry';

    /**
     * @event SaveEvent The event that is triggered after an error occurs.
     */
    const EVENT_AFTER_ERROR = 'afterError';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->enableCsrfValidation = Plugin::getInstance()->getSettings()->enableCsrfProtection;
    }

    /**
     * Saves a guest entry.
     *
     * @return Response|null
     * @throws BadRequestHttpException if it's not a post request or the requested section doesn't exist/allow guest submissions
     * @throws NotFoundHttpException if it's not a front end request
     */
    public function actionIndex()
    {
        $this->requirePostRequest();

        // Only allow front end requests
        $request = Craft::$app->getRequest();
        if (!$request->getIsSiteRequest()) {
            throw new NotFoundHttpException();
        }

        // Make sure the section exists
        $sectionId = $request->getBodyParam('sectionId');
        $sectionUid = $request->getBodyParam('sectionUid');
        $sectionHandle = $request->getBodyParam('sectionHandle');

        $sectionService = Craft::$app->getSections();

        if ($sectionHandle) {
            $section = $sectionService->getSectionByHandle($sectionHandle);
        } else if ($sectionUid) {
            $section = $sectionService->getSectionByUid($sectionUid);
        } else {
            $section = $sectionService->getSectionById((int) $sectionId);
        }

        if (!$section) {
            throw new BadRequestHttpException('Section does not exist.');
        }

        $sectionUid = $section->uid;

        // Make sure the section allows guest submissions
        $settings = Plugin::getInstance()->getSettings();
        $sectionSettings = $settings->getSection($sectionUid);

        if (!$sectionSettings->allowGuestSubmissions) {
            throw new BadRequestHttpException('Section '.$section->handle.' does not allow guest submissions.');
        }

        // Populate the entry
        $entry = $this->_populateEntryModel($section, $sectionSettings, $request);

        // Fire an 'onBeforeSave' event
        $event = new SaveEvent(['entry' => $entry]);
        $this->trigger(self::EVENT_BEFORE_SAVE_ENTRY, $event);

        if (!$event->isValid) {
            return $this->_returnError($settings, $entry);
        }

        if ($event->isSpam) {
            Craft::info('Guest entry submission suspected to be spam.', __METHOD__);
            // Pretend it worked.
            return $this->_returnSuccess($entry, true);
        }

        // Try to save it
        if ($sectionSettings->runValidation) {
            $entry->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            return $this->_returnError($settings, $entry);
        }

        return $this->_returnSuccess($entry);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a 'success' response.
     *
     * @param Entry $entry
     * @param       $isSpam
     * @return Response
     */
    private function _returnSuccess(Entry $entry, $isSpam = false): Response
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_ENTRY)) {
            $this->trigger(self::EVENT_AFTER_SAVE_ENTRY, new SaveEvent([
                'entry' => $entry,
                'isSpam' => $isSpam
            ]));
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $entry->id,
                'title' => $entry->title,
                'authorUsername' => $entry->getAuthor()->username,
                'dateCreated' => DateTimeHelper::toIso8601($entry->dateCreated),
                'dateUpdated' => DateTimeHelper::toIso8601($entry->dateUpdated),
                'postDate' => $entry->postDate ? DateTimeHelper::toIso8601($entry->postDate) : null,
                'url' => $entry->getUrl(),
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('guest-entries', 'Entry saved.'));

        return $this->redirectToPostedUrl($entry);
    }

    /**
     * Returns an 'error' response.
     *
     * @param Settings $settings
     * @param Entry $entry
     * @return Response|null
     */
    private function _returnError(Settings $settings, Entry $entry)
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_ERROR)) {
            $this->trigger(self::EVENT_AFTER_ERROR, new SaveEvent([
                'entry' => $entry
            ]));
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'errors' => $entry->getErrors(),
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('guest-entries', 'Couldn’t save entry.'));

        // Send the entry back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'variables' => [$settings->entryVariable => $entry]
        ]);

        return null;
    }

    /**
     * Populates an EntryModel with post data.
     *
     * @param Section $section
     * @param SectionSettings $sectionSettings
     * @param Request $request
     * @throws BadRequestHttpException if the requested section doesn't allow guest submissions
     * @return Entry
     */
    private function _populateEntryModel(Section $section, SectionSettings $sectionSettings, Request $request): Entry
    {
        // Create and populate the entry
        $entry = new Entry([
            'sectionId' => $section->id,
            'authorId' => Db::idByUid('{{%users}}', $sectionSettings->authorUid),
            'siteId' => $request->getBodyParam('siteId'),
            'typeId' => $request->getBodyParam('typeId') ?? $section->getEntryTypes()[0]->id,
            'title' => $request->getBodyParam('title'),
            'slug' => $request->getBodyParam('slug'),
            'enabled' => (bool)$sectionSettings->enabledByDefault,
            'enabledForSite' => (bool)$request->getBodyParam('enabledForSite', true),
            'newParentId' => $request->getBodyParam('parentId'),
        ]);

        if (($postDate = $request->getBodyParam('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }

        if (($expiryDate = $request->getBodyParam('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        $fieldsLocation = $request->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        return $entry;
    }
}
