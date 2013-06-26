<?php
    /**
     * @class  pageAdminView
     * @author Arnia (dev@karybu.org)
     * @brief page admin view of the module class
     **/

    class pageAdminView extends page {

        var $module_srl = 0;
        var $list_count = 20;
        var $page_count = 10;

        /**
         * @brief Initialization
         **/
        function init() {
            // Pre-check if module_srl exists. Set module_info if exists
            $module_srl = Context::get('module_srl');
            // Create module model object
            $oModuleModel = &getModel('module');
            // module_srl two come over to save the module, putting the information in advance
            if($module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
                if(!$module_info) {
                    Context::set('module_srl','');
                    $this->act = 'list';
                } else {
                    $moduleModel = new ModuleModel();
                    $moduleModel->syncModuleToSite($module_info);
                    //ModuleModel::syncModuleToSite($module_info);
                    $this->module_info = $module_info;
                    Context::set('module_info',$module_info);
                }
            }
            // Get a list of module categories
            $module_category = $oModuleModel->getModuleCategories();
            Context::set('module_category', $module_category);
			//Security
			$security = new Security();
			$security->encodeHTML('module_category..title');

			// Get a template path (page in the administrative template tpl putting together)
            $this->setTemplatePath($this->module_path.'tpl');

        }
        public function getGrid(){
            $categories = array();
            $moduleCategories = Context::get('module_category');
            if (is_array($moduleCategories)) {
                foreach ($moduleCategories as $key=>$category){
                    $categories[$key] = isset($category->title)?$category->title:'';
                }
            }
            global $lang;
            $grid = new Karybu\Grid\Backend();
            $grid->setShowOrderNumberColumn(true);
            $grid->setMassSelectName('cart');
            $grid->setMassSelectClass('module_srl');
            $grid->addColumn('module_category_srl', 'options', array(
                'index' => 'module_category_srl',
                'header'=> $lang->module_category,
                'options'=>$categories,
                'default'=>$lang->not_exists,
                'sortable'=>false
            ));
            $grid->addColumn('page_type', 'text', array(
                'index'=>'page_type',
                'header'=>$lang->page_type,
                'sortable'=>false
            ));
            $grid->addColumn('mid', 'text', array(
                'index'=>'mid',
                'header'=>$lang->mid
            ));
            $grid->addColumn('browser_title', 'link', array(
                'index'=>'browser_title',
                'header'=>$lang->browser_title,
                'link_key' => 'url'
            ));
            $grid->addColumn('regdate', 'date', array(
                'index'=>'regdate',
                'header'=>$lang->regdate,
                'format' => 'Y-m-d'
            ));
            $grid->addColumn('actions', 'action', array(
                'index'         => 'actions',
                'header'        => $lang->actions,
                'wrapper_top'   => '<div class="kActionIcons">',
                'wrapper_bottom'=> '</div>'
            ));
            //configure action
            $actionConfig = array(
                'title'=>$lang->cmd_view,
                'url_params'=>array('module_srl'=>'module_srl'),
                'module'=>'admin',
                'act'=>'dispPageAdminInfo',
                'icon_class' => 'kConfigure'
            );
            $action = new \Karybu\Grid\Action\Action($actionConfig);
            $grid->getColumn('actions')->addAction('configure',$action);
            //copy action
            $actionConfig = array(
                'title'=>$lang->cmd_copy,
                'url_params'=>array('module_srl'=>'module_srl'),
                'module'=>'admin',
                'act'=>'dispModuleAdminCopyModule',
                'icon_class' => 'kCopy',
                'popup'=>true
            );
            $action = new \Karybu\Grid\Action\Action($actionConfig);
            $grid->getColumn('actions')->addAction('copy',$action);
            //delete action
            $actionConfig = array(
                'title'=>$lang->cmd_delete,
                'url_params'=>array('module_srl'=>'module_srl'),
                'module'=>'admin',
                'act'=>'dispPageAdminDelete',
                'icon_class' => 'kDelete',
            );
            $action = new \Karybu\Grid\Action\Action($actionConfig);
            $grid->getColumn('actions')->addAction('delete',$action);

            return $grid;
        }
        /**
         * @brief Manage a list of pages showing
         **/
        function dispPageAdminContent() {
            $args = new stdClass();
            //$args->sort_index = "module_srl";
            $args->page = Context::get('page');
            $sort_index = Context::get('sort_index');
            $sort_order = Context::get('sort_order');

            $grid = $this->getGrid();

            $grid->setSortIndex($sort_index);
            $grid->setSortOrder($sort_order);

            $args->sort_index = $grid->getSortIndex();
            $args->sort_order = $grid->getSortOrder();
            $args->list_count = 40;
            $args->page_count = 10;
            $args->s_module_category_srl = Context::get('module_category_srl');

			$s_mid = Context::get('s_mid');
			if($s_mid) $args->s_mid = $s_mid;

			$s_browser_title = Context::get('s_browser_title');
			if($s_browser_title) $args->s_browser_title = $s_browser_title;

            $output = executeQuery('page.getPageList', $args);
			$oModuleModel = &getModel('module');
			$page_list = $oModuleModel->addModuleExtraVars($output->data);
            $moduleModel = new moduleModel();
            $moduleModel->syncModuleToSite($page_list);

            foreach ($output->data as $key=>$item){
                $output->data[$key]->mass_select_value = $item->module_srl;
                $output->data[$key]->url = getSiteUrl($item->domain,'','mid',$item->mid);
            }

            // To write to a template context:: set
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
			//Security
			$security = new Security();
			$security->encodeHTML('page_list..browser_title');
			$security->encodeHTML('page_list..mid');
			$security->encodeHTML('module_info.');
            $grid->setRows($output->data);
            $grid->setTotalCount($output->total_count);
            $grid->setTotalPages($output->total_page);
            $grid->setCurrentPage($output->page);
            Context::set('grid', $grid);
			// Set a template file
            $this->setTemplateFile('index');
        }

        /**
         * @brief Information output of the selected page
         **/
        function dispPageAdminInfo() {
            // Get module_srl by GET parameter
            $module_srl = Context::get('module_srl');
            $module_info = Context::get('module_info');
            // If you do not value module_srl just showing the index page
            if(!$module_srl) return $this->dispPageAdminContent();
            // If the layout is destined to add layout information haejum (layout_title, layout)
            if($module_info->layout_srl) {
                $oLayoutModel = &getModel('layout');
                $layout_info = $oLayoutModel->getLayout($module_info->layout_srl);
                $module_info->layout = $layout_info->layout;
                $module_info->layout_title = $layout_info->layout_title;
            }
            // Get a layout list
            $oLayoutModel = &getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);
            // Set a template file

			if ($this->module_info->page_type == 'ARTICLE'){
				$oModuleModel = &getModel('module');
				$skin_list = $oModuleModel->getSkins($this->module_path);
				Context::set('skin_list',$skin_list);

				$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
				Context::set('mskin_list', $mskin_list);
			}

			//Security
			$security = new Security();
			$security->encodeHTML('layout_list..layout');
			$security->encodeHTML('layout_list..title');
			$security->encodeHTML('mlayout_list..layout');
			$security->encodeHTML('mlayout_list..title');
			$security->encodeHTML('module_info.');

            $this->setTemplateFile('page_info');
        }

        /**
         * @brief Additional settings page showing
         * For additional settings in a service module in order to establish links with other modules peyijiim
         **/
        function dispPageAdminPageAdditionSetup() {
            // call by reference content from other modules to come take a year in advance for putting the variable declaration
            $content = '';

            $oEditorView = &getView('editor');
            $oEditorView->triggerDispEditorAdditionSetup($content);
            Context::set('setup_content', $content);
            // Set a template file
            $this->setTemplateFile('addition_setup');

			$security = new Security();
			$security->encodeHTML('module_info.');
        }

        /**
         * @brief Add Page Form Output
         **/
        function dispPageAdminInsert() {
            // Get module_srl by GET parameter
            $module_srl = Context::get('module_srl');
            // Get and set module information if module_srl exists
            if($module_srl) {
                $oModuleModel = &getModel('module');
				$columnList = array('module_srl', 'mid', 'module_category_srl', 'browser_title', 'layout_srl', 'use_mobile', 'mlayout_srl');
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
                if($module_info->module_srl == $module_srl) Context::set('module_info',$module_info);
                else {
                    unset($module_info);
                    unset($module_srl);
                }
            }
            // Get a layout list
            $oLayoutModel = &getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

			$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);

			//Security
			$security = new Security();
			$security->encodeHTML('layout_list..layout');
			$security->encodeHTML('layout_list..title');
			$security->encodeHTML('mlayout_list..layout');
			$security->encodeHTML('mlayout_list..title');

            // Set a template file
            $this->setTemplateFile('page_insert');
        }

		function dispPageAdminMobileContent() 
		{
			if($this->module_info->page_type == 'OUTSIDE')
			{
				return $this->stop(-1, 'msg_invalid_request');
			}

            if($this->module_srl) 
			{
				Context::set('module_srl',$this->module_srl);
			}

			$oPageMobile = &getMobile('page');
			$oPageMobile->module_info = $this->module_info;
			$page_type_name = strtolower($this->module_info->page_type);
			$method = '_get' . ucfirst($page_type_name) . 'Content';
			if (method_exists($oPageMobile, $method))
			{
				$page_content = $oPageMobile->{$method}();
			}
			else
			{
				return new Object(-1, sprintf('%s method is not exists', $method));
			}

            Context::set('module_info', $this->module_info);
            Context::set('page_content', $page_content);

            $this->setTemplateFile('mcontent');
		}

		function dispPageAdminMobileContentModify() 
		{
            Context::set('module_info', $this->module_info);

			if ($this->module_info->page_type == 'WIDGET')
			{
				$this->_setWidgetTypeContentModify(true);
			}
			else if ($this->module_info->page_type == 'ARTICLE')
			{
				$this->_setArticleTypeContentModify(true);
			}
		}

        /**
         * @brief Edit Page Content
         **/
        function dispPageAdminContentModify() {
            // Set the module information
            Context::set('module_info', $this->module_info);

			if ($this->module_info->page_type == 'WIDGET')
			{
				$this->_setWidgetTypeContentModify();
			}
			else if ($this->module_info->page_type == 'ARTICLE')
			{
				$this->_setArticleTypeContentModify();
			}
        }


		function _setWidgetTypeContentModify($isMobile = false) 
		{
            // Setting contents
			if($isMobile)
			{
				$content = Context::get('mcontent');
				if(!$content) $content = $this->module_info->mcontent;
				$templateFile = 'page_mobile_content_modify';
			}
			else
			{
				$content = Context::get('content');
				if(!$content) $content = $this->module_info->content;
				$templateFile = 'page_content_modify';
			}

            Context::set('content', $content);
            // Convert them to teach the widget
            $oWidgetController = &getController('widget');
            $content = $oWidgetController->transWidgetCode($content, true, !$isMobile);
			// $content = str_replace('$', '&#36;', $content);
            Context::set('page_content', $content);
            // Set widget list
            $oWidgetModel = &getModel('widget');
            $widget_list = $oWidgetModel->getDownloadedWidgetList();
            Context::set('widget_list', $widget_list);

			//Security
			$security = new Security();
			$security->encodeHTML('widget_list..title','module_info.mid');

            // Set a template file
            $this->setTemplateFile($templateFile);
        }

		function _setArticleTypeContentModify($isMobile = false) 
		{
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument(0, true);

			if($isMobile)
			{
				Context::set('isMobile', 'Y');
				$target = 'mdocument_srl';
			}
			else
			{
				Context::set('isMobile', 'N');
				$target = 'document_srl';
			}
			
			if (!empty($this->module_info->{$target}))
			{
                $document_srl = $this->module_info->{$target};
                $oDocument->setDocument($document_srl);
                Context::set('document_srl', $document_srl);
			} else {
                $document_srl = Context::get('document_srl');
                $oDocument->setDocument($document_srl);
                Context::set('document_srl', $document_srl);
            }

            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_article.xml');
			Context::set('oDocument', $oDocument);
			Context::set('mid', $this->module_info->mid);
            $this->setTemplateFile('article_content_modify');
		}

        /**
         * @brief Delete page output
         **/
        function dispPageAdminDelete() {
            $module_srl = Context::get('module_srl');
            if(!$module_srl) {
                return $this->dispContent();
            }
            $oModuleModel = &getModel('module');
			$columnList = array('module_srl', 'module', 'mid');
            $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
            Context::set('module_info',$module_info);
            // Set a template file
            $this->setTemplateFile('page_delete');
			$security = new Security();
			$security->encodeHTML('module_info.');
        }

        /**
         * @brief Rights Listing
         **/
        function dispPageAdminGrantInfo() {
            // Common module settings page, call rights
            $oModuleAdminModel = &getAdminModel('module');
            $moduleSrl = isset($this->module_info->module_srl) ? $this->module_info->module_srl : null;
            $grant = isset($this->xml_info->grant) ? $this->xml_info->grant : null;
            $grant_content = $oModuleAdminModel->getModuleGrantHTML($moduleSrl, $grant);
            Context::set('grant_content', $grant_content);

            $this->setTemplateFile('grant_list');

			$security = new Security();
			$security->encodeHTML('module_info.');
        }

		/**
		 * Display skin setting page
		 */
		function dispPageAdminSkinInfo()
		{
			$oModuleAdminModel = &getAdminModel('module');
			$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
			Context::set('skin_content', $skin_content);

			$this->setTemplateFile('skin_info');
		}

		/**
		 * Display mobile skin setting page
		 */
		function dispPageAdminMobileSkinInfo()
		{
			$oModuleAdminModel = &getAdminModel('module');
			$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
			Context::set('skin_content', $skin_content);

			$this->setTemplateFile('skin_info');
		}
    }
?>
