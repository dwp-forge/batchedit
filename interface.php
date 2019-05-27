<?php

/**
 * Plugin BatchEdit: User interface
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <dwpforge@gmail.com>
 */

class BatcheditMessage implements Serializable {
    const ERROR = 1;
    const WARNING = 2;

    public $type;
    public $data;

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

    /**
     *
     */
    public function getId() {
        return $this->data[0];
    }

    /**
     *
     */
    public function serialize() {
        return serialize(array($this->type, $this->data));
    }

    /**
     *
     */
    public function unserialize($data) {
        list($this->type, $this->data) = unserialize($data);
    }
}

class BatcheditErrorMessage extends BatcheditMessage {

    /**
     * Accepts message array that starts with message id followed by optional arguments.
     */
    public function __construct($message) {
        $this->type = self::ERROR;
        $this->data = $message;
    }
}

class BatcheditWarningMessage extends BatcheditMessage {

    /**
     * Accepts message array that starts with message id followed by optional arguments.
     */
    public function __construct($message) {
        $this->type = self::WARNING;
        $this->data = $message;
    }
}

class BatcheditInterface {

    private $plugin;
    private $indent;
    private $svgCache;

    /**
     *
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->indent = 0;
        $this->svgCache = array();
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

        $this->ptln('<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">');
        $this->ptln('<input type="hidden" name="session" value="' . $sessionId . '" />');
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
    public function printMessages($messages) {
        if (empty($messages)) {
            return;
        }

        $this->ptln('<div id="be-messages">', +2);

        foreach ($messages as $message) {
            $this->ptln('<div class="' . $message->getClass() . '">', +2);
            $this->ptln($this->getLang($message->getFormatId(), call_user_func_array(array($this, 'getLang'), $message->data)));
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

        $this->ptln('<div>', +2);

        $this->ptln('<table>', +2);
        $this->printFormEdit('lbl_ns', 'namespace');
        $this->printFormEdit('lbl_search', 'search');
        $this->printFormEdit('lbl_replace', 'replace');
        $this->printFormEdit('lbl_summary', 'summary');
        $this->ptln('</table>', -2);

        $this->printOptions();

        $this->ptln('</div>', -2);

        // Value for this hidden input is set before submit through jQuery, containing
        // JSON-encoded list of all checked checkbox ids for single matches.
        // Consolidating these inputs into a single string variable avoids problems for
        // huge replacement sets exceeding `max_input_vars` in `php.ini`.
        $this->ptln('<input type="hidden" name="apply" value="" />');

        $this->ptln('<div id="be-submitbar">', +2);
        $this->printSubmitButton('cmd[preview]', 'btn_preview');
        $this->printSubmitButton('cmd[apply]', 'btn_apply', $enableApply);
        $this->ptln('<div id="be-progressbar">', +2);
        $this->ptln('<div id="be-progresswrap"><div id="be-progress"></div></div>');
        $this->printButton('cancel', 'btn_cancel');
        $this->ptln('</div>', -2);
        $this->ptln('</div>', -2);

        $this->ptln('</div>', -2);
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
    private function printApplyCheckBox($id, $label, $title, $checked = FALSE) {
        $checked = $checked ? ' checked="checked"' : '';

        $this->ptln('<span class="be-apply" title="' . $this->getLang($title) . '">', +2);
        $this->ptln('<input type="checkbox" id="' . $id . '"' . $checked . ' />');
        $this->ptln('<label for="' . $id . '">' . $label . '</label>');
        $this->ptln('</span>', -2);
    }

    /**
     *
     */
    private function printPageStats($page) {
        $stats = $this->getLang('sts_page', $page->getId(), $this->getLangPlural('sts_matches', count($page->getMatches())));

        $this->ptln('<div class="be-stats">', +2);

        if ($page->hasUnappliedMatches()) {
            $this->printApplyCheckBox($page->getId(), $stats, 'ttl_applyfile', !$page->hasUnmarkedMatches());
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
        $this->printAction($link . (strpos($link, '?') === FALSE ? '?' : '&') . 'do=edit', 'ttl_edit', 'pencil');
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

        if (!$match->isApplied()) {
            $this->printApplyCheckBox($id, $id, 'ttl_applymatch', $match->isMarked());
        }
        else {
            // Add hidden checked checkbox to ensure that marked status is not lost on
            // applied matches if application is performed in multiple rounds. This can
            // be the case when one apply command is timed out and user issues a second
            // one to apply the remaining matches.
            $this->ptln('<input type="checkbox" id="' . $id . '" checked="checked" style="display:none;" />');
            $this->ptln($id);
        }

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printMatchTable($match) {
        $original = $this->prepareText($match->getOriginalText(), $match->isApplied() ? ' be-replaced' : 'be-preview');
        $replaced = $this->prepareText($match->getReplacedText(), $match->isApplied() ? ' be-applied' : 'be-preview');
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

                $this->printEditBox($name, FALSE, TRUE, !$multiline, $placeholder);
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
        $style = 'min-width: ' . $this->getLang('dim_options') . ';';

        $this->ptln('<div id="be-options" style="' . $style . '">', +2);

        $this->ptln('<div class="be-radiogroup">', +2);
        $this->ptln('<div>' . $this->getLang('lbl_searchmode') . '</div>');
        $this->printRadioButton('searchmode', 'text', 'lbl_searchtext');
        $this->printRadioButton('searchmode', 'regexp', 'lbl_searchregexp');
        $this->ptln('</div>', -2);

        $this->printCheckBox('matchcase', 'lbl_matchcase');
        $this->printCheckBox('multiline', 'lbl_multiline');

        $this->ptln('</div>', -2);

        $this->ptln('<div class="be-actions">', +2);
        $this->printAction('javascript:openAdvancedOptions();', 'ttl_extoptions', 'settings');
        $this->ptln('</div>', -2);

        $style = 'width: ' . $this->getLang('dim_extoptions') . ';';

        $this->ptln('<div id="be-extoptions" style="' . $style . '">', +2);
        $this->ptln('<div class="be-actions">', +2);
        $this->printAction('javascript:closeAdvancedOptions();', '', 'close');
        $this->ptln('</div>', -2);

        $this->printCheckBox('advregexp', 'lbl_advregexp');
        $this->printCheckBox('matchctx', 'printMatchContextLabel');
        $this->printCheckBox('searchlimit', 'printSearchLimitLabel');
        $this->printCheckBox('keepmarks', 'printKeepMarksLabel');
        $this->printCheckBox('checksummary', 'lbl_checksummary');

        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printMatchContextLabel() {
        $label = preg_split('/(\{\d\})/', $this->getLang('lbl_matchctx'), -1, PREG_SPLIT_DELIM_CAPTURE);
        $edits = array('{1}' => 'ctxchars', '{2}' => 'ctxlines');

        $this->printLabel('matchctx', $label[0]);
        $this->printEditBox($edits[$label[1]], TRUE, isset($_REQUEST['matchctx']));
        $this->printLabel('matchctx', $label[2]);
        $this->printEditBox($edits[$label[3]], TRUE, isset($_REQUEST['matchctx']));
        $this->printLabel('matchctx', $label[4]);
    }

    /**
     *
     */
    private function printSearchLimitLabel() {
        $label = explode('{1}', $this->getLang('lbl_searchlimit'));

        $this->printLabel('searchlimit', $label[0]);
        $this->printEditBox('searchmax', TRUE, isset($_REQUEST['searchlimit']));
        $this->printLabel('searchlimit', $label[1]);
    }

    /**
     *
     */
    private function printKeepMarksLabel() {
        $label = explode('{1}', $this->getLang('lbl_keepmarks'));
        $disabled = isset($_REQUEST['keepmarks']) ? '' : ' disabled="disabled"';

        $this->printLabel('keepmarks', $label[0]);
        $this->ptln('<select name="markpolicy"' . $disabled . '>', +2);

        for ($i = 1; $i <= 4; $i++) {
            $selected = $_REQUEST['markpolicy'] == $i ? ' selected="selected"' : '';

            $this->ptln('<option value="' . $i . '"' . $selected . '>' . $this->getLang('lbl_keepmarks' . $i) . '</option>');
        }

        $this->ptln('</select>', -2);
        $this->printLabel('keepmarks', $label[1]);
    }

    /**
     *
     */
    private function printEditBox($name, $submitted = TRUE, $enabled = TRUE, $visible = TRUE, $placeholder = '') {
        $html = '<input type="text" class="be-edit" id="be-' . $name . 'edit"';

        if ($submitted) {
            $html .= ' name="' . $name . '"';
        }

        if (!empty($placeholder)) {
            $html .= ' placeholder="' . $placeholder . '"';
        }

        if (($submitted || $visible) && isset($_REQUEST[$name])) {
            $html .= ' value="' . htmlspecialchars($_REQUEST[$name]) . '"';
        }

        if (!$enabled) {
            $html .= ' disabled="disabled"';
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

            $html .= htmlspecialchars($value);
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
        $this->printLabel($name, $label);
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
        $this->printLabel($id, $label);
        $this->ptln('</div>', -2);
    }

    /**
     *
     */
    private function printLabel($name, $label) {
        if (substr($label, 0, 5) == 'print') {
            $this->$label();
        }
        else {
            if (substr($label, 0, 4) == 'lbl_') {
                $label = $this->getLang($label);
            }
            else {
                $label = trim($label);
            }

            if (!empty($label)) {
                $this->ptln('<label for="be-' . $name . '">' . $label . '</label>');
            }
        }
    }

    /**
     *
     */
    private function printSubmitButton($name, $label, $enabled = TRUE) {
        $html = '<input type="submit" class="button be-button be-submit" name="' . $name . '" value="' . $this->getLang($label) . '"';

        if (!$enabled) {
            $html .= ' disabled="disabled"';
        }

        $this->ptln($html . ' />');
    }

    /**
     *
     */
    private function printButton($name, $label) {
        $this->ptln('<input type="button" class="button be-button" name="' . $name . '" value="' . $this->getLang($label) . '" />');
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
