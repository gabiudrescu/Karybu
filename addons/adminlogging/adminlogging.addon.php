<?php
if(!defined('__KARYBU__')) exit();

/**
 * @file adminlogging.addon.php
 * @author Arnia (dev@karybu.org)
 * @brief Automatic link add-on
 **/
$logged_info = Context::get('logged_info');
$act  = Context::get('act');
$kind = strpos(strtolower($act),'admin')!==false?'admin':'';

if($called_position == 'before_module_proc' && $kind == 'admin' && $logged_info->is_admin == 'Y') {
	$oAdminloggingController = &getController('adminlogging');
	$oAdminloggingController->insertLog($this->module, $this->act);
}
?>
