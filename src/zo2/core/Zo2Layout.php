<?php
/**
 * Zo2 (http://www.zo2framework.org)
 * A powerful Joomla template framework
 *
 * @link        http://www.zo2framework.org
 * @link        http://github.com/aploss/zo2
 * @author      ZooTemplate <http://zootemplate.com>
 * @copyright   Copyright (c) 2013 APL Solutions (http://apl.vn)
 * @license     GPL v2
 */
defined('_JEXEC') or die;

class Zo2Layout {
    /* private */
    private $_layoutName, $_templatePath, $_layourDir, $_compiledLayoutPath, $_layoutContent, $_layoutPath, $_templateName,
        $_staticsPath, $_coreStaticsPath, $_templateUri = '';
    private $_output = '';
    private $_layoutStatics = array();

    private $_styleDeclaration = array();
    private $_jsDeclaration = array();

    private $_components = array();

    const MEGAMENU_PLACEHOLDER = '<!-- zo2_megamenu_placeholder -->';

    /**
     * Construct a Zo2Layout object
     *
     * @param $templateName
     *
     * @throws Exception
     */
    public function __construct($templateName)
    {
        // assign values to private variables
        $this->_templatePath = JPATH_SITE . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $templateName . DIRECTORY_SEPARATOR;
        $this->_layourDir = JPATH_SITE . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $templateName . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR;
        $this->_layoutPath = $this->_layourDir . 'layout.json';
        //$this->_compiledLayoutPath = $this->_layourDir . 'layout.php';
        //$this->_staticsPath = $this->_layourDir . $layoutName . '.json';
        $this->_coreStaticsPath = $this->_layourDir . 'assets.json';
        $this->_templateName = $templateName;
        $this->_templateUri = JUri::base(true) . '/templates/' . $templateName;

        // check layout existence, if layout not existed, get default layout, which is homepage.php
        if(!file_exists($this->_layoutPath)) throw new Exception('Layout file cannot be found!');

        // get template content
        $this->_layoutStatics = array();
        $this->_layoutContent = file_get_contents($this->_layoutPath);
        $coreStaticsJson = file_get_contents($this->_coreStaticsPath);
        //$staticsJson = file_exists($this->_staticsPath) ? file_get_contents($this->_staticsPath) : array();
        $coreStatics = json_decode($coreStaticsJson, true);
        //$statics = json_decode($staticsJson, true);

        // combine layout statics
        $this->_layoutStatics = $coreStatics;

        $this->importComponents();
        Zo2Framework::import('core.Zo2AssetsHelper');
        Zo2Framework::import('vendor.minify.jsshrink');
        Zo2Framework::import('vendor.minify.css');
    }

    /**
     * Read layout data from json
     *
     * @return string
     */
    public function getLayoutJson()
    {
        $path = $this->_templatePath . 'layouts' . DIRECTORY_SEPARATOR . $this->_layoutName . '.json';
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        else return '';
    }

    /**
     * Insert a static file into output
     *
     * @param $path
     * @param $type
     * @param array $options
     * @param $position string Must be 'footer' or 'header'
     * @return $this
     */
    public function insertStatic($path, $type, array $options = array(), $position)
    {
        $this->_layoutStatics[] = array('path' => $path, 'type' => $type, 'options' => $options, 'position' => $position);
        return $this;
    }

    /**
     * Insert a JS file into output
     *
     * @param $path
     * @param array $options
     * @param string $position
     * @return $this
     */
    public function insertJs($path, array $options = array(), $position = 'footer')
    {
        $this->insertStatic($path, 'js', $options, $position);
        return $this;
    }

    public function insertCssDeclaration($style)
    {
        $this->_styleDeclaration[] = $style;
        return $this;
    }

    public function insertJsDeclaration($js)
    {
        $this->_jsDeclaration[] = $js;
        return $this;
    }

    public function insertLessDeclaration($less)
    {
        if (!class_exists('lessc', false)) Zo2Framework::import('vendor.less.lessc');
        $compiler = new lessc();
        $style = $compiler->compile($less);
        $this->insertCssDeclaration($style);
    }

    /**
     * Insert a CSS file into output
     *
     * @param $path
     * @param array $options
     * @param string $position
     * @return $this
     */
    public function insertCss($path, array $options = array(), $position = 'header')
    {
        $this->insertStatic($path, 'css', $options, $position);
        return $this;
    }

    public function insertLess($path, array $options = array(), $position = 'header')
    {
        $cacheDir = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

        $fileName = md5($path) . '.css';
        $cacheDir = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
        $filePath = $cacheDir . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = '/assets/cache/' . $fileName;
        $content = $this->processLessFile($path);
        file_put_contents($filePath, $content);

        $this->insertCss($relativePath);

        return $this;
    }

    /**
     * Get current layout content
     *
     * @return string
     */
    public function getLayoutContent()
    {
        return $this->_layoutContent;
    }

    /**
     * Insert script tag for js
     *
     * @param $item
     * @return string
     */
    private function generateJsTag($item) {
        $basePath = '';
        if (isset($item['base']) && $item['base'] == 'plugin') $basePath = Zo2Framework::getSystemPluginPath();
        else $basePath = $this->_templateUri;
        $path = strpos($item['path'], 'http://') !== false ? $item['path'] : $basePath . $item['path'];
        $async = "";
        if (isset($item['options']['async'])) $async = " async=\"" . $item['options']['async'] . "\"";
        $result = "<script" . $async . " type=\"text/javascript\" src=\"" . $path . "\"></script>\n";
        if (isset($item['condition']) && !empty($item['condition'])) {
            $result = '<!--[' . $item['condition'] . ']>' . $result . '<![endif]-->';
        }
        return $result;
    }

    /**
     * Insert link tag for css
     *
     * @param $item
     * @return string
     */
    private function generateCssTag($item) {
        $basePath = '';
        if (isset($item['base']) && $item['base'] == 'plugin') $basePath = Zo2Framework::getSystemPluginPath();
        else $basePath = $this->_templateUri;
        $path = strpos($item['path'], 'http://') !== false ? $item['path'] : $basePath . $item['path'];
        $rel = isset($item['options']['rel']) ? $item['options']['rel'] : "stylesheet";
        return "<link rel=\"" . $rel . "\" href=\"" . $path . "\" type=\"text/css\" />\n";
    }

    /**
     * Compile LESS into CSS then
     * Insert link tag for LESS
     *
     * @param $item
     * @return string
     */
    private function generateLessTag($item) {
        $cacheDir = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

        //$fileName = md5($item['path']) . '.css';
        $fileName = str_replace('.less', '.css', basename($item['path']));
        $oldPath = str_replace('.less', '.css', $item['path']);
        $absoluteOldPath = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $this->_templatePath . $oldPath);
        $cacheDir = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
        $filePath = $cacheDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
        $filePath = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $filePath); // fix double slashes
        $pathReplace = array('cache', 'assets', 'less');
        $filePath = str_replace(implode(DIRECTORY_SEPARATOR, $pathReplace), 'css', $filePath);
        $relativePath = str_replace('//', '/', $oldPath);
        $relativePath = str_replace('/less/', '/css/', $relativePath);
        //$content = $this->processLess(file_get_contents($this->_templatePath . $item['path']));
        $content = $this->processLessFile($this->_templatePath . $item['path']);
        $content = CssMinifier::minify($content);
        $content = Zo2AssetsHelper::fixCssUrl($content, $filePath, dirname($absoluteOldPath));
        file_put_contents($filePath, $content);
        if (file_exists($filePath)) file_put_contents($filePath, $content);
        else Zo2AssetsHelper::forcePutContent($filePath, $content);

        $path = $this->_templateUri . $relativePath;
        $rel = isset($item['options']['rel']) ? $item['options']['rel'] : "stylesheet";
        return "<link rel=\"" . $rel . "\" href=\"" . $path . "\" type=\"text/css\" />\n";
    }

    /**
     * Generate body html
     *
     * @return string
     */
    public function generateHtml()
    {
        $app = JFactory::getApplication();
        $template = $app->getTemplate(true);
        $params = $template->params;
        $debug = $params->get('debug_visibility');

        $html = '';
        $menu = &JSite::getMenu();
        $menuItem = $menu->getActive();
        $cache = 'layout_' . $menuItem->id . '.php';
        $layoutCacheDir = $this->_layourDir . 'cache/';
        $path = $layoutCacheDir . $cache;
        if (file_exists($path) && !$debug) {
            $html = file_get_contents($path);
        }
        else {
            $layoutType = $params->get('layout_type');
            if ($layoutType == 'fixed') $layoutType = '';
            else $layoutType = '-fluid';

            if (file_exists($this->_layoutPath))
            {
                $data = json_decode(file_get_contents($this->_layoutPath), true);

                for ($i = 0, $total = count($data); $i < $total; $i++) {
                    $html .= $this->generateHtmlFromItem($data[$i], $layoutType);
                }

                if (!is_dir($layoutCacheDir)) mkdir($layoutCacheDir, 0755);
                file_put_contents($path, $html);
            }
            else return '';
        }
        if (strpos($html, Zo2Layout::MEGAMENU_PLACEHOLDER) !== false) {
            $zo2 = Zo2Framework::getInstance();
            $megamenu = $zo2->displayMegaMenu($zo2->getParams('menutype', 'mainmenu'), $zo2->getTemplate());
            $html = str_replace(Zo2Layout::MEGAMENU_PLACEHOLDER, $megamenu, $html);
        }
        return $html;
    }

    /**
     * Generate html for an item such as a row or a column.
     *
     * @param $item
     * @return string
     */
    private function generateHtmlFromItem($item)
    {
        $html = '';
        if ($item['type'] == 'row') $html .= $this->generateRow($item);
        else if ($item['type'] == 'col') $html .= $this->generateColumn($item);

        return $html;
    }

    private static function generateVisibilityClass($visibilityData)
    {
        $classes = array();
        $classes[] = $visibilityData['xs'] ? 'visible-xs' : 'hidden-xs';
        $classes[] = $visibilityData['sm'] ? 'visible-sm' : 'hidden-sm';
        $classes[] = $visibilityData['md'] ? 'visible-md' : 'hidden-md';
        $classes[] = $visibilityData['lg'] ? 'visible-lg' : 'hidden-lg';
        return implode(' ', $classes);
    }

    /**
     * Generate html from a row item
     *
     * @param $item
     * @return string
     */
    private function generateRow($item)
    {
        //$class = $layoutType == 'fluid' ? 'container' : 'container-fixed';
        $class = $item['fullwidth'] ? '' : 'container';
        $class .= ' ' . self::generateVisibilityClass($item['visibility']);
        $html = '';
        if (!empty($item['id'])) $html .= '<section id="' . $item['id'] . '" class="' . $item['customClass'] . '">';
        else $html .= '<section class="' . $item['customClass'] . '">';
        $html .= '<section class="' . $class . '">'; // start of container
        $html .= '<section class="row">'; // start of row

        // count column and remove empty module here
        $exceptPos = array('header_logo', 'logo', 'menu', 'mega_menu', 'footer_logo', 'footer_copyright', 'component', 'debug', 'message');
        $doc = JFactory::getDocument();
        $freeSpace = 0;
        $totalTakenSpace = 0;
        for ($i = 0, $total = count($item['children']); $i < $total; $i++) {
            $col = $item['children'][$i];
            $modulesInPosition = $doc->countModules($col['position']);
            if (in_array($col['position'], $exceptPos)) $modulesInPosition = max($modulesInPosition, 1);
            if ($modulesInPosition == 0) {
                $freeSpace += $col['span'];
                unset($item['children'][$i]);
                continue;
            }
            else if ($modulesInPosition > 0 && $freeSpace > 0) {
                $item['children'][$i]['span'] += $freeSpace;
                $freeSpace = 0;
            }

            $totalTakenSpace += $item['children'][$i]['span'];
        }

        if ($totalTakenSpace <= 0) return !empty($item['name']) ? '<!-- empty row: ' . $item['name'] . ' -->' : '';

        $tempChildren = array();
        foreach($item['children'] as $c) {
            $tempChildren[] = $c;
        }
        $item['children'] = $tempChildren;

        if ($totalTakenSpace < 12) {
            $remainingSpace = 12 - $totalTakenSpace;
            $totalChildren = count($item['children']);
            $index = $totalChildren - 1;
            if ($index < 0) $index = 0;
            if (isset($item['children'][$index])) $item['children'][$index]['span'] += $remainingSpace;
        }

        for ($i = 0, $total = count($item['children']); $i < $total; $i++) {
            $html .= self::generateHtmlFromItem($item['children'][$i]);
        }
        $html .= '</section>'; // end of row
        $html .= '</section>'; // end of container
        $html .= '</section>'; // end of wrapper
        return $html;
    }

    /**
     * Generate html from a column item
     *
     * @param $item
     * @return string
     */
    private function generateColumn($item)
    {
        //$zo2 = Zo2Framework::getInstance();
        $html = '';
        $class = 'col-md-' . $item['span'];
        $class .= ' ' . self::generateVisibilityClass($item['visibility']);
        //$class = 'col-xs-' . $item['span'] . ' col-md-' . $item['span'] . ' col-lg-' . $item['span'];
        if (!empty($item['customClass'])) $class .= ' ' . $item['customClass'];
        if (!empty($item['id'])) $html .= '<section id="' . $item['id'] . '" class="' . $class . '">';
        else $html .= '<section class="' . $class . '">';

        if (!empty($item['position'])) {

            if (($item['position'] == 'component')&& (!$this->hideComponent())) $html .= '<jdoc:include type="component" />';
            else if (($item['position'] == 'message') && (!$this->hideComponent())) $html .= '<jdoc:include type="message" />';
            else if($item['position'] == 'mega_menu') {
                //$html .= $zo2->displayMegaMenu($zo2->getParams('menutype', 'mainmenu'), $zo2->getTemplate());
                $html .= Zo2Layout::MEGAMENU_PLACEHOLDER;
            }
            else {
                $moduleJdoc = '<jdoc:include type="modules" name="' . $item['position'] . '"  style="' . $item['style'] . '" />';
                $componentHtml = '';
                if (isset($this->_components[$item['position']]) && $componentPath = $this->_components[$item['position']]) {
                    $componentClassName = "Zo2Component_" . $item['position'];
                    if (file_exists($componentPath)) require_once $componentPath;
                    if (class_exists($componentClassName)) {
                        $component = new $componentClassName();
                        if ($component instanceof Zo2Component) {
                            $componentHtml = $component->render();

                            if ($component->position == Zo2Component::RENDER_BEFORE) $html .= $componentHtml . "\n" . $moduleJdoc;
                            else if ($component->position == Zo2Component::RENDER_AFTER) $html .= $moduleJdoc . "\n" . $componentHtml;
                        }
                    }
                }

                if (empty($componentHtml)) $html .= $moduleJdoc;
            }
        }

        if ($total = count($item['children']) > 0) {
            for ($i = 0; $i < $total; $i++) {
                $html .= self::generateHtmlFromItem($item['children'][$i]);
            }
        }

        $html .= '</section>';
        return $html;
    }

    /**
     * Generate header assets html. Usable from frontend.
     *
     * Cache result into PHP file, create on the first time
     *
     * @return string
     */
    public function insertHeaderAssets()
    {
        $app = JFactory::getApplication();
        $template = $app->getTemplate(true);
        $params = $template->params;
        $debug = $params->get('debug_visibility');
        $cache = 'header.php';
        $path = $this->_layourDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $cache;
        if (file_exists($path) && !$debug) return file_get_contents($path);
        else {
            $html = '';
            $responsive = $params->get('responsive_layout');
            $preset = $params->get('theme');
            $combineLevel = $params->get('combine_statics', 0);
            $combineJs = $params->get('combine_js', 0);
            $combineCss = $params->get('combine_css', 0);
            $compileLess = $params->get('less_compile');

            if ($compileLess == 1) {
                $pathToCache = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
                $cachedCss = glob($pathToCache . DIRECTORY_SEPARATOR . '*.css');
                foreach($cachedCss as $css) {
                    try {
                        unlink($css);
                    }
                    catch(Exception $e) {}
                }
            }

            // insert preset data
            if (empty($preset)) {
                $zo2 = Zo2Framework::getInstance();
                $presetPath = $zo2->getCurrentTemplateAbsolutePath() . '/layouts/presets.json';
                $presets = array();
                if (file_exists($presetPath)) {
                    $presets = json_decode(file_get_contents($presetPath), true);
                }
                $defaultData = array();
                for ($i= 0; $i < count($presets); $i++) {
                    if ($presets[$i]['default']) $defaultData = $presets[$i];
                }
                if (empty($defaultData) && count($presets) > 0) $presetData = $presets[0];
                else $presetData = array(
                    'name' => $defaultData['name'],
                    'css' => $defaultData['css'],
                    'less' => $defaultData['less'],
                    'background' => $defaultData['variables']['background'],
                    'header' => $defaultData['variables']['header'],
                    'header_top' => $defaultData['variables']['header_top'],
                    'text' => $defaultData['variables']['text'],
                    'link' => $defaultData['variables']['link'],
                    'link_hover' => $defaultData['variables']['link_hover'],
                    'bottom1' => $defaultData['variables']['bottom1'],
                    'bottom2' => $defaultData['variables']['bottom2'],
                    'footer' => $defaultData['variables']['footer']
                );
            }
            if(!empty($preset)) $presetData = json_decode($preset, true);
            $style = '';
            if (!empty($presetData['background'])) $style .= 'body{background-color:' . $presetData['background'] . '}';
            if (!empty($presetData['header'])) $style .= '#zo2-header{background-color:' . $presetData['header'] . '}';
            if (!empty($presetData['header_top'])) $style .= '#zo2-header-top{background-color:' . $presetData['header_top'] . '}';
            if (!empty($presetData['text'])) $style .= 'body{color:' . $presetData['text'] . '}';
            if (!empty($presetData['link'])) $style .= 'a{color:' . $presetData['link'] . '}';
            if (!empty($presetData['link_hover'])) $style .= 'a:hover{color:' . $presetData['link_hover'] . '}';
            if (!empty($presetData['bottom1'])) $style .= '#zo2-bottom1{background-color:' . $presetData['bottom1'] . '}';
            if (!empty($presetData['bottom2'])) $style .= '#zo2-bottom2{background-color:' . $presetData['bottom2'] . '}';
            if (!empty($presetData['footer'])) $style .= '#zo2-footer{background-color:' . $presetData['footer'] . '}';
            if (!empty($presetData['css'])) $this->insertCss($presetData['css']);
            $this->insertCssDeclaration($style);

            if (!$responsive)
                $this->insertCss('/assets/css/non-responsive.css');

            $this->insertCustomFontStyles();

            if (empty($combineLevel) || $combineLevel == '0') {
                foreach($this->_layoutStatics as $item) {
                    if ($item['position'] == 'header') {
                        if ($item['type'] == 'css') $html .= $this->generateCssTag($item);
                        elseif ($item['type'] == 'js') $html .= $this->generateJsTag($item);
                        elseif ($item['type'] == 'less') {
                            $html .= $this->generateLessTag($item);
                        }
                    }
                }
            }
            else {
                $cacheDir = $this->_templatePath . 'assets' . DIRECTORY_SEPARATOR . 'cache';
                $cssPath = $cacheDir . DIRECTORY_SEPARATOR . 'style.css';
                $jsPath = $cacheDir . DIRECTORY_SEPARATOR . 'script.js';
                $cssUri = $this->_templateUri . '/assets/cache/style.css';
                $jsUri = $this->_templateUri . '/assets/cache/script.js';

                if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
                $combineScript = '';
                $combineStyle = '';

                foreach ($this->_layoutStatics as $item) {
                    if ($item['position'] == 'header') {
                        $assetPath = $this->_templatePath . $item['path'];
                        $assetPath = str_replace('//', '/', $assetPath);

                        if ($item['type'] == 'css') {
                            $tempStyle = CssMinifier::minify(file_get_contents($assetPath));

                            Zo2AssetsHelper::fixCssUrl($tempStyle, $cssPath, $assetPath);

                            $combineStyle .= '/* ' . basename($assetPath) . ' */' . "\n" . $tempStyle . "\n";
                        }
                        elseif ($item['type'] == 'js') $combineScript .= file_get_contents($assetPath) . "\n";
                        elseif ($item['type'] == 'less') {
                            $tempStyle = CssMinifier::minify($this->processLessFile($assetPath));

                            Zo2AssetsHelper::fixCssUrl($tempStyle, $cssPath, $assetPath);

                            $combineStyle .= '/* ' . basename($assetPath) . ' */' . "\n" . $tempStyle . "\n";
                        }
                    }
                }

                $combineStyle = Zo2AssetsHelper::moveCssImportToBeginning($combineStyle);

                Zo2AssetsHelper::forcePutContent($cssPath, $combineStyle);
                Zo2AssetsHelper::forcePutContent($jsPath, $combineScript);

                $cssTag = '<link rel="stylesheet" type="text/css" href="' . $cssUri . '" />';
                $jsTag = '<script type="text/javascript" src="' . $jsUri . '"></script>';
                $html .= "\n" . $cssTag . "\n" . $jsTag;
            }

            if (count($this->_styleDeclaration) > 0) {
                $styles = '';
                foreach ($this->_styleDeclaration as $style) {
                    $styles .= $style . "\n";
                }

                $styles = '<style type="text/css">' . $styles . '</style>';
                $html .= "\n" . $styles;
            }

            file_put_contents($path, $html);
            return $html;
        }
    }

    /**
     * Generate custom CSS style for custom Font options.
     * Font options can be changed from backend
     */
    private function insertCustomFontStyles()
    {
        $app = JFactory::getApplication();
        $template = $app->getTemplate(true);
        $params = $template->params;

        $selectors = array('body_font' => 'body', 'h1_font' => 'h1',
            'h2_font' => 'h2', 'h3_font' => 'h3', 'h4_font' => 'h4',
            'h5_font' => 'h5', 'h6_font' => 'h6'
        );

        foreach ($selectors as $param => $selector) {
            $value = $params->get($param);

            if (!empty($value)) {
                $data = json_decode($value, true);
                $style = '';
                switch($data['type']) {
                    case 'standard':
                        $style = $this->buildStandardFontStyle($data, $selector);
                        break;
                    case 'googlefonts':
                        $style = $this->buildGoogleFontsStyle($data, $selector);
                        break;
                    case 'fontdeck':
                        $style = $this->buildFontDeckStyle($data, $selector);
                        break;
                    default:
                        break;
                }

                if (!empty($style)) $this->insertCssDeclaration($style);
            }
        }
    }

    /**
     * Generate custom CSS style for Standard Font option
     *
     * @param $data
     * @param $selector
     * @return string
     */
    private function buildStandardFontStyle($data, $selector)
    {
        $style = '';
        if (!empty($data['family'])) $style .= 'font-family:' . $data['family'] . ';';
        if (!empty($data['size']) && $data['size'] > 0) $style .= 'font-size:' . $data['size'] . 'px;';
        if (!empty($data['color'])) $style .= 'color:' . $data['color'] . ';';
        if (!empty($data['style'])) {
            switch($data['style']) {
                case 'b': $style .= 'font-weight:bold;'; break;
                case 'i': $style .= 'font-style:italic;'; break;
                case 'bi':
                case 'ib':
                    $style .= 'font-weight:bold;font-style:italic;';
                    break;
                default:
                    break;
            }
        }

        if (!empty($style)) $style = $selector . '{' . $style . '}' . "\n";

        return $style;
    }

    /**
     * Generate custom CSS style for Google Fonts option
     *
     * @param $data
     * @param $selector
     * @return string
     */
    private function buildGoogleFontsStyle($data, $selector)
    {
        $api = 'http://fonts.googleapis.com/css?family=';
        $style = '';
        if (!empty($data['family'])) {
            $style .= 'font-family:' . urldecode($data['family']) . ';';
            $this->insertCss($api . $data['family']);
        }
        else return '';
        if (!empty($data['size']) && $data['size'] > 0) $style .= 'font-size:' . $data['size'] . 'px;';
        if (!empty($data['color'])) $style .= 'color:' . $data['color'] . ';';
        if (!empty($data['style'])) {
            switch($data['style']) {
                case 'b': $style .= 'font-weight:bold;'; break;
                case 'i': $style .= 'font-style:italic;'; break;
                case 'bi':
                case 'ib':
                    $style .= 'font-weight:bold;font-style:italic;';
                    break;
                default:
                    break;
            }
        }

        if (!empty($style)) $style = $selector . '{' . $style . '}' . "\n";
        return $style;
    }

    /**
     * Generate custom CSS style for FontDeck option
     *
     * @param $data
     * @param $selector
     * @return string
     */
    private function buildFontDeckStyle($data, $selector)
    {
        $app = JFactory::getApplication();
        $template = $app->getTemplate(true);
        $params = $template->params;

        $fontdeckCode = $params->get('fontdeck_code');

        if (!empty($fontdeckCode)) {
            $this->insertJsDeclaration($fontdeckCode);
        }

        $style = '';
        if (!empty($data['family'])) $style .= 'font-family:' . $data['family'] . ';';
        if (!empty($data['size']) && $data['size'] > 0) $style .= 'font-size:' . $data['size'] . 'px;';
        if (!empty($data['color'])) $style .= 'color:' . $data['color'] . ';';
        if (!empty($data['style'])) {
            switch($data['style']) {
                case 'b': $style .= 'font-weight:bold;'; break;
                case 'i': $style .= 'font-style:italic;'; break;
                case 'bi':
                case 'ib':
                    $style .= 'font-weight:bold;font-style:italic;';
                    break;
                default:
                    break;
            }
        }

        if (!empty($style)) $style = $selector . '{' . $style . '}' . "\n";

        return $style;
    }

    /**
     * Generate footer assets html. Usable from frontend.
     *
     * Cache result into PHP file, create on the first time
     *
     * @return string
     */
    public function insertFooterAssets()
    {
        //dirty fix
        //$this->insertJsDeclaration('jQuery(document).ready(function($){window.$ = jQuery});');
        $app = JFactory::getApplication();
        $template = $app->getTemplate(true);
        $params = $template->params;
        $debug = $params->get('debug_visibility');
        $cache = 'footer.php';
        $path = $this->_layourDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $cache;
        if (file_exists($path) && !$debug) return file_get_contents($path);
        else {
            $html = '';

            foreach($this->_layoutStatics as $item) {
                if ($item['position'] == 'footer') {
                    if ($item['type'] == 'css') $html .= $this->generateCssTag($item);
                    elseif ($item['type'] == 'js') $html .= $this->generateJsTag($item);
                }
            }

            if (count($this->_jsDeclaration) > 0) {
                $scripts = implode("\n", $this->_jsDeclaration);
                $scripts = '<script type="text/javascript">' . $scripts . '</script>';
                $html .= $scripts;
            }
            file_put_contents($path, $html);

            return $html;
        }
    }

    /**
     * Generate CSS from LESS
     *
     * @param $content
     * @return string
     */
    private function processLess($content) {
        if (!class_exists('lessc', false)) Zo2Framework::import('vendor.less.lessc');

        $compiler = new lessc();

        return $compiler->compile($content);
    }

    private function processLessFile($path)
    {
        if (!class_exists('lessc', false)) Zo2Framework::import('vendor.less.lessc');

        $compiler = new lessc();

        return $compiler->compileFile($path);
    }

    /**
     * Remove whitespace, tab, new line from input
     *
     * @param $input
     * @return mixed
     */
    public static function compressHtml($input) {
        $input = str_replace("\n\n", "\n", $input);
        $input = str_replace("\r\r", "\r", $input);
        return $input;
    }

    /**
     * Compress JS with Google Closure. Work in progress.
     */
    public function combineJS() {
        if(!class_exists('PhpClosure', false)) {
            Zo2Framework::import('vendor.minify.closure');
        }
    }

    /**
     * Set Google fonts for some font element.
     *
     * @param $fontname
     */
    public function setGoogleFont($fontname) {
        $fontname = explode('|', $fontname);

        if (count($fontname) != 2) return;

        $fontPath = 'http://fonts.googleapis.com/css?family=' . $fontname[1];
        $this->insertCss($fontPath);
        $selectors = 'body, input, button, select, textarea, .navbar-search .search-query';
        $options = "\n";
        $options .= $selectors . '{';
        $options .= 'font-family:\'' . $fontname[0] . '\', Helvetica, Arial, sans-serif';
        $options .= '}';
        $options .= "\n";

        $this->_styleDeclaration[] = $options;
    }

    /**
     * Get template state
     * Work in progress
     *
     * @return string
     */
    private function getState() {
        $path = $this->_templatePath . 'runtime' . DIRECTORY_SEPARATOR . 'state.php';

        if (!file_exists($path)) {
            $state = uniqid();
            file_put_contents($path, $state);
            return $state;
        }
        else {
            $state = file_get_contents($path);
            if (empty($state)) {
                $state = uniqid();
                file_put_contents($path, $state);
                return $state;
            }
            else return $state;
        }
    }

    /**
     * Set template state
     * Work in progress
     *
     * @param null $state
     */
    private function setState($state = null) {
        if (!isset($state)) $state = uniqid();

        $path = $this->_templatePath . 'runtime' . DIRECTORY_SEPARATOR . 'state.php';

        file_put_contents($path, $state);
    }

    /**
     * Import components
     */
    public function importComponents()
    {
        $zo2 = Zo2Framework::getInstance();
        $pluginComponentsPath = $zo2->getPluginPath() . '/components/*.php';
        $templateComponentsPath = $this->_templatePath . 'components/*.php';

        $pluginComponents = glob($pluginComponentsPath);
        $templateComponents = glob($templateComponentsPath);

        foreach($pluginComponents as $comp) {
            $compName = JFILE::stripExt(basename($comp));
            $this->_components[$compName] = $comp;
        }

        foreach($templateComponents as $comp) {
            $compName = JFILE::stripExt(basename($comp));
            $this->_components[$compName] = $comp;
        }
    }

    public function getBodyClass($customClass = '')
    {
        //$jinput = JFactory::getApplication()->input;
        $classes = array();
        //$classes[] = $jinput->get('view');
        $classes[] = Zo2Framework::getCurrentPage();

        return trim(implode(' ', $classes) . ' ' . $customClass);
    }

    /**
     * Hide component from frontpage
     * @return bool
     */
    private static function hideComponent()
    {
        $params = Zo2Framework::getParams();
        $isFrontPage = Zo2Framework::isFrontPage();
        if ((int)$params->get('component_area', 0) && $isFrontPage) {
            return true;
        } else {
            return false;
        }

    }


}