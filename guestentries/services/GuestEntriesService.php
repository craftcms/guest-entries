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

	/**
	  * Fires an 'onSuccess' event.
	  *
	  * @param GuestEntriesEvent $event
	  */
	 public function onSuccess(GuestEntriesEvent $event)
	 {
		 $this->raiseEvent('onSuccess', $event);
	 }

	 /**
	  * Fires an 'onError' event.
	  *
	  * @param GuestEntriesEvent $event
	  */
	 public function onError(GuestEntriesEvent $event)
	 {
		 $this->raiseEvent('onError', $event);
	 }
}
