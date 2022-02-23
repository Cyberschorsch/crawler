<?php

namespace Crwlr\Crawler\Http;

use DateTime;
use InvalidArgumentException;

class Date
{
    private ?DateTime $dateTime = null;

    public function __construct(private string $httpDateString)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function dateTime(): DateTime
    {
        if (!$this->dateTime instanceof DateTime) {
            $dateTime = DateTime::createFromFormat('l, d M Y H:i:s e', $this->httpDateString);

            if (!$dateTime instanceof DateTime) {
                throw new InvalidArgumentException('Can\'t parse date string ' . $this->httpDateString);
            }

            $this->dateTime = $dateTime;
        }

        return $this->dateTime;
    }
}
