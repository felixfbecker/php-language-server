<?php
declare(strict_types = 1);

namespace LanguageServer\Completion;

use LanguageServer\PhpDocument;
use LanguageServer\Protocol\ {
    Range,
    Position
};

class CompletionContext
{
    /**
     *
     * @var \LanguageServer\Protocol\Position
     */
    private $position;

    /**
     *
     * @var \LanguageServer\PhpDocument
     */
    private $phpDocument;

    /**
     *
     * @var string[]
     */
    private $lines;

    /**
     * @var \LanguageServer\Protocol\Range
     */
    private $replacementRange;

    public function __construct(PhpDocument $phpDocument)
    {
        $this->phpDocument = $phpDocument;
        $this->lines = explode("\n", $this->phpDocument->getContent());
    }

    public function getReplacementRange(): Range
    {
        return $this->replacementRange;
    }

    private function calculateReplacementRange(): Range
    {
        $line = $this->getLine($this->position->line);
        if (!empty($line)) {
            // modified regexp from http://php.net/manual/en/language.variables.basics.php
            if (preg_match_all('@\$?[a-zA-Z_\x7f-\xff]?[a-zA-Z0-9_\x7f-\xff]*@', $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    if (!empty($match[0])) {
                        $start = new Position($this->position->line, $match[1]);
                        $end = new Position($this->position->line, $match[1] + strlen($match[0]));
                        $range = new Range($start, $end);
                        if ($range->includes($this->position)) {
                            return $range;
                        }
                    }
                }
            }
        }
        return new Range($this->position, $this->position);
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition(Position $position)
    {
        $this->position = $position;
        $this->replacementRange = $this->calculateReplacementRange();
    }

    public function isObjectContext()
    {
        $line = $this->getLine($this->getPosition()->line);
        if (empty($line)) {
            return false;
        }
        $range = $this->getReplacementRange();
        if (preg_match_all('@(\$this->|self::)@', $line, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                if (($match[1] + strlen($match[0])) === $range->start->character) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getLine(int $line)
    {
        if (count($this->lines) <= $line) {
            return null;
        }
        return $this->lines[$line];
    }

    public function getPhpDocument()
    {
        return $this->phpDocument;
    }
}
