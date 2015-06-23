<?php

class msResourceFileUploadProcessor extends modObjectProcessor {
    /** @var modResource $resource */
    private $resource = 0;
    /** @var modMediaSource $mediaSource */
    public $mediaSource;
    public $languageTopics = array('ms2gallery:default');


    /**
     * @return bool|null|string
     */
    public function initialize() {
        /* @var modResource $resource */
        $id = $this->getProperty('id', @$_GET['id']);
        if (!$resource = $this->modx->getObject('modResource', $id)) {
            return $this->modx->lexicon('ms2gallery_err_no_resource');
        }
        $ctx = $resource->get('context_key');
        $properties = $resource->getProperties('ms2gallery');
        $source = $properties['media_source'];

        /** @var ms2Gallery $ms2Gallery */
        if (!$ms2Gallery = $this->modx->getService('ms2gallery')) {
            return 'Could not load class ms2Gallery!';
        }
        elseif (!$this->mediaSource = $ms2Gallery->initializeMediaSource($ctx, $source)) {
            return $this->modx->lexicon('ms2gallery_err_no_source');
        }

        $this->resource = $resource;
        return true;
    }


    /**
     * @return array|string
     */
    public function process() {
        if (!$data = $this->handleFile()) {
            return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_ns'));
        }

        $properties = $this->mediaSource->getPropertyList();
        $pathinfo = preg_match('#[а-яё]#im', $data['name'])
            ? $this->_pathinfo($data['name'])
            : pathinfo($data['name']);
        $extension = strtolower($pathinfo['extension']);
        $filename = strtolower($pathinfo['filename']);

        $image_extensions = $allowed_extensions = array();
        if (!empty($properties['imageExtensions'])) {
            $image_extensions = array_map('trim', explode(',', strtolower($properties['imageExtensions'])));
        }
        if (!empty($properties['allowedFileTypes'])) {
            $allowed_extensions = array_map('trim', explode(',', strtolower($properties['allowedFileTypes'])));
        }
        if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions)) {
            return $this->failure($this->modx->lexicon('ms2gallery_err_wrong_ext'));
        }
        else {
            if (in_array($extension, $image_extensions)) {
                if (empty($data['properties']['height']) || empty($data['properties']['width'])) {
                    return $this->failure($this->modx->lexicon('ms2gallery_err_wrong_image'));
                }
                $type = 'image';
            }
            else {
                $type = $extension;
            }
        }

        if ($this->modx->getOption('ms2gallery_duplicate_check', null, true, true)) {
            if ($this->modx->getCount('msResourceFile', array('resource_id' => $this->resource->id, 'hash' => $data['hash'], 'parent' => 0))) {
                return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_exists'));
            }
        }

        $filename = !empty($properties['imageNameType']) && $properties['imageNameType'] == 'friendly'
            ? $this->resource->cleanAlias($filename)
            : $data['hash'];
        $filename .= '.' . $extension;
        $tmp_filename = $filename;
        $i = 1;
        while (true) {
            if (!$count = $this->modx->getCount('msResourceFile', array('resource_id' => $this->resource->id, 'file' => $tmp_filename, 'parent' => 0))) {
                $filename = $tmp_filename;
                break;
            }
            else {
                $pcre = '#(-'.($i - 1).'|)\.'.$extension.'$#';
                $tmp_filename = preg_replace($pcre, "-$i.$extension", $tmp_filename);
                $i ++;
            }
        }

        $rank = isset($properties['imageUploadDir']) && empty($properties['imageUploadDir'])
            ? 0
            : $this->modx->getCount('msResourceFile', array('parent' => 0, 'resource_id' => $this->resource->id));

        /* @var msResourceFile $product_file */
        $product_file = $this->modx->newObject('msResourceFile', array(
            'resource_id' => $this->resource->id,
            'parent' => 0,
            'name' => preg_replace('#\.' . $extension . '$#i', '', $data['name']),
            'file' => $filename,
            'path' => $this->resource->id . '/',
            'source' => $this->mediaSource->get('id'),
            'type' => $type,
            'rank' => $rank,
            'createdon' => date('Y-m-d H:i:s'),
            'createdby' => $this->modx->user->id,
            'active' => 1,
            'hash' => $data['hash'],
            'properties' => $data['properties'],
        ));

        $this->mediaSource->createContainer($product_file->get('path'), '/');
        $this->mediaSource->errors = array();
        if ($this->mediaSource instanceof modFileMediaSource) {
            $upload = $this->mediaSource->createObject($product_file->get('path'), $product_file->get('file'), '');
            if ($upload) {
                copy($data['tmp_name'], urldecode($upload));
            }
        }
        else {
            $upload = $this->mediaSource->uploadObjectsToContainer($product_file->get('path'), array($data));
        }
        unlink($data['tmp_name']);

        if ($upload) {
            $url = $this->mediaSource->getObjectUrl($product_file->get('path') . $product_file->get('file'));
            $product_file->set('url', $url);
            $product_file->save();

            if (empty($rank)) {
                $imagesTable = $this->modx->getTableName('msResourceFile');
                $sql = "UPDATE {$imagesTable} SET rank = rank + 1 WHERE resource_id ='" . $this->resource->id . "' AND id !='" . $product_file->get('id') . "'";
                $this->modx->exec($sql);
            }

            $generate = $product_file->generateThumbnails($this->mediaSource);
            if ($generate !== true) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not generate thumbnails for image with id = ' . $product_file->get('id') . '. ' . $generate);
                return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_thumb'));
            }
            else {
                return $this->success('', $product_file->toArray());
            }
        }
        else {
            return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_save') . ': ' . print_r($this->mediaSource->getErrors(), 1));
        }
    }


    /**
     * @return array|bool
     */
    public function handleFile() {
        $tf = tempnam(MODX_BASE_PATH, 'ms2g_');

        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $name = $_FILES['file']['name'];
            move_uploaded_file($_FILES['file']['tmp_name'], $tf);
        }
        else {
            $file = $this->getProperty('file');
            if (!empty($file) && (file_exists($file) || strpos($file, '://') !== false)) {
                $tmp = explode('/', $file);
                $name = end($tmp);
                $stream = fopen($file, 'r');
                $res = fopen($tf, 'w');
                while (!feof($stream)) {
                    fwrite($res, fread($stream, 8192));
                }
                fclose($stream);
                fclose($res);
            }
        }

        if (file_exists($tf) && !empty($name)) {
            $res = fopen($tf, 'r');
            $hash = sha1(fread($res, 8192));
            fclose($res);
            $data = array(
                'name' => $name,
                'tmp_name' => $tf,
                'hash' => $hash,
                'properties' => array(
                    'size' => filesize($tf),
                )
            );
            $tmp = getimagesize($tf);
            if (is_array($tmp)) {
                $data['properties'] = array_merge(
                    $data['properties'],
                    array(
                        'width' => $tmp[0],
                        'height' => $tmp[1],
                        'bits' => $tmp['bits'],
                        'mime' => $tmp['mime'],
                    )
                );
            }

            return $data;
        }
        else {
            unlink($tf);

            return false;
        }
    }


    /**
     * @param $path_file
     *
     * @return array
     */
    protected function _pathinfo($path_file) {
        $path_file = strtr($path_file, array('\\' => '/'));

        preg_match("#[^/]+$#", $path_file, $file);
        preg_match("#([^/]+)[.$]+(.*)#", $path_file, $file_ext);
        preg_match("#(.*)[/$]+#", $path_file, $dirname);

        return array(
            'dirname' => $dirname[1] ?: '.',
            'basename' => $file[0],
            'extension' => (isset($file_ext[2]))
                ? $file_ext[2]
                : '',
            'filename' => (isset($file_ext[1]))
                ? $file_ext[1]
                : $file[0]
        );
    }

}

return 'msResourceFileUploadProcessor';