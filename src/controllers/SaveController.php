<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries\controllers;

use Craft;
use craft\elements\Entry;
use craft\guestentries\events\SaveEvent;
use craft\guestentries\models\SectionSettings;
use craft\guestentries\models\Settings;
use craft\guestentries\Plugin;
use craft\helpers\DateTimeHelper;
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

    /**
     * @var Section The section the guest entry wants to be saved to.
     */
    private $_section;

    // Constants
    // =========================================================================

    /**
     * @event Event The event that is triggered before a guest entry is saved.
     */
    const EVENT_BEFORE_SAVE_ENTRY = 'beforeSaveEntry';

    /**
     * @event Event The event that is triggered after a guest entry is saved.
     */
    const EVENT_AFTER_SAVE_ENTRY = 'afterSaveEntry';

    /**
     * @event Event The event that is triggered after an error occurs.
     */
    const EVENT_ON_ERROR = 'onError';

    // Public Methods
    // =========================================================================

    /**
     * Saves a "guest" entry.
     *
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
        $sectionId = $request->getRequiredBodyParam('sectionId');
        if (($section = Craft::$app->getSections()->getSectionById($sectionId)) === null) {
            throw new BadRequestHttpException('Section '.$section.' does not exist.');
        }

        // Make sure the section allows guest submissions
        $settings = Plugin::getInstance()->getSettings();
        $sectionSettings = $settings->getSection($sectionId);
        if (!$sectionSettings->allowGuestSubmissions) {
            throw new BadRequestHttpException('Section '.$sectionId.' does not allow guest submissions.');
        }

        // Populate the entry
        $entry = $this->_populateEntryModel($section, $sectionSettings, $request);

        // Fire an 'onBeforeSave' event
        $event = new SaveEvent(['entry' => $entry]);
        $this->trigger(self::EVENT_BEFORE_SAVE_ENTRY, $event);

        if (!$event->isValid) {
            return $this->_returnError($entry);
        }

        if ($event->isSpam) {
            Craft::info('Guest entry submission suspected to be spam.', __METHOD__);
            // Pretend it worked.
            return $this->_returnSuccess($entry, true);
        }

        // Try to save it
        if (!Craft::$app->getElements()->saveElement($entry, $sectionSettings->runValidation)) {
            return $this->_returnError($entry);
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
     *
     * @return Response|null
     */
    private function _returnSuccess(Entry $entry, $isSpam = false)
    {
        $successEvent = new SaveEvent(['entry' => $entry, 'isSpam' => $isSpam]);
        $this->trigger(self::EVENT_AFTER_SAVE_ENTRY, $successEvent);

        if (Craft::$app->getRequest()->getIsAjax()) {
            $return['success'] = true;
            $return['id'] = $entry->id;
            $return['title'] = $entry->title;

            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $return['cpEditUrl'] = $entry->getCpEditUrl();
            }

            $return['authorUsername'] = $entry->getAuthor()->username;
            $return['dateCreated'] = DateTimeHelper::toIso8601($entry->dateCreated);
            $return['dateUpdated'] = DateTimeHelper::toIso8601($entry->dateUpdated);
            $return['postDate'] = ($entry->postDate ? DateTimeHelper::toIso8601($entry->postDate) : null);

            if ($entry->getUrl()) {
                $return['url'] = $entry->getUrl();
            }

            return $this->asJson($return);
        }

        Craft::$app->getSession()->setNotice(Craft::t('guest-entries', 'Entry Saved'));

        return $this->redirectToPostedUrl($entry);
    }

    /**
     * Returns an 'error' response.
     *
     * @param Entry $entry
     *
     * @return Response
     */
    private function _returnError(Entry $entry)
    {
        $errorEvent = new SaveEvent(['entry' => $entry]);
        $this->trigger(self::EVENT_ON_ERROR, $errorEvent);

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'errors' => $entry->getErrors(),
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('guest-entries', 'Error'));

        // Send the entry back to the template
        $entryVariable = Plugin::getInstance()->getSettings()->entryVariable;

        Craft::$app->getUrlManager()->setRouteParams([
            'variables' => [$entryVariable => $entry]
        ]);
    }

    /**
     * Populates an EntryModel with post data.
     *
     * @param Section         $section
     * @param SectionSettings $sectionSettings
     * @param Request         $request
     *
     * @throws BadRequestHttpException if the requested section doesn't allow guest submissions
     * @return Entry
     */
    private function _populateEntryModel(Section $section, SectionSettings $sectionSettings, Request $request): Entry
    {
        // Create and populate the entry
        $entry = new Entry([
            'sectionId' => $section->id,
            'authorId' => (int)$sectionSettings->authorId,
            'siteId' => $request->getBodyParam('siteId'),
            'typeId' => $request->getBodyParam('typeId') ?? $section->getEntryTypes()[0]->id,
            'title' => $request->getBodyParam('title'),
            'slug' => $request->getBodyParam('slug'),
            'enabled' => (bool)$sectionSettings->enabledByDefault,
            'enabledForSite' => (bool)$request->getBodyParam('enabledForSite', true),
            'newParentId' => $request->getBodyParam('parentId'),
            'validateCustomFields' => (bool)$sectionSettings->runValidation,
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
