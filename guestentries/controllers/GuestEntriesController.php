<?php
namespace Craft;

/**
 * Guest Entries controller
 */
class GuestEntriesController extends BaseController
{
	/**
	 * @var Allows anonymous access to this controller's actions.
	 * @access protected
	 */
	protected $allowAnonymous = true;


	/**
	 * Saves a "guest" entry.
	 *
	 * @throws Exception
	 */
	public function actionSaveEntry()
	{
		$this->requirePostRequest();

		// Only allow from the front-end.
		if (!craft()->request->isSiteRequest())
		{
			throw new HttpException(404);
		}

		// Grab the data posted data.
		$entry = $this->_populateEntryModel();

		// Fire an 'onBeforeSave' event
		Craft::import('plugins.guestentries.events.GuestEntriesEvent');
		$event = new GuestEntriesEvent($this, array('entry' => $entry));
		$this->onBeforeSave($event);

		if ($event->isValid)
		{
			if (!$event->fakeIt)
			{

				if (craft()->entries->saveEntry($entry))
				{
					$this->_returnSuccess($entry);
				}
				else
				{
					$this->_returnError($entry);
				}
			}
			else
			{
				// Pretend it worked.
				$this->_returnSuccess($entry);
			}
		}

		$this->_returnError($entry);
	}

	/**
	 * Fires an 'onBeforeSave' event.
	 *
	 * @param GuestEntriesEvent $event
	 */
	public function onBeforeSave(GuestEntriesEvent $event)
	{
		$this->raiseEvent('onBeforeSave', $event);
	}

	/**
	 * Returns a 'success' response.
	 *
	 * @param $entry
	 * @return void
	 */
	private function _returnSuccess($entry)
	{
		if (craft()->request->isAjaxRequest())
		{
			$return['success']   = true;
			$return['title']     = $entry->title;
			$return['cpEditUrl'] = $entry->getCpEditUrl();
			$return['author']    = $entry->getAuthor()->getAttributes();
			$return['postDate']  = ($entry->postDate ? $entry->postDate->localeDate() : null);

			$this->returnJson($return);
		}
		else
		{
			craft()->userSession->setNotice(Craft::t('Entry saved.'));

			// TODO: Remove for 2.0
			if (isset($_POST['redirect']) && mb_strpos($_POST['redirect'], '{entryId}') !== false)
			{
				Craft::log('The {entryId} token within the ‘redirect’ param on entries/saveEntry requests has been deprecated. Use {id} instead.', LogLevel::Warning);
				$_POST['redirect'] = str_replace('{entryId}', '{id}', $_POST['redirect']);
			}

			$this->redirectToPostedUrl($entry);
		}
	}

	/**
	 * Returns an 'error' response.
	 *
	 * @param $entry
	 */
	private function _returnError($entry)
	{
		if (craft()->request->isAjaxRequest())
		{
			$this->returnJson(array(
				'errors' => $entry->getErrors(),
			));
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save entry.'));

			// Send the entry back to the template
			craft()->urlManager->setRouteVariables(array(
				'entry' => $entry
			));
		}
	}

	/**
	 * Populates an EntryModel with post data.
	 *
	 * @access private
	 * @throws HttpException
	 * @throws Exception
	 * @return EntryModel
	 */
	private function _populateEntryModel()
	{
		$entry = new EntryModel();

		$entry->sectionId     = craft()->request->getRequiredPost('sectionId');

		$section = craft()->sections->getSectionById($entry->sectionId);
		$settings = craft()->plugins->getPlugin('guestentries')->getSettings();

		// If we're allowing guest submissions adn we've got a default author specified, grab the authorId.
		if ($settings->allowGuestSubmissions && isset($settings->defaultAuthors[$section->handle]) && $settings->defaultAuthors[$section->handle] !== 'none')
		{
			// We found a defaultAuthor
			$authorId = $settings->defaultAuthors[$section->handle];
		}
		else
		{
			// Otherwise, complain loudly.
			throw new HttpException(403);
		}

		$localeId = craft()->request->getPost('locale');
		if ($localeId)
		{
			$entry->locale = $localeId;
		}

		$entry->typeId        = craft()->request->getPost('typeId');

		$postDate = craft()->request->getPost('postDate');
		if ($postDate)
		{
			DateTime::createFromString($postDate,   craft()->timezone);
		}

		$expiryDate = craft()->request->getPost('expiryDate');
		if ($expiryDate)
		{
			DateTime::createFromString($expiryDate, craft()->timezone);
		}

		$entry->authorId      = $authorId;
		$entry->slug          = craft()->request->getPost('slug');
		$entry->postDate      = $postDate;
		$entry->expiryDate    = $expiryDate;
		$entry->enabled       = (bool)$settings->enabledByDefault[$section->handle];
		$entry->localeEnabled = (bool) craft()->request->getPost('localeEnabled');

		$entry->getContent()->title = craft()->request->getPost('title');

		$fieldsLocation = craft()->request->getParam('fieldsLocation', 'fields');
		$entry->setContentFromPost($fieldsLocation);

		$entry->parentId = craft()->request->getPost('parentId');

		return $entry;
	}
}
