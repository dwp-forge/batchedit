<?php

/**
 * Plugin BatchEdit: Search and replace engine
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditMatch {

    const CONTEXT_LENGTH = 50;
    const CONTEXT_MAX_LINES = 3;

    private $pageOffset;
    private $originalText;
    private $replacedText;
    private $contextBefore;
    private $contextAfter;
    private $marked;

    /**
     *
     */
    public function __construct($pageText, $pageOffset, $text, $regexp, $replacement) {
        $this->pageOffset = $pageOffset;
        $this->originalText = $text;
        $this->replacedText = preg_replace($regexp, $replacement, $text);;
        $this->contextBefore = $this->cropContextBefore($pageText, $pageOffset);
        $this->contextAfter = $this->cropContextAfter($pageText, $pageOffset + strlen($text));
        $this->marked = FALSE;
    }

    /**
     *
     */
    public function getPageOffset() {
        return $this->pageOffset;
    }

    /**
     *
     */
    public function getOriginalText() {
        return $this->originalText;
    }

    /**
     *
     */
    public function getReplacedText() {
        return $this->replacedText;
    }

    /**
     *
     */
    public function getContextBefore() {
        return $this->contextBefore;
    }

    /**
     *
     */
    public function getContextAfter() {
        return $this->contextAfter;
    }

    /**
     *
     */
    public function mark($marked = TRUE) {
        $this->marked = $marked;
    }

    /**
     *
     */
    public function isMarked() {
        return $this->marked;
    }

    /**
     *
     */
    public function apply($pageText, $offsetDelta) {
        $pageOffset = $this->pageOffset + $offsetDelta;
        $before = substr($pageText, 0, $pageOffset);
        $after = substr($pageText, $pageOffset + strlen($this->originalText));

        return $before . $this->replacedText . $after;
    }

    /**
     *
     */
    private function cropContextBefore($pageText, $pageOffset) {
        $length = self::CONTEXT_LENGTH;
        $offset = $pageOffset - $length;

        if ($offset < 0) {
            $length += $offset;
            $offset = 0;
        }

        $context = substr($pageText, $offset, $length);
        $count = preg_match_all('/\n/', $context, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > self::CONTEXT_MAX_LINES) {
            $context = substr($context, $match[$count - self::CONTEXT_MAX_LINES - 1][0][1] + 1);
        }

        return $context;
    }

    /**
     *
     */
    private function cropContextAfter($pageText, $pageOffset) {
        $context = substr($pageText, $pageOffset, self::CONTEXT_LENGTH);
        $count = preg_match_all('/\n/', $context, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > self::CONTEXT_MAX_LINES) {
            $context = substr($context, 0, $match[self::CONTEXT_MAX_LINES][0][1]);
        }

        return $context;
    }
}
