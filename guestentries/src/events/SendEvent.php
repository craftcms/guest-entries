<?php
namespace craft\guestentries\events;
use yii\base\Event;
/**
 * Guest Entries event
 */
class SendEvent extends Event
{
	/**
	 * @var bool Whether the submission is valid.
	 */
	public $isValid = true;

	/**
	 * @var bool Whether we should pretend the submission went through, but it really didn't.
	 */
	public $fakeIt = false;


	public $entry;

	public $successEvent;

	public $errorEvent;

	public $faked;

}


