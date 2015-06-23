<?php

class ms2GalleryForm {
    /** @var modX $modx */
    public $modx;
    /** @var modMediaSource $mediaSource */
    public $ms2Gallery;
    public $initialized = array();

    /**
     * @param modX $modx
     * @param array $config
     */
    function __construct(modX &$modx, array $config = array()) {
        $this->modx =& $modx;

        $this->ms2Gallery = $this->modx->getService('ms2Gallery', 'ms2Gallery', MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/');

        $corePath = $this->modx->getOption('ms2galleryForm.core_path', $config, $this->modx->getOption('core_path') . 'components/ms2galleryform/');
        $assetsUrl = $this->modx->getOption('ms2galleryForm.assets_url', $config, $this->modx->getOption('assets_url') . 'components/ms2galleryform/');
        $actionUrl = $this->modx->getOption('ms2galleryForm.action_url', $config, $assetsUrl . 'action.php');
        $connectorUrl = $assetsUrl . 'connector.php';

        $this->config = array_merge(array(
            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . 'css/',
            'jsUrl' => $assetsUrl . 'js/',
            'imagesUrl' => $assetsUrl . 'images/',

            'connector_url' => $connectorUrl,
            'actionUrl' => $actionUrl,

            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'ctx' => 'web',
            'json_response' => false
        ), $config);

        $this->modx->addPackage('ms2galleryForm', $this->config['modelPath']);
        $this->modx->lexicon->load('ms2galleryform:default');
    }


    /**
     * Initializes component into different contexts.
     *
     * @param string $ctx The context to load. Defaults to web.
     * @param array $scriptProperties
     *
     * @return boolean
     */
    public function initialize($ctx = 'web', $scriptProperties = array()) {
        $this->config = array_merge($this->config, $scriptProperties);

        $this->config['ctx'] = $ctx;
        if (empty($this->initialized[$ctx])) {
            $properties = $this->ms2Gallery->getSourceProperties();
            $config_js = array(
                'ctx'       => $ctx,
                'jsUrl'     => $this->config['jsUrl'] . 'web/',
                'cssUrl'    => $this->config['cssUrl'] . 'web/',
                'actionUrl' => $this->config['actionUrl'],
                'source'    => array(
                    'size' => !empty($properties['maxUploadSize'])
                        ? $properties['maxUploadSize']
                        : 3145728,
                    'height' => !empty($properties['maxUploadHeight'])
                        ? $properties['maxUploadHeight']
                        : 1080,
                    'width' => !empty($properties['maxUploadWidth'])
                        ? $properties['maxUploadWidth']
                        : 1920,
                    'extensions' => !empty($properties['allowedFileTypes'])
                        ? $properties['allowedFileTypes']
                        : 'jpg,jpeg,png,gif'
                ),
            );
            $this->modx->regClientStartupScript('<script type="text/javascript">ms2GalleryFormConfig=' . $this->modx->toJSON($config_js) . '</script>', true);

            $css = !empty($this->config['frontend_css'])
                ? $this->config['frontend_css']
                : $this->config['cssUrl'] . 'web/default.css';
            if (!empty($css) && preg_match('/\.css/i', $css)) {
                $this->modx->regClientCSS($css);
            }

            $js = !empty($this->config['frontend_js'])
                ? $this->config['frontend_js']
                : $this->config['jsUrl'] . 'web/default.js';
            if (!empty($js) && preg_match('/\.js/i', $js)) {
                $this->modx->regClientScript($js);
            }

            $this->modx->regClientScript($this->config['jsUrl'] . 'web/lib/plupload/plupload.full.min.js');
            $this->modx->regClientScript($this->config['jsUrl'] . 'web/files.js');

            $lang = $this->modx->getOption('cultureKey');
            if ($lang != 'en' && file_exists($this->config['jsUrl'] . 'web/lib/plupload/i18n/' . $lang . '.js')) {
                $this->modx->regClientScript($this->config['jsUrl'] . 'web/lib/plupload/i18n/' . $lang . '.js');
            }

            $this->initialized[$ctx] = true;
        }

        return true;
    }


    /**
     * Upload file
     *
     * @param $data
     * @param string $class
     *
     * @return array|string
     */
    public function fileUpload($data) {
//        if (!$this->authenticated || empty($this->config['allowFiles'])) {
//            return $this->error('ms2galleryform_err_access_denied');
//        }

        /** @var modProcessorResponse $response */
        $response = $this->modx->runProcessor('web/gallery/upload', $data, array(
            'processors_path' => $this->config['corePath'] . 'processors/'
        ));
        if ($response->isError()) {
            return $this->error($response->getMessage());
        }
        $file = $response->getObject();
        $this->modx->log(null,$this->modx->toJSON($file));
        $file['size'] = round($file['properties']['size'] / 1024, 2);

        $tpl = $file['type'] == 'image'
            ? $this->config['tplImage']
            : $this->config['tplFile'];
        $html = $this->modx->getChunk($tpl, $file);

        return $this->success('', $html);
    }


    /**
     * Delete or restore uploaded file
     *
     * @param $id
     *
     * @return array|string
     */
    public function fileRemove($id) {
//        if (!$this->authenticated || empty($this->config['allowFiles'])) {
//            return $this->error('ms2galleryform_err_access_denied');
//        }
        /** @var modProcessorResponse $response */
        $response = $this->modx->runProcessor('web/gallery/remove', array('id' => $id), array(
            'processors_path' => $this->config['corePath'] . 'processors/'
        ));
        if ($response->isError()) {
            return $this->error($response->getMessage());
        }

        return $this->success();
    }


    /**
     * This method returns an error of the cart
     *
     * @param string $message A lexicon key for error message
     * @param array $data Additional data
     * @param array $placeholders Array with placeholders for lexicon entry
     *
     * @return array|string $response
     */
    public function error($message = '', $data = array(), $placeholders = array()) {
        $response = array(
            'success' => false,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        );

        return $this->config['json_response']
            ? $this->modx->toJSON($response)
            : $response;
    }


    /**
     * This method returns an success of the cart
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    public function success($message = '', $data = array(), $placeholders = array()) {
        $response = array(
            'success' => true,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        );

        return $this->config['json_response']
            ? $this->modx->toJSON($response)
            : $response;
    }

}
