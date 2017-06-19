<?php

namespace craft\guestentries\controllers;

use craft\elements\Entry;
use craft\guestentries\Plugin;
use craft\helpers\DateTimeHelper;
use craft\models\BaseEntryRevisionModel;
use craft\guestentries\events\SendEvent;
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

    /**
     * @var Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected $allowAnonymous = true;

    /**
     * @var
     */
    private $_section;

    /**
     * @event ElementContentEvent The event that is triggered before an element's content is saved.
     */
    const EVENT_BEFORE_SAVE_CONTENT = 'beforeSaveContent';

    /**
     * @event ElementContentEvent The event that is triggered after an element's content is saved.
     */
    const EVENT_AFTER_SAVE_CONTENT = 'afterSaveContent';

    /**
     * @event ElementContentEvent The event that is triggered after an error occurs.
     */
    const EVENT_ON_ERROR = 'onError';


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
        $settings = Craft::$app->getPlugins()->getPlugin('guestentries')->getSettings();


        // Grab the data posted data.
        $entry = $this->_populateEntryModel($settings);

        // See if they want validation. Note that this usually doesn't occur if the entry is set to
        // disabled by default.

        if ($settings->validateEntry[$this->_section->handle]) {
            // Does the entry type have dynamic titles?
            $entryType = $entry->getType();


            if (!$entryType->hasTitleField) {
                // Have to pre-set the dynamic Title value here, so Title validation doesn't fail.
                $entry->title = Craft::$app->getView()->render($entryType->titleFormat, $entry);
            }


            // Now validate any content
            if (!$entry->validate()) {
                return $this->_returnError($entry);
            }

        }

        // Fire an 'onBeforeSave' event
        $event = new SendEvent(['entry' => $entry]);
        $this->trigger(self::EVENT_BEFORE_SAVE_CONTENT, $event);

        if ($event->isValid) {
            if (!$event->fakeIt) {
                if (Craft::$app->getElements()->saveElement($entry)) {

                   return $this->_returnSuccess($entry);
                } else {
                   return $this->_returnError($entry);
                }
            } else {
                // Pretend it worked.
                return $this->_returnSuccess($entry, true);
            }
        }

       return $this->_returnError($entry);
    }

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
        $this->trigger(self::EVENT_AFTER_SAVE_CONTENT, $successEvent);


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
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('guestentries', 'Entry Saved'));

           return $this->redirectToPostedUrl($entry);
        }
    }

    /**
     * Returns an 'error' response.
     *
     * @param Entry $entry
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

        Craft::$app->getSession()->setError(Craft::t('guestentries', 'Error'));

        // Send the entry back to the template
        $entryVariable = Plugin::getInstance()->getSettings()->entryVariable;

        Craft::$app->getUrlManager()->setRouteParams([
            'variables' => [$entryVariable => $entry]
        ]);
        //return null;
    }

    /**
     * Populates an EntryModel with post data.
     *
     * @access private
     *
     * @param $settings
     *
     * @throws HttpException
     * @return BaseEntryRevisionModel
     */
    private function _populateEntryModel($settings)
    {

        $entry = new Entry();

        // Set the entry attributes, defaulting to the existing values for whatever is missing from the post data
        $entry->typeId = Craft::$app->getRequest()->getBodyParam('typeId', $entry->typeId);
        $entry->sectionId = Craft::$app->getRequest()->getBodyParam('sectionId', $entry->sectionId);

        $this->_section = Craft::$app->sections->getSectionById($entry->sectionId);

        $entry->slug = Craft::$app->getRequest()->getBodyParam('slug', $entry->slug);
        $entry->postDate = (($postDate = Craft::$app->getRequest()->getBodyParam('postDate')) !== false ? (DateTimeHelper::toDateTime($postDate) ?: null) : $entry->postDate);
        $entry->expiryDate = (($expiryDate = Craft::$app->getRequest()->getBodyParam('expiryDate')) !== false ? (DateTimeHelper::toDateTime($expiryDate) ?: null) : null);
        $entry->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled', $entry->enabled);
        $entry->enabledForSite = (bool)Craft::$app->getRequest()->getBodyParam('enabledForSite', $entry->enabledForSite);
        $entry->title = Craft::$app->getRequest()->getBodyParam('title', $entry->title);

        if (!$entry->typeId) {
            // Default to the section's first entry type
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }

        $entry->fieldLayoutId = $entry->getType()->fieldLayoutId;
        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        $authorId = $settings->defaultAuthors[$this->_section->handle];

        // Author
        //$authorId = Craft::$app->getRequest()->getBodyParam('author', ($entry->authorId ?: Craft::$app->getUser()->getIdentity()->id));


        if (is_array($authorId)) {
            $authorId = $authorId[0] ?? null;
        }

        $entry->authorId = $authorId;


        // Parent
        $parentId = Craft::$app->getRequest()->getBodyParam('parentId');

        if (is_array($parentId)) {
            $parentId = $parentId[0] ?? null;
        }

        $entry->newParentId = $parentId ?: null;

        // Revision notes
        $entry->revisionNotes = Craft::$app->getRequest()->getBodyParam('revisionNotes');

        return $entry;
    }

}
