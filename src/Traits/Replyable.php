<?php

namespace Dacastro4\LaravelGmail\Traits;

use Dacastro4\LaravelGmail\Services\Message\Mail;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @property Google_Service_Gmail $service
 */
trait Replyable
{
    use HasHeaders;

    protected Email $symfonyMessage;

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

    protected ?Address $from = null;

    protected array $to = [];

    protected array $cc = [];

    protected array $bcc = [];

    protected array $actualReplyTo = [];

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

    public function to(array|Address|string|null $to, ?string $name = null)
    {
        $this->to = $this->standardizeAddresses($to, $name);

        return $this;
    }

    /**
     * @param array<Address>|Address|string|null $address
     * @param string|null                        $name
     *
     * @return array<Address>
     */
    protected function standardizeAddresses(array|Address|string|null $address, ?string $name = null): array
    {
        if ($address === null) {
            return [];
        } elseif (is_string($address)) {
            return [new Address($address, $name ?? '')];
        } elseif ($address instanceof Address) {
            return [$address];
        }

        return $address;
    }

    public function from(Address|string|null $from, ?string $name = null)
    {
        if (is_string($from)) {
            $this->from = new Address($from, $name ?? '');
        } else {
            // Address or null
            $this->from = $from;
        }

        return $this;
    }

    public function cc(array|Address|string|null $cc, ?string $name = null)
    {
        $this->cc = $this->standardizeAddresses($cc, $name);

        return $this;
    }

    public function bcc(array|Address|string|null $bcc, ?string $name = null)
    {
        $this->bcc = $this->standardizeAddresses($bcc, $name);

        return $this;
    }

    public function actualReplyTo(array|Address|string|null $replyTo, ?string $name = null)
    {
        $this->actualReplyTo = $this->standardizeAddresses($replyTo, $name);

        return $this;
    }

    protected function emailList($list, $name = null)
    {
        return $list;
    }

    /**
     * @param string $subject
     *
     * @return Replyable
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param string $view
     * @param array  $data
     * @param array  $mergeData
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
     * @param string $message
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
     * @param array $files comma separated of files
     *
     * @return Replyable
     * @throws \Exception
     */
    public function attach(...$files)
    {
        foreach ($files as $file) {
            if (! file_exists($file)) {
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
     * @param int $priority
     *
     * @return Replyable
     */
    public function priority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @param array $parameters
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
        if (! $this->getId()) {
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
     * @param string $header
     * @param string $value
     */
    public function setHeader($header, $value)
    {
        $headers = $this->symfonyMessage->getHeaders();

        $headers->addTextHeader($header, $value);
    }

    protected function setReplySubject()
    {
        if (! $this->subject) {
            $this->subject = $this->getSubject();
        }
    }

    protected function setReplyTo()
    {
        if (! $this->to) {
            $replyTo = $this->getReplyTo();

            $this->to($replyTo['email'], $replyTo['name']);
        }
    }

    protected function setReplyFrom()
    {
        if (! $this->from) {
            $this->from = $this->getUser();
            if (! $this->from) {
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
            ->subject($this->subject)
            ->from($this->from)
            ->to(...$this->to);

        if (! empty($this->cc)) {
            $this->symfonyMessage->cc(...$this->cc);
        }
        if (! empty($this->bcc)) {
            $this->symfonyMessage->bcc(...$this->bcc);
        }
        if (! empty($this->actualReplyTo)) {
            $this->symfonyMessage->replyTo(...$this->actualReplyTo);
        }

        $this->symfonyMessage
            ->html($this->message)
            ->priority($this->priority);

        foreach ($this->attachments as $file) {
            $this->symfonyMessage->attachFromPath($file);
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
