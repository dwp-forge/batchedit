<?php

/**
 * Plugin BatchEdit: Search and replace engine
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditPageApplyException extends Exception {
}

class BatcheditAccessControlException extends BatcheditPageApplyException {
}

class BatcheditPageLockedException extends BatcheditPageApplyException {

    public $lockedBy;

    /**
     *
     */
    public function __construct($lockedBy) {
        $this->lockedBy = $lockedBy;
    }
}

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

class BatcheditPage {

    private $id;
    private $matches;

    /**
     *
     */
    public function __construct($id) {
        $this->id = $id;
        $this->matches = array();
    }

    /**
     *
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     */
    public function findMatches($regexp, $replacement) {
        $text = rawWiki($this->id);
        $count = @preg_match_all($regexp, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new Exception('err_pregfailed');
        }

        for ($i = 0; $i < $count; $i++) {
            $this->addMatch($text, $match[$i][0][1], $match[$i][0][0], $regexp, $replacement);
        }

        return $count;
    }

    /**
     *
     */
    public function getMatches() {
        return $this->matches;
    }

    /**
     *
     */
    public function markMatch($offset) {
        if (array_key_exists($offset, $this->matches)) {
            $this->matches[$offset]->mark();
        }
    }

    /**
     *
     */
    public function hasMarkedMatches() {
        $result = FALSE;

        foreach ($this->matches as $match) {
            if ($match->isMarked()) {
                $result = TRUE;
                break;
            }
        }

        return $result;
    }

    /**
     *
     */
    public function hasUnmarkedMatches() {
        $result = FALSE;

        foreach ($this->matches as $match) {
            if (!$match->isMarked()) {
                $result = TRUE;
                break;
            }
        }

        return $result;
    }

    /**
     *
     */
    public function applyMatches($summary, $minorEdit) {
        try {
            $this->lock();

            $text = rawWiki($this->id);
            $originalLength = strlen($text);
            $count = 0;

            foreach ($this->matches as $match) {
                if ($match->isMarked()) {
                    $text = $match->apply($text, strlen($text) - $originalLength);
                    $count++;
                }
            }

            saveWikiText($this->id, $text, $summary, $minorEdit);
            unlock($this->id);
        }
        catch (Exception $error) {
            $this->unmarkAllMatches();

            throw $error;
        }

        return $count;
    }

    /**
     *
     */
    private function addMatch($text, $offset, $matched, $regexp, $replacement) {
        $this->matches[$offset] = new BatcheditMatch($text, $offset, $matched, $regexp, $replacement);
    }

    /**
     *
     */
    private function lock() {
        if (auth_quickaclcheck($this->id) < AUTH_EDIT) {
            throw new BatcheditAccessControlException();
        }

        $lockedBy = checklock($this->id);

        if ($lockedBy != FALSE) {
            throw new BatcheditPageLockedException($lockedBy);
        }

        lock($this->id);
    }

    /**
     *
     */
    private function unmarkAllMatches() {
        foreach ($this->matches as $match) {
            $match->mark(FALSE);
        }
    }
}

class BatcheditEngine {

    private $pages;
    private $matches;
    private $edits;

    /**
     *
     */
    public function __construct() {
        $this->pages = array();
        $this->matches = 0;
        $this->edits = 0;
    }

    /**
     *
     */
    public function findMatches($namespace, $regexp, $replacement) {
        if ($namespace != '') {
            $pattern = '/^' . $namespace . '/';
        }
        else {
            $pattern = '';
        }

        foreach ($this->getPageIndex() as $pageId) {
            $pageId = trim($pageId);

            if (($pattern == '') || (preg_match($pattern, $pageId) == 1)) {
                $page = new BatcheditPage($pageId);
                $count = $page->findMatches($regexp, $replacement);

                if ($count > 0) {
                    $this->pages[$pageId] = $page;
                    $this->matches += $count;
                }
            }
        }

        return $this->matches;
    }

    /**
     *
     */
    public function getPageCount() {
        return count($this->pages);
    }

    /**
     *
     */
    public function getMatchCount() {
        return $this->matches;
    }

    /**
     *
     */
    public function getEditCount() {
        return $this->edits;
    }

    /**
     *
     */
    public function getPages() {
        return $this->pages;
    }

    /**
     *
     */
    public function markRequestedMatches($request) {
        foreach ($request as $matchId) {
            list($pageId, $offset) = explode('#', $matchId);

            if (array_key_exists($pageId, $this->pages)) {
                $this->pages[$pageId]->markMatch($offset);
            }
        }
    }

    /**
     *
     */
    public function applyMatches($summary, $minorEdit) {
        $errors = array();

        foreach ($this->getPages() as $page) {
            if ($page->hasMarkedMatches()) {
                try {
                    $this->edits += $page->applyMatches($summary, $minorEdit);
                }
                catch (BatcheditPageApplyException $error) {
                    $errors[$page->getId()] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     *
     */
    private function getPageIndex() {
        global $conf;

        if (@file_exists($conf['indexdir'] . '/page.idx')) {
            require_once(DOKU_INC . 'inc/indexer.php');

            $index = idx_getIndex('page', '');

            if (count($index) == 0) {
                throw new Exception('err_emptyidx');
            }
        }
        else {
            throw new Exception('err_idxaccess');
        }

        return $index;
    }
}
