<?php

/**
 * Plugin BatchEdit: Search and replace engine
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

require_once(DOKU_PLUGIN . 'batchedit/interface.php');

class BatcheditException extends Exception {

    private $arguments;

    /**
     * Accepts message id followed by optional arguments.
     */
    public function __construct($messageId) {
        parent::__construct($messageId);

        $this->arguments = func_get_args();
    }

    /**
     *
     */
    public function getArguments() {
        return $this->arguments;
    }
}

class BatcheditEmptyNamespaceException extends BatcheditException {

    /**
     *
     */
    public function __construct($namespace) {
        parent::__construct('err_emptyns', $namespace);
    }
}

class BatcheditPageApplyException extends BatcheditException {

    /**
     * Accepts message and page ids followed by optional arguments.
     */
    public function __construct($messageId, $pageId) {
        call_user_func_array('parent::__construct', func_get_args());
    }
}

class BatcheditAccessControlException extends BatcheditPageApplyException {

    /**
     *
     */
    public function __construct($pageId) {
        parent::__construct('war_norights', $pageId);
    }
}

class BatcheditPageLockedException extends BatcheditPageApplyException {

    /**
     *
     */
    public function __construct($pageId, $lockedBy) {
        parent::__construct('war_pagelock', $pageId, $lockedBy);
    }
}

class BatcheditMatchApplyException extends BatcheditPageApplyException {

    /**
     *
     */
    public function __construct($matchId) {
        parent::__construct('war_matchfail', $matchId);
    }
}

class BatcheditMatch implements Serializable {

    private $pageOffset;
    private $originalText;
    private $replacedText;
    private $contextBefore;
    private $contextAfter;
    private $marked;
    private $applied;

    /**
     *
     */
    public function __construct($pageText, $pageOffset, $text, $regexp, $replacement, $contextChars, $contextLines) {
        $this->pageOffset = $pageOffset;
        $this->originalText = $text;
        $this->replacedText = preg_replace($regexp, $replacement, $text);
        $this->contextBefore = $this->cropContextBefore($pageText, $pageOffset, $contextChars, $contextLines);
        $this->contextAfter = $this->cropContextAfter($pageText, $pageOffset + strlen($text), $contextChars, $contextLines);
        $this->marked = FALSE;
        $this->applied = FALSE;
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
        $currentText = substr($pageText, $pageOffset, strlen($this->originalText));

        if ($currentText != $this->originalText) {
            throw new BatcheditMatchApplyException('#' . $this->pageOffset);
        }

        $before = substr($pageText, 0, $pageOffset);
        $after = substr($pageText, $pageOffset + strlen($this->originalText));

        $this->applied = TRUE;

        return $before . $this->replacedText . $after;
    }

    /**
     *
     */
    public function rollback() {
        $this->applied = FALSE;
    }

    /**
     *
     */
    public function isApplied() {
        return $this->applied;
    }

    /**
     *
     */
    public function serialize() {
        return serialize(array($this->pageOffset, $this->originalText, $this->replacedText,
                $this->contextBefore, $this->contextAfter, $this->marked, $this->applied));
    }

    /**
     *
     */
    public function unserialize($data) {
        list($this->pageOffset, $this->originalText, $this->replacedText,
                $this->contextBefore, $this->contextAfter, $this->marked, $this->applied) = unserialize($data);
    }

    /**
     *
     */
    private function cropContextBefore($pageText, $pageOffset, $contextChars, $contextLines) {
        if ($contextChars == 0) {
            return '';
        }

        $context = utf8_substr(substr($pageText, 0, $pageOffset), -$contextChars);
        $count = preg_match_all('/\n/', $context, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > $contextLines) {
            $context = substr($context, $match[$count - $contextLines - 1][0][1] + 1);
        }

        return $context;
    }

    /**
     *
     */
    private function cropContextAfter($pageText, $pageOffset, $contextChars, $contextLines) {
        if ($contextChars == 0) {
            return '';
        }

        $context = utf8_substr(substr($pageText, $pageOffset), 0, $contextChars);
        $count = preg_match_all('/\n/', $context, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count > $contextLines) {
            $context = substr($context, 0, $match[$contextLines][0][1]);
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
    public function findMatches($regexp, $replacement, $limit, $contextChars, $contextLines) {
        $text = rawWiki($this->id);
        $count = @preg_match_all($regexp, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($count === FALSE) {
            throw new BatcheditException('err_pregfailed');
        }

        $interrupted = FALSE;

        if ($limit >= 0 && $count > $limit) {
            $count = $limit;
            $interrupted = TRUE;
        }

        for ($i = 0; $i < $count; $i++) {
            $this->addMatch($text, $match[$i][0][1], $match[$i][0][0], $regexp, $replacement,
                    $contextChars, $contextLines);
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
    public function hasUnappliedMatches() {
        $result = FALSE;

        foreach ($this->matches as $match) {
            if (!$match->isApplied()) {
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
            $this->rollbackMatches();

            if ($error instanceof BatcheditMatchApplyException) {
                $error = new BatcheditMatchApplyException($this->id . $error->getArguments()[1]);
            }

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
    private function addMatch($text, $offset, $matched, $regexp, $replacement, $contextChars, $contextLines) {
        $this->matches[$offset] = new BatcheditMatch($text, $offset, $matched, $regexp, $replacement, $contextChars, $contextLines);
    }

    /**
     *
     */
    private function lock() {
        if (auth_quickaclcheck($this->id) < AUTH_EDIT) {
            throw new BatcheditAccessControlException($this->id);
        }

        $lockedBy = checklock($this->id);

        if ($lockedBy != FALSE) {
            throw new BatcheditPageLockedException($this->id, $lockedBy);
        }

        lock($this->id);
    }

    /**
     *
     */
    private function rollbackMatches() {
        foreach ($this->matches as $match) {
            $match->rollback();
        }
    }
}

class BatcheditSessionCache {

    const PRUNE_PERIOD = 3600;

    private $expirationTime;

    /**
     *
     */
    public static function getFileName($name, $ext = '') {
        global $conf;

        return $conf['cachedir'] . '/batchedit/' . $name . (!empty($ext) ? '.' . $ext : '');
    }

    /**
     *
     */
    public function __construct($expirationTime) {
        $this->expirationTime = $expirationTime;

        io_mkdir_p(dirname(self::getFileName('dummy')));
    }

    /**
     *
     */
    public function __destruct() {
        $this->prune();
    }

    /**
     *
     */
    public function save($id, $name, $data) {
        file_put_contents(self::getFileName($id, $name), serialize($data));
    }

    /**
     *
     */
    public function load($id, $name) {
        return @unserialize(file_get_contents(self::getFileName($id, $name)));
    }

    /**
     *
     */
    public function isValid($id) {
        global $conf;

        $propsTime = @filemtime(self::getFileName($id, 'props'));
        $matchesTime = @filemtime(self::getFileName($id, 'matches'));

        if ($propsTime === FALSE || $matchesTime === FALSE) {
            return FALSE;
        }

        $now = time();

        if ($propsTime + $this->expirationTime < $now || $matchesTime + $this->expirationTime < $now) {
            return FALSE;
        }

        $changeLogTime = @filemtime($conf['changelog']);

        if ($changeLogTime !== FALSE && ($changeLogTime > $propsTime || $changeLogTime > $matchesTime)) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     *
     */
    public function expire($id) {
        @unlink(self::getFileName($id, 'props'));
        @unlink(self::getFileName($id, 'matches'));
        @unlink(self::getFileName($id, 'progress'));
        @unlink(self::getFileName($id, 'cancel'));
    }

    /**
     *
     */
    private function prune() {
        $marker = self::getFileName('_prune');
        $lastPrune = @filemtime($marker);
        $now = time();

        if ($lastPrune !== FALSE && $lastPrune + self::PRUNE_PERIOD > $now) {
            return;
        }

        $directory = new GlobIterator(self::getFileName('*.*'));
        $expired = array();

        foreach ($directory as $fileInfo) {
            if ($fileInfo->getMTime() + $this->expirationTime < $now) {
                $expired[pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME)] = TRUE;
            }
        }

        foreach ($expired as $id => $dummy) {
            $this->expire($id);
        }

        @touch($marker);
    }
}

class BatcheditSession {

    private $id;
    private $error;
    private $warnings;
    private $pages;
    private $matches;
    private $edits;
    private $cache;

    private static $persistentWarnings = array(
        'war_nomatches',
        'war_searchlimit'
    );

    /**
     *
     */
    public function __construct($expirationTime) {
        $this->id = $this->generateId();
        $this->error = NULL;
        $this->warnings = array();
        $this->pages = array();
        $this->matches = 0;
        $this->edits = 0;
        $this->cache = new BatcheditSessionCache($expirationTime);
    }

    /**
     *
     */
    public function setId($id) {
        $this->id = $id;

        @unlink(BatcheditSessionCache::getFileName($id, 'cancel'));
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
        $this->setId($request->getSessionId());

        if (!$this->cache->isValid($this->id)) {
            return FALSE;
        }

        $properties = $this->loadArray('props');

        if (!is_array($properties) || !empty(array_diff_assoc($properties, $this->getProperties($request, $config)))) {
            return FALSE;
        }

        $matches = $this->loadArray('matches');

        if (!is_array($matches)) {
            return FALSE;
        }

        list($warnings, $this->matches, $this->pages) = $matches;

        $this->warnings = array_filter($warnings, function ($message) {
            return in_array($message->getId(), self::$persistentWarnings);
        });

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
        $this->cache->expire($this->id);
    }

    /**
     *
     */
    public function setError($error) {
        $this->error = new BatcheditErrorMessage($error->getArguments());
        $this->pages = array();
        $this->matches = 0;
        $this->edits = 0;
    }

    /**
     * Accepts BatcheditException instance or message id followed by optional arguments.
     */
    public function addWarning($warning) {
        if ($warning instanceof BatcheditException) {
            $this->warnings[] = new BatcheditWarningMessage($warning->getArguments());
        }
        else {
            $this->warnings[] = new BatcheditWarningMessage(func_get_args());
        }
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
    public function getCachedPages() {
        if (!$this->cache->isValid($this->id)) {
            return array();
        }

        $matches = $this->loadArray('matches');

        return is_array($matches) ? $matches[2] : array();
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
    private function generateId() {
        global $USERINFO;

        $time = gettimeofday();

        return md5($time['sec'] . $time['usec'] . $USERINFO['name'] . $USERINFO['mail']);
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
        $properties['matchctx'] = $config->getConf('matchctx') ? $config->getConf('ctxchars') . ',' . $config->getConf('ctxlines') : 0;

        return $properties;
    }

    /**
     *
     */
    private function saveArray($name, $array) {
        $this->cache->save($this->id, $name, $array);
    }

    /**
     *
     */
    private function loadArray($name) {
        return $this->cache->load($this->id, $name);
    }
}

abstract class BatcheditMarkPolicy {

    protected $pages;

    /**
     *
     */
    public function __construct($pages) {
        $this->pages = $pages;
    }

    /**
     *
     */
    abstract public function markMatch($pageId, $offset);
}

class BatcheditMarkPolicyVerifyBoth extends BatcheditMarkPolicy {

    protected $cache;

    /**
     *
     */
    public function __construct($pages, $cache) {
        parent::__construct($pages);

        $this->cache = $cache;
    }

    /**
     *
     */
    public function markMatch($pageId, $offset) {
        if (!array_key_exists($pageId, $this->pages) || !array_key_exists($pageId, $this->cache)) {
            return;
        }

        $matches = $this->pages[$pageId]->getMatches();
        $cache = $this->cache[$pageId]->getMatches();

        if (!array_key_exists($offset, $matches) || !array_key_exists($offset, $cache)) {
            return;
        }

        if ($this->compareMatches($matches[$offset], $cache[$offset])) {
            $this->pages[$pageId]->markMatch($offset);
        }
    }

    /**
     *
     */
    protected function compareMatches($match, $cache) {
        return $match->getOriginalText() == $cache->getOriginalText() &&
                $match->getReplacedText() == $cache->getReplacedText();
    }
}

class BatcheditMarkPolicyVerifyMatched extends BatcheditMarkPolicyVerifyBoth {

    /**
     *
     */
    protected function compareMatches($match, $cache) {
        return $match->getOriginalText() == $cache->getOriginalText();
    }
}

class BatcheditMarkPolicyVerifyOffset extends BatcheditMarkPolicy {

    /**
     *
     */
    public function markMatch($pageId, $offset) {
        if (array_key_exists($pageId, $this->pages)) {
            $this->pages[$pageId]->markMatch($offset);
        }
    }
}

class BatcheditMarkPolicyVerifyContext extends BatcheditMarkPolicy {

    /**
     *
     */
    public function markMatch($pageId, $offset) {
        if (!array_key_exists($pageId, $this->pages)) {
            return;
        }

        if (array_key_exists($offset, $this->pages[$pageId]->getMatches())) {
            $this->pages[$pageId]->markMatch($offset);

            return;
        }

        $minDelta = PHP_INT_MAX;
        $minOffset = -1;

        foreach ($this->pages[$pageId]->getMatches() as $match) {
            $matchOffset = $match->getPageOffset();

            if ($offset < $matchOffset - strlen($match->getContextBefore())) {
                continue;
            }

            if ($offset >= $matchOffset + strlen($match->getOriginalText()) + strlen($match->getContextAfter())) {
                continue;
            }

            $delta = abs($matchOffset - $offset);

            if ($delta >= $minDelta) {
                break;
            }

            $minDelta = $delta;
            $minOffset = $matchOffset;
        }

        if ($minDelta != PHP_INT_MAX) {
            $this->pages[$pageId]->markMatch($minOffset);
        }
    }
}

class BatcheditProgress {

    const UNKNOWN = 0;
    const SEARCH = 1;
    const APPLY = 2;
    const SCALE = 1000;
    const SAVE_PERIOD = 0.25;

    private $fileName;
    private $operation;
    private $range;
    private $progress;
    private $lastSave;

    /**
     *
     */
    public function __construct($sessionId, $operation = self::UNKNOWN, $range = 0) {
        $this->fileName = BatcheditSessionCache::getFileName($sessionId, 'progress');
        $this->operation = $operation;
        $this->range = $range;
        $this->progress = 0;
        $this->lastSave = 0;

        if ($this->operation != self::UNKNOWN && $this->range > 0) {
            $this->save();
        }
    }

    /**
     *
     */
    public function update($progressDelta = 1) {
        $this->progress += $progressDelta;

        if (microtime(TRUE) > $this->lastSave + self::SAVE_PERIOD) {
            $this->save();
        }
    }

    /**
     *
     */
    public function get() {
        $progress = @filesize($this->fileName);

        if ($progress === FALSE) {
            return array(self::UNKNOWN, 0);
        }

        if ($progress <= self::SCALE) {
            return array(self::SEARCH, $progress);
        }

        return array(self::APPLY, $progress - self::SCALE);
    }

    /**
     *
     */
    private function save() {
        $progress = max(round(self::SCALE * $this->progress / $this->range), 1);

        if ($this->operation == self::APPLY) {
            $progress += self::SCALE;
        }

        @file_put_contents($this->fileName, str_pad('', $progress, '.'));

        $this->lastSave = microtime(TRUE);
    }
}

class BatcheditEngine {

    const VERIFY_BOTH = 1;
    const VERIFY_MATCHED = 2;
    const VERIFY_OFFSET = 3;
    const VERIFY_CONTEXT = 4;

    // These constants are used to take into account the time that plugin spends outside
    // of the engine. For example, this can be time spent by DokuWiki itself, time for
    // request parsing, session loading and saving, etc.
    const NON_ENGINE_TIME_RATIO = 0.1;
    const NON_ENGINE_TIME_MAX = 5;

    private $session;
    private $startTime;
    private $timeLimit;

    /**
     *
     */
    public static function cancelOperation($sessionId) {
        @touch(BatcheditSessionCache::getFileName($sessionId, 'cancel'));
    }

    /**
     *
     */
    public function __construct($session) {
        $this->session = $session;
        $this->startTime = time();
        $this->timeLimit = $this->getTimeLimit();
    }

    /**
     *
     */
    public function findMatches($namespace, $regexp, $replacement, $limit, $contextChars, $contextLines) {
        $index = $this->getPageIndex($namespace);
        $progress = new BatcheditProgress($this->session->getId(), BatcheditProgress::SEARCH, count($index));

        foreach ($index as $pageId) {
            $page = new BatcheditPage(trim($pageId));
            $interrupted = $page->findMatches($regexp, $replacement, $limit - $this->session->getMatchCount(),
                    $contextChars, $contextLines);

            if (count($page->getMatches()) > 0) {
                $this->session->addPage($page);
            }

            $progress->update();

            if ($interrupted) {
                $this->session->addWarning('war_searchlimit');
                break;
            }

            if ($this->isOperationTimedOut()) {
                $this->session->addWarning('war_timeout');
                break;
            }

            if ($this->isOperationCancelled()) {
                $this->session->addWarning('war_cancelled');
                break;
            }
        }

        if ($this->session->getMatchCount() == 0) {
            $this->session->addWarning('war_nomatches');
        }
    }

    /**
     *
     */
    public function markRequestedMatches($request, $policy = self::VERIFY_OFFSET) {
        switch ($policy) {
            case self::VERIFY_BOTH:
                $policy = new BatcheditMarkPolicyVerifyBoth($this->session->getPages(), $this->session->getCachedPages());
                break;

            case self::VERIFY_MATCHED:
                $policy = new BatcheditMarkPolicyVerifyMatched($this->session->getPages(), $this->session->getCachedPages());
                break;

            case self::VERIFY_OFFSET:
                $policy = new BatcheditMarkPolicyVerifyOffset($this->session->getPages());
                break;

            case self::VERIFY_CONTEXT:
                $policy = new BatcheditMarkPolicyVerifyContext($this->session->getPages());
                break;
        }

        foreach ($request as $matchId) {
            list($pageId, $offset) = explode('#', $matchId);

            $policy->markMatch($pageId, $offset);
        }
    }

    /**
     *
     */
    public function applyMatches($summary, $minorEdit) {
        $progress = new BatcheditProgress($this->session->getId(), BatcheditProgress::APPLY,
                array_reduce($this->session->getPages(), function ($marks, $page) {
                    return $marks + ($page->hasMarkedMatches() && $page->hasUnappliedMatches() ? 1 : 0);
                }, 0));

        foreach ($this->session->getPages() as $page) {
            if (!$page->hasMarkedMatches() || !$page->hasUnappliedMatches()) {
                continue;
            }

            try {
                $this->session->addEdits($page->applyMatches($summary, $minorEdit));
            }
            catch (BatcheditPageApplyException $error) {
                $this->session->addWarning($error);
            }

            $progress->update();

            if ($this->isOperationTimedOut()) {
                $this->session->addWarning('war_timeout');
                break;
            }

            if ($this->isOperationCancelled()) {
                $this->session->addWarning('war_cancelled');
                break;
            }
        }
    }

    /**
     *
     */
    private function getPageIndex($namespace) {
        global $conf;

        if (!@file_exists($conf['indexdir'] . '/page.idx')) {
            throw new BatcheditException('err_idxaccess');
        }

        require_once(DOKU_INC . 'inc/indexer.php');

        $index = idx_getIndex('page', '');

        if (count($index) == 0) {
            throw new BatcheditException('err_emptyidx');
        }

        if ($namespace != '') {
            if ($namespace == ':') {
                $pattern = "\033^[^:]+$\033";
            }
            else {
                $pattern = "\033^" . $namespace . "\033";
            }

            $index = array_filter($index, function ($pageId) use ($pattern) {
                return preg_match($pattern, $pageId) == 1;
            });

            if (count($index) == 0) {
                throw new BatcheditEmptyNamespaceException($namespace);
            }
        }

        return $index;
    }

    /**
     *
     */
    private function getTimeLimit() {
        $timeLimit = ini_get('max_execution_time');
        $timeLimit -= ceil(min($timeLimit * self::NON_ENGINE_TIME_RATIO, self::NON_ENGINE_TIME_MAX));

        return $timeLimit;
    }

    /**
     *
     */
    private function isOperationTimedOut() {
        // On different systems max_execution_time can be used in diffenent ways: it can track
        // either real time or only user time excluding all system calls. Here we enforce real
        // time limit, which could be more strict then what PHP would do, but is easier to
        // implement in a cross-platform way and easier for a user to understand.
        return time() - $this->startTime >= $this->timeLimit;
    }

    /**
     *
     */
    private function isOperationCancelled() {
        return file_exists(BatcheditSessionCache::getFileName($this->session->getId(), 'cancel'));
    }
}
