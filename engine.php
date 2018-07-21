<?php

/**
 * Plugin BatchEdit: Search and replace engine
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'batchedit/interface.php');

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

class BatcheditMatch implements Serializable {

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
        $this->replacedText = preg_replace($regexp, $replacement, $text);
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
    public function serialize() {
        return serialize(array($this->pageOffset, $this->originalText, $this->replacedText,
                $this->contextBefore, $this->contextAfter, $this->marked));
    }

    /**
     *
     */
    public function unserialize($data) {
        list($this->pageOffset, $this->originalText, $this->replacedText,
                $this->contextBefore, $this->contextAfter, $this->marked) = unserialize($data);
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

class BatcheditPage implements Serializable {

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
    public function findMatches($regexp, $replacement, $limit) {
        $text = rawWiki($this->id);
        $count = @preg_match_all($regexp, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new Exception('err_pregfailed');
        }

        $interrupted = FALSE;

        if ($limit > 0 && $count > $limit) {
            $count = $limit;
            $interrupted = TRUE;
        }

        for ($i = 0; $i < $count; $i++) {
            $this->addMatch($text, $match[$i][0][1], $match[$i][0][0], $regexp, $replacement);
        }

        return $interrupted;
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
    public function serialize() {
        return serialize(array($this->id, $this->matches));
    }

    /**
     *
     */
    public function unserialize($data) {
        list($this->id, $this->matches) = unserialize($data);
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

class BatcheditSession {

    private $cacheDir;
    private $id;
    private $error;
    private $warnings;
    private $pages;
    private $matches;
    private $edits;

    /**
     *
     */
    public function __construct() {
        global $USERINFO;
        global $conf;

        $this->cacheDir = $conf['cachedir'] . '/batchedit';

        io_mkdir_p($this->cacheDir);

        $time = gettimeofday();

        $this->id = md5($time['sec'] . $time['usec'] . $USERINFO['name'] . $USERINFO['mail']);
        $this->error = NULL;
        $this->warnings = array();
        $this->pages = array();
        $this->matches = 0;
        $this->edits = 0;
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
    public function load($request, $config) {
        $this->id = $request->getSessionId();

        $properties = $this->loadArray('props');

        if (!is_array($properties) || !empty(array_diff_assoc($properties, $this->getProperties($request, $config)))) {
            return FALSE;
        }

        $matches = $this->loadArray('matches');

        if (!is_array($matches)) {
            return FALSE;
        }

        list($this->warnings, $this->matches, $this->pages) = $matches;

        return TRUE;
    }

    /**
     *
     */
    public function save($request, $config) {
        $this->saveArray('props', $this->getProperties($request, $config));
        $this->saveArray('matches', array($this->warnings, $this->matches, $this->pages));
    }

    /**
     *
     */
    public function expire() {
        @unlink($this->getCacheName('props'));
        @unlink($this->getCacheName('matches'));
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function setError($messageId) {
        $this->error = new BatcheditErrorMessage(func_get_args());
        $this->pages = array();
        $this->matches = 0;
        $this->edits = 0;
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function addWarning($messageId) {
        $this->warnings[] = new BatcheditWarningMessage(func_get_args());
    }

    /**
     *
     */
    public function getMessages() {
        if ($this->error != NULL) {
            return array($this->error);
        }

        return $this->warnings;
    }

    /**
     *
     */
    public function addPage($page) {
        $this->pages[$page->getId()] = $page;
        $this->matches += count($page->getMatches());
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
    public function addEdits($edits) {
        $this->edits += $edits;
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
    private function getProperties($request, $config) {
        global $USERINFO;

        $properties = array();

        $properties['username'] = $USERINFO['name'];
        $properties['usermail'] = $USERINFO['mail'];
        $properties['namespace'] = $request->getNamespace();
        $properties['regexp'] = $request->getRegexp();
        $properties['replacement'] = $request->getReplacement();
        $properties['searchlimit'] = $config->getConf('searchlimit') ? $config->getConf('searchmax') : 0;

        return $properties;
    }

    /**
     *
     */
    private function getCacheName($ext) {
        return $this->cacheDir . '/' . $this->id . '.' . $ext;
    }

    /**
     *
     */
    private function saveArray($name, $array) {
        file_put_contents($this->getCacheName($name), serialize($array));
    }

    /**
     *
     */
    private function loadArray($name) {
        return @unserialize(file_get_contents($this->getCacheName($name)));
    }
}

class BatcheditEngine {

    private $session;
    private $matches;
    private $edits;

    /**
     *
     */
    public function __construct($session) {
        $this->session = $session;
    }

    /**
     *
     */
    public function findMatches($namespace, $regexp, $replacement, $limit) {
        if ($namespace != '') {
            $pattern = '/^' . $namespace . '/';
        }
        else {
            $pattern = '';
        }

        $interrupted = FALSE;

        foreach ($this->getPageIndex() as $pageId) {
            $pageId = trim($pageId);

            if (($pattern == '') || (preg_match($pattern, $pageId) == 1)) {
                $page = new BatcheditPage($pageId);
                $interrupted = $page->findMatches($regexp, $replacement, $limit - $this->session->getMatchCount());

                if (count($page->getMatches()) > 0) {
                    $this->session->addPage($page);
                }

                if ($interrupted) {
                    break;
                }
            }
        }

        return $interrupted;
    }

    /**
     *
     */
    public function markRequestedMatches($request) {
        $pages = $this->session->getPages();

        foreach ($request as $matchId) {
            list($pageId, $offset) = explode('#', $matchId);

            if (array_key_exists($pageId, $pages)) {
                $pages[$pageId]->markMatch($offset);
            }
        }
    }

    /**
     *
     */
    public function applyMatches($summary, $minorEdit) {
        $errors = array();

        foreach ($this->session->getPages() as $page) {
            if ($page->hasMarkedMatches()) {
                try {
                    $this->session->addEdits($page->applyMatches($summary, $minorEdit));
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
