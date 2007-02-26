<?php
/**
 * Class that holds individual pages
 *
 * @author Matthew McNaney <mcnaney at gmail dot com>
 * @version $Id$
 */

PHPWS_Core::requireInc('webpage', 'error_defines.php');
PHPWS_Core::requireConfig('webpage', 'config.php');
PHPWS_Core::initModClass('webpage', 'Page.php');

if (!defined('WP_VOLUME_DATE_FORMAT')) {
    define('WP_VOLUME_DATE_FORMAT', '%c'); 
}

class Webpage_Volume {
    var $id             = 0;
    var $key_id         = 0;
    var $title          = null;
    var $summary        = null;
    var $date_created   = 0;
    var $date_updated   = 0;
    var $create_user_id = 0;
    var $created_user   = null;
    var $update_user_id = 0;
    var $updated_user   = null;
    var $frontpage      = false;
    var $approved       = 0;
    var $active         = 1;
    var $featured       = 0;
    var $_current_page  = 1;
    // array of pages indexed by order, value is id
    var $_key           = null;
    var $_pages         = null;
    var $_error         = null;
    var $_db            = null;

    function Webpage_Volume($id=null)
    {
        if (empty($id)) {
            return;
        }

        $this->id = (int)$id;
        $this->init();
        $this->loadPages();
    }

    function resetDB()
    {
        if (empty($this->_db)) {
            $this->_db = new PHPWS_DB('webpage_volume');
        } else {
            $this->_db->reset();
        }
    }

    function loadApprovalPages()
    {
        $this->loadPages();
        $approval = new Version_Approval('webpage', 'webpage_page', 'Webpage_Page');
        $approval->_db->addOrder('page_number');
        $pages = $approval->get();

        if (!empty($pages)) {
            foreach ($pages as $version) {
                $page = new Webpage_Page;
                $page->_volume = & $this;
                $version->loadObject($page);
                $this->_pages[$page->id] = $page;
            }
        }
    }

    function loadPages()
    {
        $db = new PHPWS_DB('webpage_page');
        $db->addWhere('volume_id', $this->id);
        if ($this->approved) {
            $db->addWhere('approved', 1);
        }
        $db->setIndexBy('id');
        $db->addOrder('page_number');
        $result = $db->getObjects('Webpage_Page');

        if (!empty($result)) {
            if (PEAR::isError($result)) {
                PHPWS_Error::log($result);
                return;
            } else {
                foreach ($result as $key => $page) {
                    $page->_volume = & $this;
                    $this->_pages[$key] = $page;
                }
            }
        }
    }

    function init()
    {
        $this->resetDB();
        $result = $this->_db->loadObject($this);
        if (PEAR::isError($result)) {
            $this->_error = $result;
            return;
        }
    }


    function getDateCreated($format=null)
    {
        if (empty($format)) {
            $format = WP_VOLUME_DATE_FORMAT;
        }

        return strftime($format, $this->date_created);
    }

    function getDateUpdated($format=null)
    {
        if (empty($format)) {
            $format = WP_VOLUME_DATE_FORMAT;
        }

        return strftime($format, $this->date_updated);
    }

    function setTitle($title)
    {
        $this->title = strip_tags($title);
    }

    function getTitle()
    {
        return sprintf('<a href="%s">%s</a>', $this->getViewLink(true), $this->title);
    }

    function setSummary($summary)
    {
        $this->summary = PHPWS_Text::parseInput($summary);
    }


    function getSummary()
    {
        return PHPWS_Text::parseOutput($this->summary);
    }

    function getTotalPages()
    {
        return count($this->_pages);
    }

    function post()
    {
        if (empty($_POST['title'])) {
            $errors[] = _('Missing page title');
        } else {
            $this->setTitle($_POST['title']);
        }

        if (empty($_POST['summary'])) {
            $this->summary = null;
        } else {
            $this->setSummary($_POST['summary']);
        }

        if (isset($_POST['volume_version_id']) || Current_User::isRestricted('webpage')) {
            $this->approved = 0;
        } else {
            $this->approved = 1;
        }

        if (isset($errors)) {
            return $errors;
        } else {
            return true;
        }
    }

    function getViewLink($base=false)
    {
        if ($base) {
            if (MOD_REWRITE_ENABLED) {
                return sprintf('webpage/%s', $this->id);
            } else {
                return 'index.php?module=webpage&amp;id=' . $this->id;
            }
        } else {
            return PHPWS_Text::rewriteLink(_('View'), 'webpage', $this->id);
        }
    }

    function getCurrentPage()
    {
        $page = $this->getPagebyNumber($this->_current_page);
        // Necessary for php 4
        $page->_volume->_current_page = $this->_current_page;
        return $page;
    }

    function getPageLink()
    {
        $page = $this->getCurrentPage();
        return $page->getPageLink();
    }

    function getPageUrl()
    {
        $page = $this->getCurrentPage();
        if ($page) {
            return $page->getPageUrl();
        } else {
            return null;
        }
    }

    /**
     * returns an associative array for the dbpager listing of volumes
     */
    function rowTags()
    {
        $vars['volume_id'] = $this->id;
        if (Current_User::allow('webpage', 'edit_page', $this->id, 'volume')) {
            $vars['wp_admin'] = 'edit_webpage';
            if (Current_User::isRestricted('webpage')) {
                $version = new Version('webpage_volume');
                $version->setSource($this);
                $approval_id = $version->isWaitingApproval();
                if ($approval_id) {
                    $vars['version_id'] = & $approval_id;
                }
            }
            $links[] = PHPWS_Text::secureLink(_('Edit'), 'webpage', $vars);
        }

        $links[] = $this->getViewLink();

        if (Current_User::isUnrestricted('webpage') && Current_User::allow('webpage', 'delete_page')) {
            $vars['wp_admin'] = 'delete_wp';
            $js_vars['QUESTION'] = sprintf(_('Are you sure you want to delete &quot;%s&quot and all its pages?'),
                                           $this->title);
            $js_vars['ADDRESS'] = PHPWS_Text::linkAddress('webpage', $vars, true);
            $js_vars['LINK'] = _('Delete');
            $links[] = javascript('confirm', $js_vars);
        }

        $tpl['DATE_CREATED'] = $this->getDateCreated();
        $tpl['DATE_UPDATED'] = $this->getDateUpdated();
        $tpl['ACTION']       = implode(' | ', $links);

        $tpl['TITLE'] = sprintf('<a href="%s">%s</a>', $this->getViewLink(true), $this->title);

        $tpl['CHECKBOX'] = sprintf('<input type="checkbox" name="webpage[]" id="webpage" value="%s" />', $this->id);

        if (Current_User::isUnrestricted('webpage') && Current_User::allow('webpage', 'delete_page')) {
            if ($this->frontpage) {
                $tpl['FRONTPAGE'] = _('Yes');
            } else {
                $tpl['FRONTPAGE'] = _('No');
            }

            if ($this->active) {
                $vars['wp_admin'] = 'deactivate_vol';
                $active = PHPWS_Text::secureLink(_('Yes'), 'webpage', $vars);
            } else {
                $vars['wp_admin'] = 'activate_vol';
                $active = PHPWS_Text::secureLink(_('No'), 'webpage', $vars);
            }
            $tpl['ACTIVE'] = $active;
        }

        return $tpl;
    }

    function delete()
    {
        $pagedb = new PHPWS_DB('webpage_page');
        $pagedb->addWhere('volume_id', $this->id);
        $result = $pagedb->delete();

        if (PEAR::isError($result)) {
            return $result;
        }

        $page_version = new PHPWS_DB('webpage_page_version');
        $page_version->addWhere('volume_id', $this->id);
        $page_version->delete();

        Key::drop($this->key_id);

        $this->resetDB();
        $this->_db->addWhere('id', $this->id);
        $result = $this->_db->delete();
        if (PEAR::isError($result)) {
            return $result;
        }

        Version::flush('webpage_volume', $this->id);
        
        return true;
    }

    function save()
    {
        PHPWS_Core::initModClass('version', 'Version.php');

        if (empty($this->title)) {
            return PHPWS_Error::get(WP_TPL_TITLE_MISSING, 'webpages', 'Volume::save');
        }

        $this->update_user_id = Current_User::getId();
        $this->updated_user   = Current_User::getUsername();
        $this->date_updated   = mktime();

        if (!$this->id) {
            $new_vol = true;
            $this->create_user_id = Current_User::getId();
            $this->created_user = Current_User::getUsername();
            $this->date_created = mktime();
        } else {
            $new_vol = false;
        }

        // If unapproved, we create an unapproved source volume
        if ($this->approved || !$this->id) {
            $this->resetDB();
            $result = $this->_db->saveObject($this);
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        if ($this->approved) {
            $update = (!$this->key_id) ? true : false;

            $key = $this->saveKey();
            if ($update) {
                $this->_db->saveObject($this);
            }
            $search = new Search($this->key_id);
            $search->addKeywords($this->title);
            $search->addKeywords($this->summary);
            $result = $search->save();
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        $version = new Version('webpage_volume');
        $version->setSource($this);
        $version->setApproved($this->approved);
        if ($this->approved) {
            $version->authorizeCreator($key);
        }

        return $version->save();
    }

    function saveKey()
    {
        if ($this->key_id) {
            $key = new Key($this->key_id);
        } else {
            $key = new Key;
            $key->setModule('webpage');
            $key->setItemName('volume');
            $key->setItemId($this->id);
            $key->setEditPermission('edit_page');
        }

        $key->active = (int)$this->active;
        $key->setTitle($this->title);
        $key->setSummary($this->summary);
        $key->setUrl($this->getViewLink(true));

        $result = $key->save();
        $this->key_id = $key->id;
        return $key;
    }


    function &getPagebyNumber($page_number)
    {
        if (!$page_number) {
            return null;
        }

        $page_number = (int)$page_number;

        if (empty($page_number) || empty($this->_pages)) {
            return null;
        }

        $i = 1;

        foreach ($this->_pages as $id => $page) {
            if ($page_number != $i) {
                $i++;
                continue;
            }
            return $page;
        }
    }

    function &getPagebyId($page_id)
    {
        if (!isset($this->_pages[(int)$page_id])) {
            return null;
        }
        return $this->_pages[(int)$page_id];
    }


    function getPageSelect($alist)
    {
        $form = new PHPWS_Form('page_select');
        $form->setMethod('get');
        $form->noAuthKey();
        $form->addHidden('module', 'webpage');
        $form->addHidden('id', $this->id);
        $form->addSelect('page', $alist);
        $form->setMatch('page', $this->_current_page);
        $form->setLabel('page', _('Page'));
        if (javascriptEnabled()) {
            $form->setExtra('page', 'onchange="this.form.submit()"');
        } else {
            $form->addSubmit('go', _('Go!'));
        }
        $formtpl = $form->getTemplate();
        return implode("\n", $formtpl);
    }

    function getTplTags($page_links=true, $version=0)
    {
        $template['PAGE_TITLE'] = $this->title;
        $template['SUMMARY'] = $this->getSummary();

        if ($page_links && count($this->_pages) > 1) {
            foreach ($this->_pages as $key => $page) {
                if ($this->_current_page == $page->page_number) {
                    $brief_link[] = $page->page_number;
                    $template['verbose-link'][] = array('VLINK' => $page->page_number . ' ' . $page->title);
                } else {
                    $brief_link[] = $page->getPageLink();
                    $template['verbose-link'][] = array('VLINK' => $page->getPageLink(true));
                }
                $alist[$page->page_number] = $page->title;
            }

            $template['PAGE_SELECT'] = $this->getPageSelect($alist);

            if ($this->_current_page > 1) {
                $page = $this->_current_page - 1;
                $template['PAGE_LEFT'] = PHPWS_Text::rewriteLink(WP_PAGE_LEFT, 'webpage', $this->id, $page);
                $template['PREVIOUS_PAGE']  = PHPWS_Text::rewriteLink(WP_PREVIOUS_PAGE, 'webpage', $this->id, $page);
            } 

            if ($this->_current_page < count($this->_pages)) {
                $page = $this->_current_page + 1;
                $template['PAGE_RIGHT'] = PHPWS_Text::rewriteLink(WP_PAGE_RIGHT, 'webpage', $this->id, $page);
                $template['NEXT_PAGE']  = PHPWS_Text::rewriteLink(WP_NEXT_PAGE, 'webpage', $this->id, $page);
            }

           
            $template['BRIEF_PAGE_LINKS'] = implode('&nbsp;', $brief_link);
            $template['PAGE_LABEL'] = _('Page');
        }

        if ( (Current_User::allow('webpage', 'edit_page') && Current_User::isUser($this->create_user_id)) || 
             Current_User::allow('webpage', 'edit_page', $this->id)) {
            $vars['wp_admin'] = 'edit_header';
            $vars['volume_id'] = $this->id;
            if ($version) {
                $vars['version_id'] = $version;
            }
            $template['EDIT_HEADER'] = PHPWS_Text::secureLink(_('Edit header'), 'webpage', $vars);
        }

        if (!$version && Current_User::allow('webpage', 'edit_page', null, null, true)) {
            $vars = array('wp_admin' => 'restore_volume', 'volume_id'=>$this->id);
            $template['RESTORE'] = PHPWS_Text::secureLink(_('Restore'), 'webpage', $vars);
            if ($this->featured) {
                $vars['wp_admin'] = 'unfeature';
            } else {
                $vars['wp_admin'] = 'feature';
            }
            $template['FEATURE'] = PHPWS_Text::secureLink(_('Feature'), 'webpage', $vars);
        }

        $result = Categories::getSimpleLinks($this->key_id);
        if (!empty($result)) {
            $template['CATEGORIES'] = implode(', ', $result);
        }

        return $template;
    }

    function viewHeader($version=0)
    {
        if (!$this->frontpage) {
            $this->flagKey();
        }
        $template = $this->getTplTags(false, $version);
        return PHPWS_Template::process($template, 'webpage', 'header.tpl');
    }

    function forceTemplate($template)
    {
        $template_dir = Webpage_Page::getTemplateDirectory();

        if (empty($this->id) || !is_file($template_dir . $template)) {
            return false;
        }

        $db = new PHPWS_DB('webpage_page');
        $db->addValue('template', $template);
        $db->addWhere('volume_id', $this->id);
        return $db->update();
    }
    
    function view($page=null)
    {
        $this->loadKey();

        if (!$this->_key->allowView()) {
            PHPWS_Core::errorPage('404');
        }

        Layout::addStyle('webpage');
        Layout::addPageTitle($this->title);

        if (!empty($page)) {
            $this->_current_page = (int)$page;
        }

        if (!empty($this->_pages)) {
            if ($page == 'all') {
                $content = $this->showAllPages();
            } else {
                $oPage = $this->getCurrentPage();

                if (!is_object($oPage)) {
                    PHPWS_Error::log(WP_PAGE_FROM_VOLUME, 'webpage', 'Webpage_Volume::view');
                    PHPWS_Core::errorPage();
                }
                $content = $oPage->view();
            }
        } else {
            $content = _('Page is not complete.');
        }

        $this->flagKey();
        return $content;
    }

    function showAllPages($admin=false)
    {
        $template = $this->getTplTags(false);
        foreach ($this->_pages as $page) {
            $template['multiple'][] = $page->getTplTags(false, false);
        }

        return PHPWS_Template::process($template, 'webpage', 'multiple/default.tpl');
    }

    function flagKey()
    {
        if ($this->frontpage) {
            $key = Key::getHomeKey();
            $key->flag();
            return;
        }
        $this->loadKey();
        $this->_key->flag();
    }

    function loadKey()
    {
        if (empty($this->_key)) {
            $this->_key = new Key($this->key_id);
        }
    }

    function joinAllPages()
    {
        foreach ($this->_pages as $page) {
            if (!isset($first_page)) {
                $first_page = $page;
                $all_content[] = $page->content;
                continue;
            }

            $all_content[] = '<h2>' . $page->title . '</h2>' . $page->content;
            $page->delete();
        }
        
        $first_page->content = implode("\n", $all_content);
        $first_page->save();
    }

    function joinPage($page_id)
    {
        if (!isset($this->_pages[$page_id])) {
            return true;
        } else {
            $source = $this->_pages[$page_id];
        }

        foreach ($this->_pages as $id => $page) {
            if ($id == $page_id) {
                break;
            }
        }
        
        $next_page = current($this->_pages);

        $source->content .= '&lt;br /&gt;&lt;h2&gt;' . $next_page->title . '&lt;/h2&gt;' . $next_page->content;
        $source->save();
        $result = $next_page->delete();
        if (PEAR::isError($result)) {
            PHPWS_Error::log($result);
            return false;
        } else {
            return true;
        }
        unset($this->_pages[$next_page->id]);
    }

    function dropPage($page_id)
    {
        if (!isset($this->_pages[$page_id])) {
            return true;
        }

        $this->_pages[$page_id]->delete();
        unset($this->_pages[$page_id]);
        Version::flush('webpage_page', $page_id);

        $count = 1;
        foreach ($this->_pages as $id => $page) {
            $page->page_number = $count;
            $page->save();
            $count++;
        }
    }

    function approval_view()
    {
        $template['TITLE'] = $this->title;
        $template['SUMMARY'] = $this->getSummary();
        return PHPWS_Template::process($template, 'webpage', 'approval_list.tpl');
    }

    function saveSearch()
    {
        $this->loadPages();
        if (empty($this->_pages)) {
            return true;
        }

        $search = new Search($this->key_id);
        $search->resetKeywords();
        foreach ($this->_pages as $page) {
            $content[] = $page->title;
            $content[] = $page->content;
        }

        $all_search_content[] = implode(' ', $content);
        $search->addKeywords($all_search_content);
        return $search->save();
    }

}

?>