<?php
declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use PHPMailer\PHPMailer\Exception AS PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use function count;

final class SimpleMailer
{
    private PHPMailer $mailer;

    /**
     * @param bool $enabled Is mailer enabled or not.
     * @param array{string, string|null}|string|null $sender Default sender (email with optional name).
     * @param array<array{string, string|null}>|null $recipients Override recipients.
     * @param array<array{string, string|null}>|null $replies Default replies.
     * @param bool $strict Adding wrong recipients, cc, replies and errors on sending will throw exceptions.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly bool $strict = false,
        array|string|null $sender = null,
        ?array $recipients = null,
        ?array $replies = null,
    ) {
        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;

        if ($sender !== null) {
            $this->setFrom(...(array) $sender);
        }
        if ($recipients !== null) {
            foreach ($recipients as $recipient) {
                $this->addRecipient(...$recipient);
            }
        }
        if ($replies !== null) {
            foreach ($replies as $reply) {
                $this->addReply(...$reply);
            }
        }
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
     * Creates message and sends it.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function send(): bool
    {
        if (count($this->mailer->getToAddresses()) === 0) {
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
