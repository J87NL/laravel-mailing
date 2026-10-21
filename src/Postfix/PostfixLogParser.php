<?php

declare(strict_types=1);

namespace J87NL\Mailing\Postfix;

class PostfixLogParser
{
    private const string PROGRAM = 'postfix[\w.-]*\/[\w.\/-]+';

    private const string SYSLOG = '/(?:^|\s)'.self::PROGRAM.'\[\d+\]:\s+(?<message>.+)$/';

    private const string RFC5424 = '/^(?:<\d{1,3}>)?1\s+\S+\s+\S+\s+'.self::PROGRAM.'\s+\S+\s+\S+\s+(?:-|(?:\[.*?\])+)\s+(?:\xEF\xBB\xBF)?(?<message>.+)$/';

    private const string QUEUE_ID = '/^(?<queue>[0-9A-Za-z]{5,20}):\s+(?<rest>.+)$/';

    private const string MESSAGE_ID = '/^message-id=<?(?<id>[^<>\s]+)>?/';

    private const string DELIVERY = '/^to=<(?<to>[^>]*)>,(?<fields>.*?)\sstatus=(?<status>sent|deferred|bounced)\b\s*(?:\((?<reply>.*)\))?\s*$/';

    private const string EXPIRED = '/(?:^|,\s)status=(?<reply>expired\b.*)$/';

    private const string NO_QUEUE = 'NOQUEUE';

    private const string REMOVED = 'removed';

    public function parse(string $line): ?PostfixLogEntry
    {
        $message = $this->message(rtrim($line, "\r\n"));

        if (is_null($message) || preg_match(self::QUEUE_ID, $message, $matches) !== 1 || $matches['queue'] === self::NO_QUEUE) {
            return null;
        }

        $queueId = $matches['queue'];
        $rest = trim($matches['rest']);

        if ($rest === self::REMOVED) {
            return new PostfixLogEntry(PostfixLogEntryType::Removed, $queueId);
        }

        if (preg_match(self::MESSAGE_ID, $rest, $id) === 1) {
            return new PostfixLogEntry(PostfixLogEntryType::MessageId, $queueId, messageId: sprintf('<%s>', $id['id']));
        }

        if (preg_match(self::DELIVERY, $rest, $delivery) === 1) {
            return new PostfixLogEntry(
                type: PostfixLogEntryType::Delivery,
                queueId: $queueId,
                recipient: strtolower($delivery['to']),
                status: $delivery['status'],
                dsn: $this->field($delivery['fields'], 'dsn'),
                relay: $this->field($delivery['fields'], 'relay'),
                reply: isset($delivery['reply']) && trim($delivery['reply']) !== '' ? trim($delivery['reply']) : null,
            );
        }

        if (preg_match(self::EXPIRED, $rest, $expired) === 1) {
            return new PostfixLogEntry(PostfixLogEntryType::Expired, $queueId, status: 'expired', reply: $expired['reply']);
        }

        return null;
    }

    private function message(string $line): ?string
    {
        if (preg_match(self::SYSLOG, $line, $matches) === 1 || preg_match(self::RFC5424, $line, $matches) === 1) {
            return $matches['message'];
        }

        return null;
    }

    private function field(string $fields, string $name): ?string
    {
        if (preg_match(sprintf('/(?:^|,)\s*%s=(?<value>[^,\s]+)/', preg_quote($name, '/')), $fields, $matches) !== 1) {
            return null;
        }

        return $matches['value'];
    }
}
