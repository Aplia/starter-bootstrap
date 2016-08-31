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

    public function expandAssets()
    {
        foreach ($this->bundles as $name => $bundle) {
            if ($bundle['type'] == 'js') {
                $bundle['files'] = $this->expandPath($bundle['files'], 'javascript');
            } elseif ($bundle['type'] == 'css') {
                $bundle['files'] = $this->expandPath($bundle['files'], 'stylesheets');
            }
            $this->bundles[$name] = $bundle;
        }
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
