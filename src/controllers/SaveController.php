<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\guestentries\controllers;

use craft\elements\Entry;
use craft\guestentries\Plugin;
use craft\helpers\DateTimeHelper;
use craft\guestentries\events\SendEvent;
use craft\models\Section;
use craft\web\Controller;
use Exception;
use yii\web\HttpException;
use Craft;
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
     * @throws Exception
     */
    public function actionIndex()
    {
        $this->requirePostRequest();

        // Only allow from the front-end.
        if (!Craft::$app->getRequest()->getIsSiteRequest()) {
            throw new HttpException(404);
        }

        $settings = Plugin::getInstance()->getSettings();

        // Grab the data posted data.
        $entry = $this->_populateEntryModel($settings);

        $runValidation = $settings->validateEntry[$this->_section->handle];

        // See if they want validation. Note that this usually doesn't occur if the entry is set to
        // disabled by default.
        if ($runValidation) {
            // Does the entry type have dynamic titles?
            $entryType = $entry->getType();

            if (!$entryType->hasTitleField) {
                // Have to pre-set the dynamic Title value here, so Title validation doesn't fail.
                $entry->title = Craft::$app->getView()->render($entryType->titleFormat, $entry);
            }
        }

        // Fire an 'onBeforeSave' event
        $event = new SendEvent(['entry' => $entry]);
        $this->trigger(self::EVENT_BEFORE_SAVE_ENTRY, $event);

        if ($event->isValid) {
            if (!$event->fakeIt) {
                if (Craft::$app->getElements()->saveElement($entry, $runValidation)) {
                   return $this->_returnSuccess($entry);
                }

                return $this->_returnError($entry);
            }

            // Pretend it worked.
            return $this->_returnSuccess($entry, true);
        }

       return $this->_returnError($entry);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a 'success' response.
     *
     * @param Entry $entry
     * @param $faked
     *
     * @return Response|null
     */
    private function _returnSuccess(Entry $entry, $faked = false)
    {
        $successEvent = new SendEvent(['entry' => $entry, 'faked' => $faked]);
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
        $errorEvent = new SendEvent( ['entry' => $entry]);
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
     * @param $settings
     *
     * @throws HttpException
     * @return Entry
     */
    private function _populateEntryModel($settings): Entry
    {
        $entry = new Entry();

        $entry->sectionId = Craft::$app->getRequest()->getRequiredBodyParam('sectionId');
        $this->_section = Craft::$app->sections->getSectionById($entry->sectionId);

        if (!$this->_section) {
            throw new HttpException(404);
        }

        // If we're allowing guest submissions and we've got a default author specified, grab the authorId.
        if ($settings->allowGuestSubmissions && isset($settings->defaultAuthors[$this->_section->handle]) && $settings->defaultAuthors[$this->_section->handle] !== 'none') {
            // We found a defaultAuthor
            $entry->authorId = $settings->defaultAuthors[$this->_section->handle];
        } else {
            // Otherwise, complain loudly.
            throw new HttpException(403);
        }

        $localeId = Craft::$app->getRequest()->getBodyParam('locale');

        if ($localeId) {
            $entry->locale = $localeId;
        }

        $entry->typeId = Craft::$app->getRequest()->getBodyParam('typeId');

        if (!$entry->typeId) {
            // Default to the section's first entry type
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }

        $postDate = Craft::$app->getRequest()->getBodyParam('postDate');

        if ($postDate) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }

        $expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate');

        if ($expiryDate) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        $entry->slug = Craft::$app->getRequest()->getBodyParam('slug');
        $entry->enabled = (bool)$settings->enabledByDefault[$this->_section->handle];

        if (($enabledForSite = Craft::$app->getRequest()->getBodyParam('enabledForSite')) === null)
        {
            $enabledForSite = true;
        }

        $entry->enabledForSite = (bool)$enabledForSite;

        $entry->title = Craft::$app->getRequest()->getBodyParam('title');

        $entry->fieldLayoutId = $entry->getType()->fieldLayoutId;
        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        // Parent
        $entry->newParentId = Craft::$app->getRequest()->getBodyParam('parentId');

        return $entry;
    }
}
