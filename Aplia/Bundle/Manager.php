<?php
namespace Aplia\Bundle;

/**
 * Manager for assets which can expand relative paths defined in INI files to
 * full paths (relative to www-root) by looking up files from extensions using
 * the design-resource handler.
 */
class Manager
{
    public $bundles = array();
    public $missingAssets = array();

    public function __construct($bundles=array())
    {
        $this->bundles = $bundles;
    }

    /**
     * Finds assets by looking at INI files with definitions for frontend/backend
     * assets.
     */
    public function discoverAssets()
    {
        $designIni = \eZINI::instance('design.ini');
        $bundles = array(
            'frontendCss' => array(
                'type' => 'css',
                'path' => 'css/frontend.css',
                'files' => array_merge(
                    $designIni->hasVariable('StylesheetSettings', 'PreFrontendCSSFileList') ? $designIni->variable('StylesheetSettings', 'PreFrontendCSSFileList') : array(),
                    $designIni->hasVariable('StylesheetSettings', 'FrontendCSSFileList') ? $designIni->variable('StylesheetSettings', 'FrontendCSSFileList') : array(),
                    $designIni->hasVariable('StylesheetSettings', 'PostFrontendCSSFileList') ? $designIni->variable('StylesheetSettings', 'PostFrontendCSSFileList') : array()
                ),
            ),
            'frontendJs' => array(
                'type' => 'js',
                'path' => 'js/frontend.js',
                'files' => array_merge(
                    $designIni->hasVariable('JavaScriptSettings', 'PreFrontendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'PreFrontendJavaScriptList') : array(),
                    $designIni->hasVariable('JavaScriptSettings', 'FrontendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'FrontendJavaScriptList') : array(),
                    $designIni->hasVariable('JavaScriptSettings', 'PostFrontendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'PostFrontendJavaScriptList') : array()
                ),
            ),
            'backendCss' => array(
                'type' => 'js',
                'path' => 'css/backend.css',
                'files' => array_merge(
                    $designIni->hasVariable('StylesheetSettings', 'PreBackendCSSFileList') ? $designIni->variable('StylesheetSettings', 'PreBackendCSSFileList') : array(),
                    $designIni->hasVariable('StylesheetSettings', 'BackendCSSFileList') ? $designIni->variable('StylesheetSettings', 'BackendCSSFileList') : array(),
                    $designIni->hasVariable('StylesheetSettings', 'PostBackendCSSFileList') ? $designIni->variable('StylesheetSettings', 'PostBackendCSSFileList') : array()
                ),
            ),
            'backendJs' => array(
                'type' => 'js',
                'path' => 'js/backend.js',
                'files' => array_merge(
                    $designIni->hasVariable('JavaScriptSettings', 'PreBackendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'PreBackendJavaScriptList') : array(),
                    $designIni->hasVariable('JavaScriptSettings', 'BackendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'BackendJavaScriptList') : array(),
                    $designIni->hasVariable('JavaScriptSettings', 'PostBackendJavaScriptList') ? $designIni->variable('JavaScriptSettings', 'PostBackendJavaScriptList') : array()
                ),
            ),
            'editorCss' => array(
                'type' => 'css',
                'path' => 'css/editor.css',
                'files' => $designIni->variable('StylesheetSettings', 'EditorCSSFileList'),
            ),
        );
        $bundles = $this->expandAssets($bundles);

        $this->bundles = array_merge($this->bundles, $bundles);
    }

    /**
     * Expands assets paths from relative to extension/design folder to relative
     * to the www-root.
     */
    public function expandAssets($bundles)
    {
        foreach ($bundles as $name => $bundle) {
            if ($bundle['type'] == 'js') {
                $bundle['files'] = $this->expandPath($bundle['files'], 'javascript');
            } elseif ($bundle['type'] == 'css') {
                $bundle['files'] = $this->expandPath($bundle['files'], 'stylesheets');
            }
            $bundles[$name] = $bundle;
        }
        return $bundles;
    }

    public function expandPath($assetList, $subFolder)
    {
        $expandedList = array();
        $bases = \eZTemplateDesignResource::allDesignBases('no');
        foreach ($assetList as $assetPath) {
            if (substr($assetPath, 0, 7) == 'ezjsc::') {
                // TODO: What to do with jscore entries?
            } else {
                $triedFiles = array();
                $match = \eZTemplateDesignResource::fileMatch( $bases, $subFolder, $assetPath, $triedFiles );
                if ( $match === false ) {
                    $this->missingAssets[] = array(
                        'file' => $assetPath,
                        'tried' => $triedFiles,
                    );
                    continue;
                }
                $file = $match['path'];
                $expandedList[] = $file;
            }
        }
        return $expandedList;
    }
}
