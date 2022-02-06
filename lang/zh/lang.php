<?php
/**
 * Plugin BatchEdit: Chinese(Simplified) language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     luminisward <luminis@vip.qq.com>
 */

// Settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// For admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = '批量修改';

$lang['err_invreq'] = '无效请求。';
$lang['err_nosearch'] = '未指定搜索条件。';
$lang['err_invregexp'] = '无效的正则表达式。';
$lang['err_emptyidx'] = '页面索引为空。';
$lang['err_idxaccess'] = '无法访问页面索引。';
$lang['err_emptyns'] = '在命名空间 "{1}" 中找不到页面。';
$lang['err_pregfailed'] = '正则表达式错误。';

$lang['war_nomatches'] = '找不到匹配文本。';
$lang['war_norights'] = '您没有权限编辑页面 {1}。';
$lang['war_pagelock'] = '页面 {1} 被用户 {2} 锁定。';
$lang['war_matchfail'] = 'Failed to apply match {1}.';
$lang['war_searchlimit'] = '达到了最大匹配数，搜索中断。';
$lang['war_timeout'] = '执行时间超过指定的时间限制，操作中断。';
$lang['war_cancelled'] = '用户请求中断操作。';
$lang['war_nosummary'] = '还未填写编辑摘要，确定要执行吗？';

$lang['msg_error'] = '<b>错误:</b> {1}';
$lang['msg_warning'] = '<b>警告:</b> {1}';

$lang['btn_preview'] = '预览';
$lang['btn_apply'] = '执行';
$lang['btn_cancel'] = '取消';

$lang['hnt_textsearch'] = 'wiki文本';
$lang['hnt_textreplace'] = 'DokuWiki';
$lang['hnt_regexpsearch'] = '\w+(Wiki)';
$lang['hnt_regexpreplace'] = 'Doku$1';
$lang['hnt_advregexpsearch'] = '/\w+(Wiki)/m';

$lang['lbl_ns'] = '命名空间';
$lang['lbl_search'] = '搜索';
$lang['lbl_replace'] = '替换为';
$lang['lbl_summary'] = '编辑摘要';
$lang['lbl_minor'] = '细微修改';
$lang['lbl_searchmode'] = '搜索模式';
$lang['lbl_searchtext'] = '纯文本';
$lang['lbl_searchregexp'] = '正则表达式';
$lang['lbl_matchcase'] = '区分大小写';
$lang['lbl_multiline'] = '多行模式';
$lang['lbl_advregexp'] = '在正则表达式中使用分隔符和修饰符';
$lang['lbl_matchctx'] = '显示匹配结果前后 {1} 个字符或 {2} 行';
$lang['lbl_searchlimit'] = '匹配到 {1} 个结果后停止搜索';
$lang['lbl_keepmarks'] = '再次预览后保留勾选 {1}';
$lang['lbl_keepmarks1'] = '当匹配的页面内容和替换结果相同时';
$lang['lbl_keepmarks2'] = '当匹配的页面内容跟上次相同时';
$lang['lbl_keepmarks3'] = '当页面中匹配位置跟上次相同时';
$lang['lbl_keepmarks4'] = '看运气！';
$lang['lbl_checksummary'] = '未填写编辑摘要时，执行前弹出确认框';
$lang['lbl_searching'] = '搜索中...';
$lang['lbl_applying'] = '执行中...';

$lang['sts_preview'] = '搜索结果: {2} 中有 {1} ';
$lang['sts_apply'] = '修改结果: {2} 中有 {1}, {3}';
$lang['sts_page'] = '{1} &ndash; {2}';
$lang['sts_matches#one'] = '{1} 个匹配';
$lang['sts_matches#many'] = '{1} 个匹配';
$lang['sts_pages#one'] = '{1} 个页面';
$lang['sts_pages#many'] = '{1} 个页面';
$lang['sts_edits#one'] = '替换了 {1} 处';
$lang['sts_edits#many'] = '替换了 {1} 处';

$lang['ttl_applyall'] = '勾选所有匹配';
$lang['ttl_applyfile'] = '勾选这个页面中的所有匹配';
$lang['ttl_applymatch'] = '勾选这个匹配';
$lang['ttl_view'] = '前往此页';
$lang['ttl_edit'] = '修改此页';
$lang['ttl_mainform'] = '到页尾';
$lang['ttl_extoptions'] = '高级选项';

$lang['dim_options'] = '10em';
$lang['dim_extoptions'] = '30em';
