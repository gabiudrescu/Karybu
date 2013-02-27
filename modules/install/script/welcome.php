<?php

// ko/en/...


/**
 * Class responsible for generating the menu and the pages
 * that will exist on a first time installation
 */
class DatasetCreator {

    public function createNewMenu($title) {
        $menu = new stdClass();
        $menu->site_srl = 0;
        $menu->title = $title;
        $menu->menu_srl = getNextSequence();
        $menu->listorder = $menu->menu_srl * -1;

        $output = executeQuery('menu.insertMenu', $menu);
        if(!$output->toBool()) {
            // TODO Log menu creation error
            return $output;
        }

        return new DatasetMenu($menu->menu_srl, $title);
    }

    /**
     * Currently supports just layouts with one menu
     * Can easily be updated to support different main and bottom menu
     * by adding an extra parameter
     *
     * @param DatasetMenu $main_menu
     * @return mixed
     */
    public function createNewLayout($layout_name, $layout_title, DatasetMenu $main_menu) {
        // create Layout
        //extra_vars init
        $extra_vars = new stdClass();
        $extra_vars->colorset = 'default';
        $extra_vars->main_menu = $main_menu->getSrl();
        $extra_vars->bottom_menu = $main_menu->getSrl();
        $extra_vars->menu_name_list = array();
        $extra_vars->menu_name_list[$main_menu->getSrl()] = $main_menu->getTitle();

        $args = new stdClass();
        $args->site_srl = 0;
        $layout_srl = $args->layout_srl = getNextSequence();
        $args->layout = $layout_name;
        $args->title = $layout_title;
        $args->layout_type = 'P';

        $oLayoutAdminController = &getAdminController('layout');
        $output = $oLayoutAdminController->insertLayout($args);
        if(!$output->toBool()) return $output;

        // update Layout
        $args->extra_vars = serialize($extra_vars);
        $output = $oLayoutAdminController->updateLayout($args);
        if(!$output->toBool()) return $output;

        return $layout_srl;
    }

    public function createNewArticlePage($layout_srl, $browser_title, $mid, $article_page_title) {
        // 1. Insert page module
        $page_args = new stdClass();
        $page_args->layout_srl = $layout_srl;
        $page_args->browser_title = $browser_title;
        $page_args->module = 'page';
        $page_args->mid = $mid;
        $page_args->module_category_srl = 0;
        $page_args->page_caching_interval = 0;
        $page_args->page_type = 'ARTICLE';
        $page_args->skin = 'default';

        $oModuleController = &getController('module');
        $output = $oModuleController->insertModule($page_args);

        if(!$output->toBool()) return $output;

        // 2. Insert document associated with this page
        $oTemplateHandler = &TemplateHandler::getInstance();
        $oDocumentController = &getController('document');
        $lang = Context::getLangType();

        $module_srl = $output->get('module_srl');

        $obj = new stdClass();
        $obj->module_srl = $module_srl;
        Context::set('version', __ZBXE_VERSION__);
        $obj->title = $article_page_title;
        $obj->content = $oTemplateHandler->compile('./modules/install/script/welcome_content', $mid.'_'.$lang);

        $output = $oDocumentController->insertDocument($obj);
        if(!$output->toBool()) return $output;

        // 3. Update page to point to the newly created document
        $document_srl = $output->get('document_srl');

        $oModuleModel = &getModel('module');
        $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
        $module_info->document_srl = $document_srl;
        $output = $oModuleController->updateModule($module_info);
        if(!$output->toBool()) return $output;

        return $module_srl;
    }

    public function setHomepage($module_srl) {
        $site_args = new stdClass();
        $site_args->site_srl = 0;
        $site_args->index_module_srl = $module_srl;

        $oModuleController = getController('module');
        $oModuleController->updateSite($site_args);
    }

}

class DatasetMenu {
    private $menu_srl;
    private $title;

    public function __construct($menu_srl, $title) {
        $this->menu_srl = $menu_srl;
        $this->title = $title;
    }

    public function addItem($title, $url = null, $parent_srl = null) {
        $menu_item = new stdClass();
        $menu_item->menu_srl = $this->menu_srl;
        $menu_item->name = $title;
        $menu_item->menu_item_srl = getNextSequence();
        $menu_item->listorder = -1 * $menu_item->menu_item_srl;
        if($parent_srl) $menu_item->parent_srl = $parent_srl;
        if($url) $menu_item->url = $url;

        $output = executeQuery('menu.insertMenuItem', $menu_item);
        if(!$output->toBool()) {
            // TODO Log menu creation error
            return $output;
        }

        return $menu_item->menu_item_srl;
    }

    public function save() {
        $oMenuAdminController = &getAdminController('menu');
        $oMenuAdminController->makeXmlFile($this->menu_srl);
    }

    public function getTitle() {
        return $this->title;
    }

    public function getSrl() {
        return $this->menu_srl;
    }
}

$dataset_creator = new DatasetCreator();

// 1. Create the new menu
$menu = $dataset_creator->createNewMenu('welcome_menu');

$home_srl = $menu->addItem('Welcome to XE', 'welcome');
$styles_srl = $menu->addItem('Styles');
$text_styles_srl = $menu->addItem('Text styles', 'text_styles', $styles_srl);
$form_styles_srl = $menu->addItem('Form styles', 'form_styles', $styles_srl);
$table_styles_srl = $menu->addItem('Table styles', 'table_styles', $styles_srl);
$icon_glyphs_srl = $menu->addItem('Icon glyphs', 'icon_glyphs', $styles_srl);
$documentation_srl = $menu->addItem('Documentation', 'documentation');
$codebook_srl = $menu->addItem('Codebook', 'http://www.xpressengine.org/help_and_support');

$menu->save();

// 2. Create a new layout
$layout_srl = $dataset_creator->createNewLayout('xe_official', 'Welcome layout', $menu);

// 3. Create new pages
$welcome_page = $dataset_creator->createNewArticlePage($layout_srl, 'Welcome to XE', 'welcome', 'Welcome to XE');
$text_styles_page = $dataset_creator->createNewArticlePage($layout_srl, 'Text styles', 'text_styles', 'Text styles');
$form_styles_page = $dataset_creator->createNewArticlePage($layout_srl, 'Form styles', 'form_styles', 'Form styles');
$table_styles_page = $dataset_creator->createNewArticlePage($layout_srl, 'Table styles', 'table_styles', 'Table styles');
$icon_glyphs_page = $dataset_creator->createNewArticlePage($layout_srl, 'Icon glyphs', 'icon_glyphs', 'Icon glyphs');
$documentation_page = $dataset_creator->createNewArticlePage($layout_srl, 'Documentation', 'documentation', 'Documentation');

// 4. Set homepage
$dataset_creator->setHomepage($welcome_page);

