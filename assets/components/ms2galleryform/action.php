<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (empty($_REQUEST['action'])) {
	die('Access denied');
}
else {
	$action = $_REQUEST['action'];
}

define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/index.php';

$modx->getService('error','error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

// Get properties
$properties = array();
/* @var TicketThread $thread */
if (!empty($_REQUEST['form_key']) && isset($_SESSION['ms2GalleryForm'][$_REQUEST['form_key']])) {
    $properties = $_SESSION['ms2GalleryForm'][$_REQUEST['form_key']];
}
elseif (!empty($_SESSION['ms2GalleryForm'])) {
    $properties = $_SESSION['ms2GalleryForm'];
}

// Switch context
$context = 'web';
if (!empty($_REQUEST['id']) && $resource = $modx->getObject('modResource', $_REQUEST['id'])) {
	$context = $resource->get('context_key');
}
elseif (!empty($_REQUEST['ctx']) && $modx->getCount('modContext', $_REQUEST['ctx'])) {
	$context = $_REQUEST['ctx'];
}
if ($context != 'web') {
	$modx->switchContext($context);
}

/* @var ms2GalleryForm $ms2GalleryForm */
define('MODX_ACTION_MODE', true);
$ms2GalleryForm = $modx->getService('ms2galleryform','ms2GalleryForm',$modx->getOption('ms2GalleryForm.core_path',null,$modx->getOption('core_path').'components/ms2galleryform/').'model/ms2galleryform/', $properties);
if ($modx->error->hasError() || !($ms2GalleryForm instanceof ms2GalleryForm)) {
    die('Error');
}

/* @var ms2GalleryForm $ms2GalleryForm */
define('MODX_ACTION_MODE', true);

switch ($action) {
    case 'gallery/file/upload': $response = $ms2GalleryForm->fileUpload($_POST); break;
    case 'gallery/file/remove': $response = $ms2GalleryForm->fileRemove($_POST['id']); break;
	default:
		$message = $_REQUEST['action'] != $action ? 'tickets_err_register_globals' : 'tickets_err_unknown';
		$response = $modx->toJSON(array('success' => false, 'message' => $modx->lexicon($message)));
}

if (is_array($response)) {
	$response = $modx->toJSON($response);
}

@session_write_close();
exit($response);