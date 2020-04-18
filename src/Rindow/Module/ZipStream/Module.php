<?php
namespace Rindow\Module\ZipStream;

class Module
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'components' => array(
                    'Rindow\\Module\\ZipStream\\ZipStreamFactory' => array(
                    ),
                ),
            ),
        );
    }
}
