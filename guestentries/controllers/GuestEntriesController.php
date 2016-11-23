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
	 * @var
	 */
	private $_section;


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

		$settings = craft()->plugins->getPlugin('guestentries')->getSettings();

		// Grab the data posted data.
		$entry = $this->_populateEntryModel($settings);

		// See if they want validation. Note that this usually doesn't occur if the entry is set to disabled by default.
		if ($settings->validateEntry[$this->_section->handle])
		{
			// Does the entry type have dynamic titles?
			$entryType = $entry->getType();

			if (!$entryType->hasTitleField)
			{
				// Have to pre-set the dynamic Title value here, so Title validation doesn't fail.
				$entry->getContent()->title = craft()->templates->renderObjectTemplate($entryType->titleFormat, $entry);
			}

			// Now validate any content
			if (!craft()->content->validateContent($entry))
			{
				$entry->addErrors($entry->getContent()->getErrors());
			}

			if ($entry->hasErrors())
			{
				$this->_returnError($entry);
			}
		}

		// Fire an 'onBeforeSave' event
		$event = new GuestEntriesEvent($this, array('entry' => $entry));
		craft()->guestEntries->onBeforeSave($event);

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
				$this->_returnSuccess($entry, true);
			}
		}

		$this->_returnError($entry);
	}

	/**
	 * Returns a 'success' response.
	 *
	 * @param $entry
	 * @return void
	 */
	private function _returnSuccess($entry, $faked = false)
	{
		$successEvent = new GuestEntriesEvent($this, array('entry' => $entry, 'faked' => $faked));
		craft()->guestEntries->onSuccess($successEvent);

		if (craft()->request->isAjaxRequest())
		{
			$return['success']   = true;
			$return['id']        = $entry->id;
			$return['title']     = $entry->title;

			if (craft()->request->isCpRequest())
			{
				$return['cpEditUrl'] = $entry->getCpEditUrl();
			}

			$return['authorUsername']   = $entry->getAuthor()->username;
			$return['dateCreated']      = DateTimeHelper::toIso8601($entry->dateCreated);
			$return['dateUpdated']      = DateTimeHelper::toIso8601($entry->dateUpdated);
			$return['postDate']         = ($entry->postDate ? DateTimeHelper::toIso8601($entry->postDate) : null);

			if ($entry->getUrl())
			{
				$return['url']          = $entry->getUrl();
			}

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
		$errorEvent = new GuestEntriesEvent($this, array('entry' => $entry));
		craft()->guestEntries->onError($errorEvent);

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
			$entryVariable = craft()->config->get('entryVariable', 'guestentries');

			craft()->urlManager->setRouteVariables(array(
				$entryVariable => $entry
			));
		}
	}

	/**
	 * Populates an EntryModel with post data.
	 *
	 * @access private
	 * @param $settings
	 * @throws HttpException
	 * @return EntryModel
	 */
	private function _populateEntryModel($settings)
	{
		$entry = new EntryModel();

		$entry->sectionId     = craft()->request->getRequiredPost('sectionId');

		$this->_section = craft()->sections->getSectionById($entry->sectionId);

		if (!$this->_section)
		{
			throw new HttpException(404);
		}

		// If we're allowing guest submissions and we've got a default author specified, grab the authorId.
		if ($settings->allowGuestSubmissions && isset($settings->defaultAuthors[$this->_section->handle]) && $settings->defaultAuthors[$this->_section->handle] !== 'none')
		{
			// We found a defaultAuthor
			$authorId = $settings->defaultAuthors[$this->_section->handle];
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
		$entry->enabled       = (bool)$settings->enabledByDefault[$this->_section->handle];

		if (($localeEnabled = craft()->request->getPost('localeEnabled', null)) === null)
		{
			$localeEnabled = true;
		}

		$entry->localeEnabled = (bool) $localeEnabled;

		$entry->getContent()->title = craft()->request->getPost('title');

		$fieldsLocation = craft()->request->getParam('fieldsLocation', 'fields');
		$entry->setContentFromPost($fieldsLocation);

		$entry->parentId = craft()->request->getPost('parentId');

		return $entry;
	}
}
