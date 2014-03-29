<?php
namespace Craft;

class GuestEntriesService extends BaseApplicationcomponent
{
	/**
	 * Fires an 'onBeforeSave' event.
	 *
	 * @param GuestEntriesEvent $event
	 */
	public function onBeforeSave(GuestEntriesEvent $event)
	{
		$this->raiseEvent('onBeforeSave', $event);
	}
}
