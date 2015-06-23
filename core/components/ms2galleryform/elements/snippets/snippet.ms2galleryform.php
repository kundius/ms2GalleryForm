<?php
/* @var array $scriptProperties */
/* @var ms2GalleryForm $ms2GalleryForm */
$ms2GalleryForm = $modx->getService('ms2GalleryForm', 'ms2GalleryForm', MODX_CORE_PATH . 'components/ms2galleryform/model/ms2galleryform/');
$ms2GalleryForm->initialize($modx->context->key, $scriptProperties);

$tplForm = $ms2GalleryForm->config['tplForm'] = $modx->getOption('tplForm', $scriptProperties, 'tpl.ms2GalleryForm.form');
$tplFiles = $ms2GalleryForm->config['tplFiles'] = $modx->getOption('tplFiles', $scriptProperties, 'tpl.ms2GalleryForm.files');
$tplFile = $ms2GalleryForm->config['tplFile'] = $modx->getOption('tplFile', $scriptProperties, 'tpl.ms2GalleryForm.file', true);
$tplImage = $ms2GalleryForm->config['tplImage'] = $modx->getOption('tplImage', $scriptProperties, 'tpl.ms2GalleryForm.image', true);

$q = $modx->newQuery('msResourceFile');
$q->andCondition(array('parent' => 0, 'resource_id' => $modx->resource->id, 'createdby' => $modx->user->id), null, 1);

$q->sortby('createdon', 'ASC');
$collection = $modx->getIterator('msResourceFile', $q);

$files = '';
/** @var msResourceFile $item */
foreach ($collection as $item) {
    $item = $item->toArray();
    $item['size'] = round($item['properties']['size'] / 1024, 2);
    $tpl = $item['type'] == 'image'
        ? $tplImage
        : $tplFile;
    $files .= $modx->getChunk($tpl, $item);
}
$data['files'] = $modx->getChunk($tplFiles, array(
    'files' => $files,
));

$output = $modx->getChunk($tplForm, $data);
// print_r($ms2GalleryForm->config);
$key = md5($modx->toJSON($ms2GalleryForm->config));
$_SESSION['ms2GalleryForm'][$key] = $ms2GalleryForm->config;
$output = str_ireplace('</form>', "\n<input type=\"hidden\" name=\"form_key\" value=\"{$key}\" class=\"disable-sisyphus\" />\n</form>", $output);

return $output;