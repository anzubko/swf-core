<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use PHPMailer\PHPMailer\Exception AS PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SimpleMailer
{
    private PHPMailer $mailer;

    private bool $strict;

    /**
     * @param bool $enabled Is mailer enabled or not.
     * @param array{string, string|null}|string|null $sender Default sender: 'EMAIL' or array('EMAIL'[, 'NAME']) or null
     * @param array<array{string, string|null}>|null $recipients Override recipients: ?array('EMAIL' or array('EMAIL'[, 'NAME']), ...)
     * @param array<array{string, string|null}>|null $replies Default replies: ?array('EMAIL' or array('EMAIL'[, 'NAME']), ...)
     * @param bool $strict Adding wrong recipients, cc, replies and errors on sending will throw exceptions.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly bool $enabled = true,
        array|string|null $sender = null,
        ?array $recipients = null,
        ?array $replies = null,
        bool $strict = false,
    ) {
        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;

        $this->strict = true;

        if (null !== $sender) {
            $this->setFrom(...(array) $sender);
        }
        if (null !== $recipients) {
            $this->addRecipients($recipients);
        }
        if (null !== $replies) {
            $this->addReplies($replies);
        }

        $this->strict = $strict;
    }

    /**
     * Sets from.
     *
     * @throws InvalidArgumentException
     */
    public function setFrom(string $email, ?string $name = null): self
    {
        try {
            $this->mailer->setFrom($email, $name ?? '');
        } catch (PHPMailerException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    /**
     * Adds recipient.
     *
     * @throws InvalidArgumentException
     */
    public function addRecipient(string $email, ?string $name = null): self
    {
        try {
            $this->mailer->addAddress($email, $name ?? '');
        } catch (PHPMailerException $e) {
            if ($this->strict) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Sets recipient.
     *
     * @throws InvalidArgumentException
     */
    public function setRecipient(string $email, ?string $name = null): self
    {
        $this->mailer->clearAddresses();

        return $this->addRecipient($email, $name);
    }

    /**
     * Adds recipients.
     *
     * @param array<array{string, string|null}> $recipients
     *
     * @throws InvalidArgumentException
     */
    public function addRecipients(array $recipients): self
    {
        foreach ($recipients as $recipient) {
            $this->addRecipient(...$recipient);
        }

        return $this;
    }

    /**
     * Sets recipients.
     *
     * @param array<array{string, string|null}> $recipients
     *
     * @throws InvalidArgumentException
     */
    public function setRecipients(array $recipients = []): self
    {
        $this->mailer->clearAddresses();

        return $this->addRecipients($recipients);
    }

    /**
     * Adds CC.
     *
     * @throws InvalidArgumentException
     */
    public function addCC(string $email, ?string $name = null): self
    {
        try {
            $this->mailer->addCC($email, $name ?? '');
        } catch (PHPMailerException $e) {
            if ($this->strict) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Sets CC.
     *
     * @throws InvalidArgumentException
     */
    public function setCC(string $email, ?string $name = null): self
    {
        $this->mailer->clearCCs();

        return $this->addCC($email, $name);
    }

    /**
     * Adds CC's.
     *
     * @param array<array{string, string|null}> $copies
     *
     * @throws InvalidArgumentException
     */
    public function addCCs(array $copies): self
    {
        foreach ($copies as $copy) {
            $this->addCC(...$copy);
        }

        return $this;
    }

    /**
     * Sets CC's.
     *
     * @param array<array{string, string|null}> $copies
     *
     * @throws InvalidArgumentException
     */
    public function setCCs(array $copies = []): self
    {
        $this->mailer->clearCCs();

        return $this->addCCs($copies);
    }

    /**
     * Adds reply.
     *
     * @throws InvalidArgumentException
     */
    public function addReply(string $email, ?string $name = null): self
    {
        try {
            $this->mailer->addReplyTo($email, $name ?? '');
        } catch (PHPMailerException $e) {
            if ($this->strict) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Sets reply.
     *
     * @throws InvalidArgumentException
     */
    public function setReply(string $email, ?string $name = null): self
    {
        $this->mailer->clearReplyTos();

        return $this->addReply($email, $name);
    }

    /**
     * Adds replies.
     *
     * @param array<array{string, string|null}> $replies
     *
     * @throws InvalidArgumentException
     */
    public function addReplies(array $replies): self
    {
        foreach ($replies as $reply) {
            $this->addReply(...$reply);
        }

        return $this;
    }

    /**
     * Sets replies.
     *
     * @param array<array{string, string|null}> $replies
     *
     * @throws InvalidArgumentException
     */
    public function setReplies(array $replies = []): self
    {
        $this->mailer->clearReplyTos();

        return $this->addReplies($replies);
    }

    /**
     * Adds custom header.
     *
     * @throws InvalidArgumentException
     */
    public function addCustomHeader(string $name, ?string $value = null): self
    {
        try {
            $this->mailer->addCustomHeader($name, $value);
        } catch (PHPMailerException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    /**
     * Sets custom header.
     *
     * @throws InvalidArgumentException
     */
    public function setCustomHeader(string $name, ?string $value = null): self
    {
        $this->mailer->clearCustomHeaders();

        return $this->addCustomHeader($name, $value);
    }

    /**
     * Adds custom headers.
     *
     * @param array<array{string, string|null}> $headers
     *
     * @throws InvalidArgumentException
     */
    public function addCustomHeaders(array $headers): self
    {
        foreach ($headers as $header) {
            $this->addCustomHeader(...$header);
        }

        return $this;
    }

    /**
     * Sets custom headers.
     *
     * @param array<array{string, string|null}> $headers
     *
     * @throws InvalidArgumentException
     */
    public function setCustomHeaders(array $headers = []): self
    {
        $this->mailer->clearCustomHeaders();

        return $this->addCustomHeaders($headers);
    }

    /**
     * Sets subject.
     */
    public function setSubject(string $subject): self
    {
        $this->mailer->Subject = $subject;

        return $this;
    }

    /**
     * Sets body.
     *
     * @throws InvalidArgumentException
     */
    public function setBody(string $body, bool $html = true): self
    {
        if ($html) {
            try {
                $this->mailer->msgHTML($body);
            } catch (PHPMailerException $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        } else {
            $this->mailer->Body = $body;
        }

        return $this;
    }

    /**
     * Adds attachment file.
     *
     * @throws InvalidArgumentException
     */
    public function addAttachmentFile(string $path, ?string $filename = null, ?string $type = null): self
    {
        try {
            $this->mailer->addAttachment($path, $filename ?? '', type: $type ?? '');
        } catch (PHPMailerException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    /**
     * Sets attachment file.
     *
     * @throws InvalidArgumentException
     */
    public function setAttachmentFile(string $path, ?string $filename = null, ?string $type = null): self
    {
        $this->mailer->clearAttachments();

        return $this->addAttachmentFile($path, $filename, $type);
    }

    /**
     * Adds attachment files.
     *
     * @param array<array{string, string|null, string|null}> $attachments
     *
     * @throws InvalidArgumentException
     */
    public function addAttachmentFiles(array $attachments): self
    {
        foreach ($attachments as $attachment) {
            $this->addAttachmentFile(...$attachment);
        }

        return $this;
    }

    /**
     * Sets attachment files.
     *
     * @param array<array{string, string|null, string|null}> $attachments
     *
     * @throws InvalidArgumentException
     */
    public function setAttachmentFiles(array $attachments = []): self
    {
        $this->mailer->clearAttachments();

        return $this->addAttachmentFiles($attachments);
    }

    /**
     * Adds attachment string as file.
     *
     * @throws InvalidArgumentException
     */
    public function addAttachmentString(string $contents, string $filename, ?string $type = null): self
    {
        try {
            $this->mailer->addStringAttachment($contents, $filename, type: $type ?? '');
        } catch (PHPMailerException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    /**
     * Sets attachment string as file.
     *
     * @throws InvalidArgumentException
     */
    public function setAttachmentString(string $contents, string $filename, ?string $type = null): self
    {
        $this->mailer->clearAttachments();

        return $this->addAttachmentString($contents, $filename, $type);
    }

    /**
     * Adds attachment strings as files.
     *
     * @param array<array{string, string, string|null}> $attachments
     *
     * @throws InvalidArgumentException
     */
    public function addAttachmentStrings(array $attachments): self
    {
        foreach ($attachments as $attachment) {
            $this->addAttachmentString(...$attachment);
        }

        return $this;
    }

    /**
     * Sets attachment strings as files.
     *
     * @param array<array{string, string, string|null}> $attachments
     *
     * @throws InvalidArgumentException
     */
    public function setAttachmentStrings(array $attachments): self
    {
        $this->mailer->clearAttachments();

        return $this->addAttachmentStrings($attachments);
    }

    /**
     * Creates message and sends it.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function send(): bool
    {
        if (empty($this->mailer->getToAddresses())) {
            if ($this->strict) {
                throw new InvalidArgumentException('Must be at least one recipient');
            }

            return false;
        }

        if (!$this->enabled) {
            return true;
        }

        try {
            return $this->mailer->send();
        } catch (PHPMailerException $e) {
            if ($this->strict) {
                throw new RuntimeException($e->getMessage());
            }

            return false;
        }
    }
}
