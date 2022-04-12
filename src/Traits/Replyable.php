<?php

namespace Dacastro4\LaravelGmail\Traits;

use Dacastro4\LaravelGmail\Services\Message\Mail;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Swift_Attachment;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @property Google_Service_Gmail $service
 */
trait Replyable
{
	use HasHeaders;

	protected $symfonyMessage;

	/**
	 * Gmail optional parameters
	 *
	 * @var array
	 */
    protected $parameters = [];

	/**
	 * Text or html message to send
	 *
	 * @var string
	 */
    protected $message;

	/**
	 * Subject of the email
	 *
	 * @var string
	 */
    protected $subject;

	/**
	 * Sender's email
	 *
	 * @var string
	 */
    protected $from;

	/**
	 * Sender's name
	 *
	 * @var  string
	 */
    protected $nameFrom;

	/**
	 * Email of the recipient
	 *
	 * @var string|array
	 */
    protected $to;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
    protected $nameTo;

	/**
	 * Single email or array of email for a carbon copy
	 *
	 * @var array|string
	 */
    protected $cc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
    protected $nameCc;

	/**
	 * Single email or array of email for a blind carbon copy
	 *
	 * @var array|string
	 */
    protected $bcc;

	/**
	 * Name of the recipient
	 *
	 * @var string
	 */
    protected $nameBcc;

	/**
	 * List of attachments
	 *
	 * @var array
	 */
    protected $attachments = [];

    protected $priority = 2;

	public function __construct()
	{
		$this->symfonyMessage = new Email();
	}

	/**
	 * Receives the recipient's
	 * If multiple recipients will receive the message an array should be used.
	 * Example: array('receiver@domain.org', 'other@domain.org' => 'A name')
	 *
	 * If $name is passed and the first parameter is a string, this name will be
	 * associated with the address.
	 *
	 * @param  string|array  $to
	 *
	 * @param  string|null  $name
	 *
	 * @return Replyable
	 */
	public function to($to, $name = null)
	{
		$this->to = $to;
		$this->nameTo = $name;

		return $this;
	}

	public function from($from, $name = null)
	{
		$this->from = $from;
		$this->nameFrom = $name;

		return $this;
	}

	/**
	 * @param  array|string  $cc
	 *
	 * @param  string|null  $name
	 *
	 * @return Replyable
	 */
	public function cc($cc, $name = null)
	{
		$this->cc = $this->emailList($cc, $name);
		$this->nameCc = $name;

		return $this;
	}

    protected function emailList($list, $name = null)
	{
		if (is_array($list)) {
			return $this->convertEmailList($list, $name);
		} else {
			return $list;
		}
	}

    protected function convertEmailList($emails, $name = null)
	{
		$newList = [];
		$count = 0;
		foreach ($emails as $key => $email) {
			$emailName = isset($name[$count]) ? $name[$count] : explode('@', $email)[0];
			$newList[$email] = $emailName;
			$count = $count + 1;
		}

		return $newList;
	}

	/**
	 * @param  array|string  $bcc
	 *
	 * @param  string|null  $name
	 *
	 * @return Replyable
	 */
	public function bcc($bcc, $name = null)
	{
		$this->bcc = $this->emailList($bcc, $name);
		$this->nameBcc = $name;

		return $this;
	}

	/**
	 * @param  string  $subject
	 *
	 * @return Replyable
	 */
	public function subject($subject)
	{
		$this->subject = $subject;

		return $this;
	}

	/**
	 * @param  string  $view
	 * @param  array  $data
	 * @param  array  $mergeData
	 *
	 * @return Replyable
	 * @throws \Throwable
	 */
	public function view($view, $data = [], $mergeData = [])
	{
		$this->message = view($view, $data, $mergeData)->render();

		return $this;
	}

	/**
	 * @param  string  $message
	 *
	 * @return Replyable
	 */
	public function message($message)
	{
		$this->message = $message;

		return $this;
	}

	/**
	 * Attaches new file to the email from the Storage folder
	 *
	 * @param  array  $files  comma separated of files
	 *
	 * @return Replyable
	 * @throws \Exception
	 */
	public function attach(...$files)
	{
		foreach ($files as $file) {

			if (!file_exists($file)) {
				throw new FileNotFoundException($file);
			}

			array_push($this->attachments, $file);
		}

		return $this;
	}

    public function getSymfonyMessage()
    {
        return $this->symfonyMessage;
    }

	/**
	 * The value is an integer where 1 is the highest priority and 5 is the lowest.
	 *
	 * @param  int  $priority
	 *
	 * @return Replyable
	 */
	public function priority($priority)
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * @param  array  $parameters
	 *
	 * @return Replyable
	 */
	public function optionalParameters(array $parameters)
	{
		$this->parameters = $parameters;

		return $this;
	}

	/**
	 * Reply to a specific email
	 *
	 * @return Mail
	 * @throws \Exception
	 */
	public function reply()
	{
		if (!$this->getId()) {
			throw new \Exception('This is a new email. Use send().');
		}

		$this->setReplyThread();
		$this->setReplySubject();
		$this->setReplyTo();
		$this->setReplyFrom();
		$body = $this->getMessageBody();
		$body->setThreadId($this->getThreadId());

		return new Mail($this->service->users_messages->send('me', $body, $this->parameters));
	}

	public abstract function getId();

    protected function setReplyThread()
	{
		$threadId = $this->getThreadId();
		if ($threadId) {
			$this->setHeader('In-Reply-To', $this->getHeader('In-Reply-To'));
			$this->setHeader('References', $this->getHeader('References'));
			$this->setHeader('Message-ID', $this->getHeader('Message-ID'));
		}
	}

	public abstract function getThreadId();

	/**
	 * Add a header to the email
	 *
	 * @param  string  $header
	 * @param  string  $value
	 */
	public function setHeader($header, $value)
	{
		$headers = $this->symfonyMessage->getHeaders();

		$headers->addTextHeader($header, $value);

	}

    protected function setReplySubject()
	{
		if (!$this->subject) {
			$this->subject = $this->getSubject();
		}
	}

    protected function setReplyTo()
	{
		if (!$this->to) {
			$replyTo = $this->getReplyTo();

			$this->to = $replyTo['email'];
			$this->nameTo = $replyTo['name'];
		}
	}

    protected function setReplyFrom()
	{
		if (!$this->from) {
			$this->from = $this->getUser();
			if(!$this->from) {
				throw new \Exception('Reply from is not defined');
			}
		}
	}

	public abstract function getSubject();

	public abstract function getReplyTo();

	public abstract function getUser();

	/**
	 * @return Google_Service_Gmail_Message
	 */
    protected function getMessageBody()
	{
		$body = new Google_Service_Gmail_Message();

		$this->symfonyMessage
			->setSubject($this->subject)
			->setFrom($this->from, $this->nameFrom)
			->setTo($this->to, $this->nameTo)
			->setCc($this->cc, $this->nameCc)
			->setBcc($this->bcc, $this->nameBcc)
			->setBody($this->message, 'text/html')
			->setPriority($this->priority);

		foreach ($this->attachments as $file) {
			$this->symfonyMessage
				->embed(Swift_Attachment::fromPath($file));
		}

		$body->setRaw($this->base64_encode($this->symfonyMessage->toString()));

		return $body;
	}

    protected function base64_encode($data)
	{
		return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
	}

	/**
	 * Sends a new email
	 *
	 * @return self|Mail
	 */
	public function send()
	{
		$body = $this->getMessageBody();

		$this->setMessage($this->service->users_messages->send('me', $body, $this->parameters));

		return $this;
	}

	protected abstract function setMessage(\Google_Service_Gmail_Message $message);
}
