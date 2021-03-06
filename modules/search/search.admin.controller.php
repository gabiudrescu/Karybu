<?php
    /**
     * @class  searchAdminController
     * @author NHN (developers@xpressengine.com)
     * @brief admin view class of the search module
     *
     * Search Management
     *
     **/

    class searchAdminController extends search {
        /**
         * @brief Initialization
         **/
        function init() {}

        /**
         * @brief Save Settings
         **/
        function procSearchAdminInsertConfig() {
            // Get configurations (using module model object)
            $oModuleModel = getModel('module');
            $config = $oModuleModel->getModuleConfig('search');

            $args = new stdClass();
            $args->skin = Context::get('skin');
            $args->target = Context::get('target');
            $args->target_module_srl = Context::get('target_module_srl');
            $args->lucene_search = Context::get('lucene_search');
            if(!$args->target_module_srl) $args->target_module_srl = '';
            $args->skin_vars = $config->skin_vars;
            $options = Context::get('options_list') ? explode(',',Context::get('options_list')) : array();
            foreach ($options as $option){
                $args->options[$option] = $option;
            }

            $oModuleController = getController('module');
            $output = $oModuleController->insertModuleConfig('search',$args);

            if (!$config->lucene_search && $args->lucene_search == 'Y') {
                $controller = getController('search');
                $controller->reIndex();
            }

			if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
				$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSearchAdminContent');
				header('location:'.$returnUrl);
				return;
			}
			else return $output;
        }

        /**
         * @brief Save the skin information
         **/
        function procSearchAdminInsertSkin() {
            // Get configurations (using module model object)
            $oModuleModel = getModel('module');
            $config = $oModuleModel->getModuleConfig('search');

            $args->skin = $config->skin;
            $args->target_module_srl = $config->target_module_srl;
            // Get skin information (to check extra_vars)
            $skin_info = $oModuleModel->loadSkinInfo($this->module_path, $config->skin);
            // Check received variables (delete the basic variables such as mo, act, module_srl, page)
            $obj = Context::getRequestVars();
            unset($obj->act);
            unset($obj->module_srl);
            unset($obj->page);
            // Separately handle if the extra_vars is an image type in the original skin_info
            if($skin_info->extra_vars) {
                foreach($skin_info->extra_vars as $vars) {
                    if($vars->type!='image') continue;

                    $image_obj = $obj->{$vars->name};
                    // Get a variable on a request to delete
                    $del_var = $obj->{"del_".$vars->name};
                    unset($obj->{"del_".$vars->name});
                    if($del_var == 'Y') {
                        FileHandler::removeFile($module_info->{$vars->name});
                        continue;
                    }
                    // Use the previous data if not uploaded
                    if(!$image_obj['tmp_name']) {
                        $obj->{$vars->name} = $module_info->{$vars->name};
                        continue;
                    }
                    // Ignore if the file is not successfully uploaded
                    if(!is_uploaded_file($image_obj['tmp_name'])) {
                        unset($obj->{$vars->name});
                        continue;
                    }
                    // Ignore if the file is not an image
                    if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $image_obj['name'])) {
                        unset($obj->{$vars->name});
                        continue;
                    }
                    // Upload the file to a path
                    $path = sprintf("./files/attach/images/%s/", $module_srl);
                    // Create a directory
                    if(!FileHandler::makeDir($path)) return false;

                    $filename = $path.$image_obj['name'];
                    // Move the file
                    if(!move_uploaded_file($image_obj['tmp_name'], $filename)) {
                        unset($obj->{$vars->name});
                        continue;
                    }
                    // Change a variable
                    unset($obj->{$vars->name});
                    $obj->{$vars->name} = $filename;
                }
            }
            // Serialize and save
            $args->skin_vars = serialize($obj);

            $oModuleController = getController('module');
			$output = $oModuleController->insertModuleConfig('search',$args);

            $this->setMessage('success_updated', 'info');
			if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) {
				$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispSearchAdminSkinInfo');
				$this->setRedirectUrl($returnUrl);
				return;
			}
			else $output;
        }
    }
?>
