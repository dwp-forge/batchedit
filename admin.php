<?php

/**
 * Plugin BatchEdit
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'admin.php');
require_once(DOKU_PLUGIN . 'batchedit/engine.php');
require_once(DOKU_PLUGIN . 'batchedit/request.php');

class admin_plugin_batchedit extends DokuWiki_Admin_Plugin {

    private $error;
    private $warning;
    private $request;
    private $engine;
    private $indent;
    private $svgCache;

    public function __construct() {
        $this->error = '';
        $this->warning = array();
        $this->request = NULL;
        $this->engine = NULL;
        $this->indent = 0;
        $this->svgCache = array();
    }

    /**
     *
     */
    public function getLang($id) {
        $string = parent::getLang($id);

        if (func_num_args() > 1) {
            $search = array();
            $replace = array();

            for ($i = 1; $i < func_num_args(); $i++) {
                $search[$i - 1] = '{' . $i . '}';
                $replace[$i - 1] = func_get_arg($i);
            }

            $string = str_replace($search, $replace, $string);
        }

        return $string;
    }

    /**
     *
     */
    public function getLangPlural($id, $quantity) {
        if ($quantity == 1) {
            return $this->getLang($id . '#one', $quantity);
        }

        return $this->getLang($id . '#many', $quantity);
    }

    /**
     * Handle user request
     */
    public function handle() {

        if (!isset($_REQUEST['cmd'])) {
            // First time - nothing to do
            return;
        }

        try {
            $this->handleRequest();
        }
        catch (Exception $error) {
            $this->error = $this->getLang($error->getMessage());
        }
    }

    /**
     * Output appropriate html
     */
    public function html() {
        global $ID;

        ptln('<!-- batchedit -->');
        ptln('<div id="batchedit">');

        $this->printMessages();

        ptln('<form action="' . wl($ID) . '" method="post">');

        if ($this->error == '' && !empty($this->request) && $this->engine->getMatchCount() > 0) {
            $this->printMatches();
        }

        $this->printMainForm();

        ptln('</form>');
        ptln('</div>');
        ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    private function handleRequest() {
        $this->request = new BatcheditRequest();
        $this->engine = new BatcheditEngine();

        $matches = $this->engine->findMatches($this->request->getNamespace(), $this->request->getRegexp(), $this->request->getReplacement());

        if ($matches == 0) {
            $this->warning[] = $this->getLang('war_nomatches');
        }
        elseif ($this->request->getCommand() == BatcheditRequest::COMMAND_APPLY && !empty($this->request->getAppliedMatches())) {
            $this->engine->markRequestedMatches($this->request->getAppliedMatches());

            $errors = $this->engine->applyMatches($this->request->getSummary(), $this->request->getMinorEdit());

            foreach ($errors as $pageId => $error) {
                if ($error instanceof BatcheditAccessControlException) {
                    $this->warning[] = $this->getLang('war_norights', $pageId);
                }
                elseif ($error instanceof BatcheditPageLockedException) {
                    $this->warning[] = $this->getLang('war_pagelock', $pageId, $error->lockedBy);
                }
            }
        }
    }

    /**
     *
     */
    private function printMatches() {
        $this->printTotalStats();

        foreach ($this->engine->getPages() as $page) {
            $this->ptln('<div class="file">', +2);
            $this->printPageStats($page);
            $this->printPageActions($page->getId());
            $this->printPageMatches($page);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printTotalStats() {
        $matches = $this->getLangPlural('sts_matches', $this->engine->getMatchCount());
        $pages = $this->getLangPlural('sts_pages', $this->engine->getPageCount());

        switch ($this->request->getCommand()) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $stats = $this->getLang('sts_preview', $matches, $pages);
                break;

            case BatcheditRequest::COMMAND_APPLY:
                $edits = $this->getLangPlural('sts_edits', $this->engine->getEditCount());
                $stats = $this->getLang('sts_apply', $matches, $pages, $edits);
                break;
        }

        $this->ptln('<div id="totalstats"><div>', +2);

        if ($this->engine->getEditCount() < $this->engine->getMatchCount()) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyall') . '">', +2);
            $this->ptln('<input type="checkbox" id="applyall" />');
            $this->ptln('<label for="applyall">' . $stats . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln($stats);
        }

        $this->ptln('</div></div>', -2);
    }

    /**
     *
     */
    private function printPageStats($page) {
        $stats = $this->getLang('sts_page', $page->getId(), $this->getLangPlural('sts_matches', count($page->getMatches())));

        $this->ptln('<div class="stats">', +2);

        if ($page->hasUnmarkedMatches()) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applyfile') . '">', +2);
            $this->ptln('<input type="checkbox" id="' . $page->getId() . '" />');
            $this->ptln('<label for="' . $page->getId() . '">' . $stats . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln($stats);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printPageActions($pageId) {
        $link = wl($pageId);

        $this->ptln('<div class="actions">', +2);
        $this->printAction($link, 'ttl_view', 'file-document');
        $this->printAction($link . '&do=edit', 'ttl_edit', 'pencil');
        $this->printAction('#mainform', 'ttl_mainform', 'arrow-down');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printAction($href, $titleId, $iconId) {
        $action = '<a class="action" href="' . $href . '" title="' . $this->getLang($titleId) . '">';
        $action .= $this->getSvg($iconId);
        $action .= '</a>';

        $this->ptln($action);
    }

    /**
     *
     */
    private function printPageMatches($page) {
        foreach ($page->getMatches() as $match) {
            $this->ptln('<div class="match">', +2);
            $this->printMatchHeader($page->getId(), $match);
            $this->printMatchTable($match);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printMatchHeader($pageId, $match) {
        $id = $pageId . '#' . $match->getPageOffset();

        if (!$match->isMarked()) {
            $this->ptln('<span class="apply" title="' . $this->getLang('ttl_applymatch') . '">', +2);
            $this->ptln('<input type="checkbox" id="' . $id . '" name="apply[' . $id . ']" value="on" />');
            $this->ptln('<label class="match-id" for="' . $id . '">' . $id . '</label>');
            $this->ptln('</span>', -2);
        }
        else {
            $this->ptln('<div class="match-id">' . $id . '</div>');
        }
    }

    /**
     *
     */
    private function printMatchTable($match) {
        $original = $this->prepareText($match->getOriginalText(), 'search_hit' . ($match->isMarked() ? ' replaced' : ''));
        $replaced = $this->prepareText($match->getReplacedText(), 'search_hit' . ($match->isMarked() ? ' applied' : ''));
        $before = $this->prepareText($match->getContextBefore());
        $after = $this->prepareText($match->getContextAfter());

        $this->ptln('<table><tr>', +2);
        $this->ptln('<td class="text">' . $before . $original . $after . '</td>');
        $this->ptln('<td class="arrow">' . $this->getSvg('slide-arrow-right') . '</td>');
        $this->ptln('<td class="text">' . $before . $replaced . $after . '</td>');
        $this->ptln('</tr></table>', -2);
    }

    /**
     * Prepare wiki text to be displayed as html
     */
    private function prepareText($text, $highlight = '') {
        $html = htmlspecialchars($text);
        $html = str_replace("\n", '<br />', $html);

        if ($highlight != '') {
            $html = '<span class="' . $highlight . '">' . $html . '</span>';
        }

        return $html;
    }

    /**
     *
     */
    private function printMessages() {
        if ((count($this->warning) > 0) || ($this->error != '')) {
            $this->ptln('<div id="messages">', +2);

            $this->printWarnings();

            if ($this->error != '') {
                $this->printError();
            }

            $this->ptln('</div>', -2);
            ptln('');
        }
    }

    /**
     *
     */
    private function printWarnings() {
        foreach($this->warning as $w) {
            $this->ptln('<div class="notify">', +2);
            $this->ptln('<b>Warning:</b> ' . $w);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    private function printError() {
        $this->ptln('<div class="error">', +2);
        $this->ptln('<b>Error:</b> ' . $this->error);
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printMainForm() {

        $this->ptln('<div id="mainform">', +2);

        // Output hidden values to ensure dokuwiki will return back to this plugin
        $this->ptln('<input type="hidden" name="do"   value="admin" />');
        $this->ptln('<input type="hidden" name="page" value="' . $this->getPluginName() . '" />');

        $this->ptln('<table>', +2);
        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_regexp', 'regexp');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');
        $this->ptln('</table>', -2);

        $this->ptln('<input type="submit" class="button" name="cmd[preview]"  value="' . $this->getLang('btn_preview') . '" />');
        $this->ptln('<input type="submit" class="button" name="cmd[apply]"  value="' . $this->getLang('btn_apply') . '" />');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printFormEdit($title, $name) {
        $value = '';

        if (isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];
        }

        $this->ptln('<tr>', +2);
        $this->ptln('<td class="title"><nobr><b>' . $this->getLang($title) . ':</b></nobr></td>');
        $this->ptln('<td class="edit"><input type="text" class="edit" name="' . $name . '" value="' . $value . '" /></td>');

        switch ($name) {
            case 'summary':
                $this->ptln('<td style="padding-left: 2em">', +2);
                $this->printCheckBox('lbl_minor', 'minor');
                $this->ptln('</td>', -2);
                break;

            case 'regexp':
                $this->ptln('<td style="padding-left: 2em">' . $this->getLang('inf_regexp') . '</td>');
                break;

            case 'replace':
                $this->ptln('<td style="padding-left: 2em">' . $this->getLang('inf_replace') . '</td>');
                break;

            default:
                $this->ptln('<td></td>');
                break;
        }

        $this->ptln('</tr>', -2);
    }

    /**
     *
     */
    private function printCheckBox($title, $name) {
        $html = '<input type="checkbox" id="' . $name . '" name="' . $name . '" value="on"';

        if (isset($_REQUEST[$name])) {
            $html .= ' checked="checked"';
        }

        $this->ptln($html . ' />');
        $this->ptln('<label for="' . $name . '">' . $this->getLang($title) . '</label>');
    }

    /**
     *
     */
    private function ptln($string, $indentDelta = 0) {
        if ($indentDelta < 0) {
            $this->indent += $indentDelta;
        }

        ptln($string, $this->indent);

        if ($indentDelta > 0) {
            $this->indent += $indentDelta;
        }
    }

    /**
     *
     */
    private function getSvg($id) {
        if (!array_key_exists($id, $this->svgCache)) {
            $this->svgCache[$id] = file_get_contents(DOKU_PLUGIN . 'batchedit/images/' . $id . '.svg');
        }

        return $this->svgCache[$id];
    }
}
