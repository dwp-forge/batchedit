<?php

/**
 * Plugin BatchEdit: User interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditMessage {
    const ERROR = 1;
    const WARNING = 2;

    public $type;
    public $text;

    /**
     *
     */
    public function __construct($type, $text) {
        $this->type = $type;
        $this->text = $text;
    }

    /**
     *
     */
    public function getClass() {
        switch ($this->type) {
            case self::ERROR:
                return 'error';
            case self::WARNING:
                return 'notify';
        }

        return '';
    }

    /**
     *
     */
    public function getFormatId() {
        switch ($this->type) {
            case self::ERROR:
                return 'msg_error';
            case self::WARNING:
                return 'msg_warning';
        }

        return '';
    }
}

class BatcheditInterface {

    private $plugin;
    private $messages;
    private $indent;
    private $svgCache;

    /**
     *
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->messages = array();
        $this->indent = 0;
        $this->svgCache = array();
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function addErrorMessage($id) {
        $this->addMessage(BatcheditMessage::ERROR, func_get_args());
    }

    /**
     * Accepts message id followed by optional arguments.
     */
    public function addWarningMessage($id) {
        $this->addMessage(BatcheditMessage::WARNING, func_get_args());
    }

    /**
     *
     */
    public function configure($config) {
        foreach ($config->getConfig() as $id => $value) {
            if (!empty($value) || $value === 0) {
                $_REQUEST[$id] = $value;
            }
            else {
                unset($_REQUEST[$id]);
            }
        }
    }

    /**
     *
     */
    public function printBeginning($sessionId) {
        $this->ptln('<!-- batchedit -->');
        $this->ptln('<div id="batchedit">');

        $this->printJavascriptLang();

        $this->ptln('<form action="' . $_SERVER[REQUEST_URI] . '" method="post">');
        $this->ptln('<input type="hidden" name="session" value="' . $sessionId . '">');
    }

    /**
     *
     */
    public function printEnding() {
        $this->ptln('</form>');
        $this->ptln('</div>');
        $this->ptln('<!-- /batchedit -->');
    }

    /**
     *
     */
    public function printMessages() {
        if (empty($this->messages)) {
            return;
        }

        $this->ptln('<div id="be-messages">', +2);

        foreach($this->messages as $message) {
            $this->ptln('<div class="' . $message->getClass() . '">', +2);
            $this->ptln($this->getLang($message->getFormatId(), $message->text));
            $this->ptln('</div>', -2);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    public function printTotalStats($command, $matchCount, $pageCount, $editCount) {
        $matches = $this->getLangPlural('sts_matches', $matchCount);
        $pages = $this->getLangPlural('sts_pages', $pageCount);

        switch ($command) {
            case BatcheditRequest::COMMAND_PREVIEW:
                $stats = $this->getLang('sts_preview', $matches, $pages);
                break;

            case BatcheditRequest::COMMAND_APPLY:
                $edits = $this->getLangPlural('sts_edits', $editCount);
                $stats = $this->getLang('sts_apply', $matches, $pages, $edits);
                break;
        }

        $this->ptln('<div id="be-totalstats"><div>', +2);

        if ($editCount < $matchCount) {
            $this->printApplyCheckBox('be-applyall', $stats, 'ttl_applyall');
        }
        else {
            $this->ptln($stats);
        }

        $this->ptln('</div></div>', -2);
    }

    /**
     *
     */
    public function printMatches($pages) {
        foreach ($pages as $page) {
            $this->ptln('<div class="be-file">', +2);
            $this->printPageStats($page);
            $this->printPageActions($page->getId());
            $this->printPageMatches($page);
            $this->ptln('</div>', -2);
        }
    }

    /**
     *
     */
    public function printMainForm($enableApply) {
        $this->ptln('<div id="be-mainform">', +2);

        $this->ptln('<table>', +2);
        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_search', 'search');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');
        $this->ptln('</table>', -2);

        $this->printOptions();

        // Value for this hidden input is set before submit through jQuery, containing
        // JSON-encoded list of all checked checkbox ids for single matches.
        // Consolidating these inputs into a single string variable avoids problems for
        // huge replacement sets exceeding `max_input_vars` in `php.ini`.
        $this->ptln('<input type="hidden" name="apply" value="">');

        $this->printSubmitButton('cmd[preview]', 'btn_preview');
        $this->printSubmitButton('cmd[apply]', 'btn_apply', $enableApply);

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function addMessage($type, $arguments) {
        $this->messages[] = new BatcheditMessage($type, call_user_func_array(array($this, 'getLang'), $arguments));
    }

    /**
     *
     */
    private function printJavascriptLang() {
        $this->ptln('<script type="text/javascript">');

        $langIds = array('hnt_textsearch', 'hnt_textreplace', 'hnt_regexpsearch', 'hnt_regexpreplace',
                'hnt_advregexpsearch', 'war_nosummary');
        $lang = array();

        foreach ($langIds as $id) {
            $lang[$id] = $this->getLang($id);
        }

        $this->ptln('var batcheditLang = ' . json_encode($lang) . ';');
        $this->ptln('</script>');
    }

    /**
     *
     */
    private function printApplyCheckBox($id, $label, $title) {
        $this->ptln('<span class="be-apply" title="' . $this->getLang($title) . '">', +2);
        $this->ptln('<input type="checkbox" id="' . $id . '" />');
        $this->ptln('<label for="' . $id . '">' . $label . '</label>');
        $this->ptln('</span>', -2);
    }

    /**
     *
     */
    private function printPageStats($page) {
        $stats = $this->getLang('sts_page', $page->getId(), $this->getLangPlural('sts_matches', count($page->getMatches())));

        $this->ptln('<div class="be-stats">', +2);

        if ($page->hasUnmarkedMatches()) {
            $this->printApplyCheckBox($page->getId(), $stats, 'ttl_applyfile');
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

        $this->ptln('<div class="be-actions">', +2);
        $this->printAction($link, 'ttl_view', 'file-document');
        $this->printAction($link . '&do=edit', 'ttl_edit', 'pencil');
        $this->printAction('#be-mainform', 'ttl_mainform', 'arrow-down');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printAction($href, $titleId, $iconId) {
        $action = '<a class="be-action" href="' . $href . '" title="' . $this->getLang($titleId) . '">';
        $action .= $this->getSvg($iconId);
        $action .= '</a>';

        $this->ptln($action);
    }

    /**
     *
     */
    private function printPageMatches($page) {
        foreach ($page->getMatches() as $match) {
            $this->ptln('<div class="be-match">', +2);
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

        $this->ptln('<div class="be-matchid">', +2);

        if (!$match->isMarked()) {
            $this->printApplyCheckBox($id, $id, 'ttl_applymatch');
        }
        else {
            $this->ptln($id);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printMatchTable($match) {
        $original = $this->prepareText($match->getOriginalText(), 'search_hit' . ($match->isMarked() ? ' be-replaced' : ''));
        $replaced = $this->prepareText($match->getReplacedText(), 'search_hit' . ($match->isMarked() ? ' be-applied' : ''));
        $before = $this->prepareText($match->getContextBefore());
        $after = $this->prepareText($match->getContextAfter());

        $this->ptln('<table><tr>', +2);
        $this->ptln('<td class="be-text">' . $before . $original . $after . '</td>');
        $this->ptln('<td class="be-arrow">' . $this->getSvg('slide-arrow-right') . '</td>');
        $this->ptln('<td class="be-text">' . $before . $replaced . $after . '</td>');
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
    private function printFormEdit($title, $name) {
        $this->ptln('<tr>', +2);
        $this->ptln('<td class="be-title">' . $this->getLang($title) . '</td>');
        $this->ptln('<td class="be-edit">', +2);

        switch ($name) {
            case 'namespace':
                $this->printEditBox($name);
                break;

            case 'search':
            case 'replace':
                $multiline = isset($_REQUEST['multiline']);
                $placeholder = $this->getLang($this->getPlaceholderId($name));

                $this->printEditBox($name, FALSE, !$multiline, $placeholder);
                $this->printTextArea($name, $multiline, $placeholder);
                break;

            case 'summary':
                $this->printEditBox($name);
                $this->printCheckBox('minor', 'lbl_minor');
                break;
        }

        $this->ptln('</td>', -2);
        $this->ptln('</tr>', -2);
    }

    /**
     *
     */
    private function getPlaceholderId($editName) {
        switch ($editName) {
            case 'search':
                switch ($_REQUEST['searchmode']) {
                    case 'text':
                        return 'hnt_textsearch';
                    case 'regexp':
                        return isset($_REQUEST['advregexp']) ? 'hnt_advregexpsearch' : 'hnt_regexpsearch';
                }
            case 'replace':
                return 'hnt_' . $_REQUEST['searchmode'] . 'replace';
        }

        return '';
    }

    /**
     *
     */
    private function printOptions() {
        $this->ptln('<div id="be-options"><div>', +2);

        $this->ptln('<div class="be-radiogroup">', +2);
        $this->ptln('<div>' . $this->getLang('lbl_searchmode') . '</div>');
        $this->printRadioButton('searchmode', 'text', 'lbl_searchtext');
        $this->printRadioButton('searchmode', 'regexp', 'lbl_searchregexp');
        $this->ptln('</div>', -2);

        $this->printCheckBox('matchcase', 'lbl_matchcase');
        $this->printCheckBox('multiline', 'lbl_multiline');

        $this->ptln('</div></div>', -2);

        $this->ptln('<div class="be-actions">', +2);
        $this->printAction('javascript:openAdvancedOptions();', 'ttl_extoptions', 'settings');
        $this->ptln('</div>', -2);

        $this->ptln('<div id="be-extoptions">', +2);
        $this->ptln('<div class="be-actions">', +2);
        $this->printAction('javascript:closeAdvancedOptions();', '', 'close');
        $this->ptln('</div>', -2);

        $this->printCheckBox('advregexp', 'lbl_advregexp');
        $this->printSearchLimit();
        $this->printCheckBox('checksummary', 'lbl_checksummary');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printSearchLimit() {
        $this->ptln('<div class="be-checkbox">', +2);

        $html = '<input type="checkbox" id="be-searchlimit" name="searchlimit" value="on"';

        if (isset($_REQUEST['searchlimit'])) {
            $html .= ' checked="checked"';
        }

        $this->ptln($html . ' />');

        $label = explode('{1}', $this->getLang('lbl_searchlimit'));

        $this->ptln('<label for="be-searchlimit">' . $label[0] . '</label>');

        $html = '<input type="text" class="be-edit" id="be-searchmaxedit" name="searchmax"';

        if (isset($_REQUEST['searchmax'])) {
            $html .= ' value="' . $_REQUEST['searchmax'] . '"';
        }

        if (!isset($_REQUEST['searchlimit'])) {
            $html .= ' disabled="disabled"';
        }

        $this->ptln($html . ' />');

        $this->ptln('<label for="be-searchlimit">' . $label[1] . '</label>');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printEditBox($name, $submitted = TRUE, $visible = TRUE, $placeholder = '') {
        $html = '<input type="text" class="be-edit" id="be-' . $name . 'edit"';

        if ($submitted) {
            $html .= ' name="' . $name . '"';
        }

        if (!empty($placeholder)) {
            $html .= ' placeholder="' . $placeholder . '"';
        }

        if (($submitted || $visible) && isset($_REQUEST[$name])) {
            $html .= ' value="' . $_REQUEST[$name] . '"';
        }

        if (!$visible) {
            $html .= ' style="display: none;"';
        }

        $this->ptln($html . ' />');
    }

    /**
     *
     */
    private function printTextArea($name, $visible = TRUE, $placeholder = '') {
        $html = '<textarea class="be-edit" id="be-' . $name . 'area" name="' . $name . '"';

        if (!empty($placeholder)) {
            $html .= ' placeholder="' . $placeholder . '"';
        }

        $style = array();

        if (!$visible) {
            $style[] = 'display: none;';
        }

        if (isset($_REQUEST[$name . 'height'])) {
            $style[] = 'height: ' . $_REQUEST[$name . 'height'] . 'px;';
        }

        if (!empty($style)) {
            $html .= ' style="' . join(' ', $style) . '"';
        }

        $html .= '>';

        if (isset($_REQUEST[$name])) {
            $value = $_REQUEST[$name];

            // HACK: It seems that even with "white-space: pre" textarea trims one leading
            // empty line. To workaround this duplicate the empty line.
            if (preg_match("/^(\r?\n)/", $value, $match) == 1) {
                $value = $match[1] . $value;
            }

            $html .= $value;
        }

        $this->ptln($html . '</textarea>');
    }

    /**
     *
     */
    private function printCheckBox($name, $label) {
        $html = '<input type="checkbox" id="be-' . $name . '" name="' . $name . '" value="on"';

        if (isset($_REQUEST[$name])) {
            $html .= ' checked="checked"';
        }

        $this->ptln('<div class="be-checkbox">', +2);
        $this->ptln($html . ' />');
        $this->ptln('<label for="be-' . $name . '">' . $this->getLang($label) . '</label>');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printRadioButton($group, $name, $label) {
        $id = $group . $name;
        $html = '<input type="radio" id="be-' . $id . '" name="' . $group . '" value="' . $name . '"';

        if (isset($_REQUEST[$group]) && $_REQUEST[$group] == $name) {
            $html .= ' checked="checked"';
        }

        $this->ptln('<div class="be-radiobtn">', +2);
        $this->ptln($html . ' />');
        $this->ptln('<label for="be-' . $id . '">' . $this->getLang($label) . '</label>');
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printSubmitButton($name, $label, $enabled = TRUE) {
        $html = '<input type="submit" class="button be-submit" name="' . $name . '" value="' . $this->getLang($label) . '"';

        if (!$enabled) {
            $html .= ' disabled="disabled"';
        }

        $this->ptln($html . ' />');
    }

    /**
     *
     */
    private function getLang($id) {
        $string = $this->plugin->getLang($id);

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
    private function getLangPlural($id, $quantity) {
        if ($quantity == 1) {
            return $this->getLang($id . '#one', $quantity);
        }

        return $this->getLang($id . '#many', $quantity);
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
